<?php

declare(strict_types=1);

namespace Jamasa\Core\Livewire\Features;

use Igniter\Local\Facades\Location;
use Igniter\Orange\Livewire\Checkout;
use Igniter\User\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Checkout-failure telemetry (Global TODO #1 (e), built 2026-08-26).
 *
 * Livewire fires trigger('exception', $component, $e, $stopPropagation)
 * (Wrapped::__call) whenever a component method throws — including
 * Checkout::onValidate/onConfirm. We log ONE production.NOTICE line per
 * checkout ValidationException:
 *
 *   famedo checkout rejected: fields.termsAgreed=Accepted | fields.telephone=Required | guest | delivery
 *
 * ⚠️ WHY \Livewire\on('exception') AND NOT Livewire::componentHook():
 * ComponentHookRegistry::boot() bakes one mount/hydrate closure PER HOOK that
 * is registered at the moment Livewire's provider boots — TI extensions boot
 * AFTER that, so a componentHook() registered here is silently never
 * initialized (verified 2026-08-26: zero log lines, no error). The EventBus
 * (`on()`) is checked at fire time instead, so attaching in Extension::boot
 * works. Orange's own SupportFlashMessages can use componentHook() only
 * because its ServiceProvider is a package provider.
 *
 * Contract (same as the geocoder rescue log + src capture — the structured
 * one-line convention the future ANALYTICS-HOME counts):
 *  - field names + failing RULE names only, NEVER user-entered values (the
 *    2026-08-08 audit lesson: we must see WHY checkouts die without logging
 *    the person). withMessages()-style exceptions (jamasa listeners, vendor
 *    payment throws) have no failed() rules — those log `<key>=failed`.
 *  - guest|customer + order type give the segmentation the El Greco forensics
 *    lacked (the blocker only hit fresh passwordless accounts).
 *
 * Known blind spot, accepted: Checkout::checkCheckoutSecurity() catches its
 * own exceptions internally (flash only, nothing escapes to this hook) —
 * cart-level states (empty cart, closed, below minimum) are not field
 * failures and stay unlogged here.
 *
 * Rules: NEVER throw (telemetry must not break checkout), NEVER call the
 * $stopPropagation we're handed (we only observe — orange's SupportValidation
 * owns the error-bag handling, and its stopPropagation only suppresses the
 * rethrow, later listeners still run).
 */
class LogCheckoutRejections
{
    public static function handle(mixed $target, mixed $e): void
    {
        try {
            if (!$target instanceof Checkout || !$e instanceof ValidationException) {
                return;
            }

            $parts = [];

            $failed = $e->validator?->failed() ?? [];
            foreach ($failed as $field => $rules) {
                $parts[] = $field.'='.implode(',', array_keys($rules));
            }

            if ($parts === []) {
                $keys = array_keys($e->errors());
                foreach ($keys as $field) {
                    // The jamasa checkout listener double-keys each message
                    // (fields.* + bare twin) for the theme transition — count
                    // the failure once, under the surviving fields.* key.
                    if (in_array('fields.'.$field, $keys, true)) {
                        continue;
                    }
                    $parts[] = $field.'=failed';
                }
            }

            if ($parts === []) {
                return;
            }

            $parts[] = Auth::customer() ? 'customer' : 'guest';
            $parts[] = (string)(Location::orderType() ?: 'unknown');

            Log::notice('famedo checkout rejected: '.implode(' | ', $parts));
        } catch (\Throwable $t) {
            Log::warning('famedo checkout-rejection logging failed: '.$t->getMessage());
        }
    }
}
