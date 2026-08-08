<?php

declare(strict_types=1);

namespace Jamasa\Core\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the checkout identity-wipe bug (2026-08-09).
 *
 * WHAT BROKE: famedo.js deleted the `checkout_fields` cache on EVERY page load
 * while `body.famedo-authed` was present, on the premise that "once logged in,
 * the ACCOUNT is the source of truth for checkout identity". That premise is
 * false for a passwordless account — it is created from an e-mail alone, so
 * first_name/last_name/telephone are empty. Returning from the address modal
 * reloaded the page, the only copy of what the customer had typed was deleted,
 * and the form re-rendered blank from the empty account. E-mail survived
 * because e-mail IS on the account. The customer could not recover by retyping:
 * the next reload wiped it again. It only bites the FIRST order of such an
 * account (afterSaveOrder persists the identity), which is why every earlier
 * e2e missed it and a live client demo found it.
 *
 * THE FIX has one invariant, spread over three files:
 *   1. tab-fields.blade.php  — $identityLocked: does the ACCOUNT carry an identity?
 *   2. _layouts/default.blade.php — the same predicate, emitted as a body class
 *   3. famedo.js — drop the cache ONLY when that class is present
 *
 * (1) and (2) must stay identical or the class lies about the form's state, and
 * a code comment saying "keep these identical" is not a guard — this is.
 *
 * ⚠️ SCOPE, honestly: these are structural assertions over the shipped source,
 * not a browser test. They prove the invariant is still WIRED (the thing that
 * silently came undone), not that the browser behaves. The behavioural check is
 * the manual e2e: a FRESH OTP account, because an account that has already
 * ordered has a filled profile and cannot reproduce the bug.
 *
 * HOW TO RUN — deliberately dependency-free: it boots no framework, touches no
 * DB and loads no extension class, so it runs in any PHP 8.3 with the dev deps
 * present. The production image ships --no-dev (no phpunit) and the host has no
 * php at all, so use a throwaway container over the host checkout:
 *
 *   docker run --rm -v "$PWD:/app" -w /app --entrypoint php \
 *     iamnothardcoded/tastyigniter:<tag> \
 *     vendor/bin/phpunit extensions/jamasa/core/tests/CheckoutIdentityGuardTest.php --testdox
 *
 * ⚠️ Running the whole `Extensions` suite that way reports ~13 pre-existing
 * "Class not found" errors (EmailNormalizer, DiscountAllocator): TastyIgniter
 * registers extension namespaces at boot, which a bare runner never does. That
 * is a limitation of the runner, NOT a regression — this file is unaffected by
 * design.
 *
 * MUTATION-TESTED 2026-08-09 (a guard that cannot fail is theatre): ungating the
 * session wipe reproduces the original bug and fails 2 tests; dropping the email
 * check from the layout predicate fails the drift test.
 */
final class CheckoutIdentityGuardTest extends TestCase
{
    private const THEME = __DIR__.'/../../../../themes/famedo';

    private const STORAGE_KEY = 'checkout_fields';

    /**
     * The gate as the BROWSER sees it. Deliberately the full call and not the
     * bare class name: the class name also appears in the comment above the
     * block, so a looser match would still pass if someone deleted the gate and
     * left the comment behind — the test would guard nothing.
     */
    private const GATE = "classList.contains('famedo-identity-complete')";

    private static function read(string $relative): string
    {
        $path = self::THEME.'/'.$relative;
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            self::fail("Cannot read {$relative} — the famedo theme moved? This guard is now blind.");
        }

        return $contents;
    }

    /**
     * The rule itself, as both Blade files express it. Kept as a closure rather
     * than imported, so the test states the EXPECTED semantics independently of
     * the implementation it is guarding.
     */
    private function identityIsComplete(?object $customer): bool
    {
        return $customer
            && filled($customer->first_name)
            && filled($customer->last_name)
            && filled($customer->email);
    }

    private function customer(?string $first, ?string $last, ?string $email): object
    {
        return (object) ['first_name' => $first, 'last_name' => $last, 'email' => $email];
    }

    // ---------------------------------------------------------------- semantics

    public function test_a_passwordless_account_is_not_complete(): void
    {
        // THE BUG'S CUSTOMER: created from an e-mail alone by the OTP login.
        // Must be false, or the cache holding what they just typed is deleted.
        $this->assertFalse($this->identityIsComplete(
            $this->customer(null, null, 'neu@example.de'),
        ));
    }

    public function test_a_half_filled_account_is_not_complete(): void
    {
        // Partial is still useless as a source of truth: the form would come
        // back missing whichever half the account lacks.
        $this->assertFalse($this->identityIsComplete($this->customer('Athina', null, 'a@example.de')));
        $this->assertFalse($this->identityIsComplete($this->customer(null, 'Georgiou', 'a@example.de')));
        $this->assertFalse($this->identityIsComplete($this->customer('Athina', 'Georgiou', null)));
    }

    public function test_whitespace_is_not_an_identity(): void
    {
        // filled() trims — a name of spaces must not unlock the wipe.
        $this->assertFalse($this->identityIsComplete($this->customer(' ', 'Georgiou', 'a@example.de')));
        $this->assertFalse($this->identityIsComplete($this->customer('Athina', "\t", 'a@example.de')));
    }

    public function test_a_guest_is_never_complete(): void
    {
        $this->assertFalse($this->identityIsComplete(null));
    }

    public function test_a_finished_account_is_complete(): void
    {
        // From order two onwards afterSaveOrder has persisted the identity, so
        // the account CAN replace the cache — dropping it is correct here, and
        // is what keeps the shared-device privacy intent alive.
        $this->assertTrue($this->identityIsComplete(
            $this->customer('Athina', 'Georgiou', 'athina@example.de'),
        ));
    }

    // ------------------------------------------------------------- no drift

    public function test_the_layout_and_the_checkout_form_use_the_SAME_predicate(): void
    {
        $layout = self::read('_layouts/default.blade.php');
        $fields = self::read('includes/checkout/tab-fields.blade.php');

        $normalise = static function (string $source, string $assignedTo): string {
            // Grab the @php(...) assignment, balanced to the closing paren.
            $start = strpos($source, '@php($'.$assignedTo.' = ');
            if ($start === false) {
                self::fail("No '{$assignedTo}' assignment left — the identity rule was renamed or removed.");
            }
            $open = strpos($source, '(', $start);
            $depth = 0;
            for ($i = $open; $i < strlen($source); $i++) {
                $depth += ($source[$i] === '(') ? 1 : (($source[$i] === ')') ? -1 : 0);
                if ($depth === 0) {
                    break;
                }
            }
            $expr = substr($source, $open + 1, $i - $open - 1);
            $expr = substr($expr, strpos($expr, '=') + 1);          // drop "$name ="
            $expr = preg_replace('/\$[A-Za-z_][A-Za-z0-9_]*/', '$C', $expr);  // unify var names
            return preg_replace('/\s+/', '', $expr);                 // whitespace-insensitive
        };

        $this->assertSame(
            $normalise($fields, 'identityLocked'),
            $normalise($layout, 'famedoIdentityComplete'),
            'The body class and the checkout form disagree about what a complete identity is. '
            .'Whichever one you just edited, edit the other: if the class is LOOSER than the form, '
            .'the wipe fires while the form still needs the cache — that is the 2026-08-09 bug.',
        );
    }

    // ------------------------------------------------- the wipe stays gated

    public function test_the_session_cache_is_dropped_ONLY_for_a_complete_account(): void
    {
        $js = self::read('assets/js/famedo.js');
        $key = self::STORAGE_KEY;

        $sessionWipe = "sessionStorage.removeItem('{$key}')";
        $this->assertSame(
            1,
            substr_count($js, $sessionWipe),
            "Expected exactly one place that drops the session cache; found another. "
            ."Every one of them needs the identity-complete gate.",
        );

        $gate = strpos($js, self::GATE);
        $wipe = strpos($js, $sessionWipe);

        $this->assertNotFalse($gate, 'The identity-complete gate is gone — the wipe is unconditional again, which IS the bug.');
        $this->assertLessThan(
            $wipe,
            $gate,
            'The session cache is dropped before the identity-complete check. A passwordless '
            .'account loses the name and phone it just typed.',
        );
    }

    public function test_the_legacy_local_cache_is_still_dropped_unconditionally(): void
    {
        // The opposite failure: over-correcting and gating BOTH storages would
        // leave July-9-era guest identity sitting in localStorage on a shared
        // device. Nothing has written there since 2026-07-28, so dropping it can
        // never destroy live data — it must stay unconditional.
        $js = self::read('assets/js/famedo.js');

        $localWipe = "localStorage.removeItem('".self::STORAGE_KEY."')";
        $this->assertStringContainsString($localWipe, $js, 'The legacy localStorage cleanup vanished.');

        $gate = strpos($js, self::GATE);
        $this->assertLessThan(
            $gate,
            strpos($js, $localWipe),
            'The legacy localStorage cleanup got moved behind the identity gate. It must run for '
            .'every logged-in visitor: it is pure hygiene and cannot destroy live data.',
        );
    }

    public function test_all_three_files_agree_on_the_storage_key(): void
    {
        // A silent rename here breaks save/restore without any error: the form
        // would just look empty again.
        $this->assertStringContainsString("'".self::STORAGE_KEY."'", self::read('assets/js/famedo.js'));
        $this->assertStringContainsString("'".self::STORAGE_KEY."'", self::read('includes/checkout/tab-fields.blade.php'));
    }

    // -------------------------------------------- the blur-to-submit race

    public function test_the_submit_flush_is_still_wired(): void
    {
        // Second defect of the same incident: wire:model.blur never commits when
        // the tap goes straight from the last input to "Bestellen", so the first
        // tap posts stale props. The flush must stay DEFERRED (third arg false)
        // or it costs a roundtrip per field on every checkout.
        $js = self::read('assets/js/famedo.js');

        $this->assertStringContainsString('data-checkout-control', $js);
        $this->assertMatchesRegularExpression(
            '/\.set\(\s*[\'"]fields\.[\'"]\s*\+\s*[\w.]+\s*,\s*[\w.]+\s*,\s*false\s*\)/',
            $js,
            'The submit flush is missing or no longer deferred. Non-deferred turns one '
            .'submit into one roundtrip per field.',
        );
    }
}
