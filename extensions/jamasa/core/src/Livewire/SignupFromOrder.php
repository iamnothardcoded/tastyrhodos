<?php

declare(strict_types=1);

namespace Jamasa\Core\Livewire;

use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\User\Actions\RegisterCustomer;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Customer;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Inline "create an account from your order" prompt on the order-success page.
 *
 * A guest who just checked out already gave us their name, email, phone and
 * address on the order — and accounts are PASSWORDLESS (email-code login), so
 * opening one is a single tap: no password, no input. We register them with
 * the order's own details + a random hashed password nobody ever uses (via
 * the core RegisterCustomer action, which auto-logs them in and back-links
 * this guest order by email). Inbox ownership is proven at their first
 * email-code login; the back-linked order does NOT consume the welcome
 * eligibility (DiscountManager counts orders since account creation), so
 * „Nächstes Mal sparen" is a true promise.
 *
 * Shows ONLY for: a guest (not logged in), on a real guest order (no
 * customer_id), whose email has no account yet, and only when a welcome
 * discount is actually on (there's a reason to register). The email comes
 * from the order server-side — nobody can register an address that isn't
 * on their own just-placed order.
 */
class SignupFromOrder extends Component
{
    /**
     * ⚠️ #[Locked]: without it, a public Livewire prop is client-writable — an
     * attacker could `$wire.set('hash', <someone else's order hash>)` on their
     * OWN success page and `register()` would create + auto-login an account
     * bound to that email/address (account takeover). Locked pins it to the hash
     * set at mount() from the URL the customer legitimately loaded. Possessing a
     * success-page hash IS the capability to view that order — the same trust
     * model as the vendor OrderPreview on this page; Locked only removes the
     * inject-a-foreign-hash escalation.
     */
    #[Locked]
    public ?string $hash = null;

    public bool $registered = false;

    public function mount(?string $hash = null): void
    {
        $this->hash = $hash ?? request()->route()?->parameter('hash');
    }

    protected function order(): ?Order
    {
        return $this->hash ? resolve(OrderManager::class)->getOrderByHash($this->hash) : null;
    }

    /** True if an account already exists for this email or any gmail dot/+tag
     *  variant of it (keys on the canonical normalized_email). */
    protected function accountExistsFor(string $email): bool
    {
        return Customer::query()
            ->where('normalized_email', \Jamasa\Core\Helpers\EmailNormalizer::normalize($email))
            ->exists();
    }

    /**
     * @return ?array{teaser: array, email: string, name: string}
     */
    public function offer(): ?array
    {
        if (Auth::isLogged() || !class_exists(\Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::class)) {
            return null;
        }

        if (!$teaser = \Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::successTeaser()) {
            return null;
        }

        $order = $this->order();
        if (!$order || $order->customer_id || !filled($order->email)) {
            return null;
        }

        // Canonical exists-check: no duplicate account for a gmail dot/+tag
        // variant. Creation keeps the ORDER's literal email (the observer's
        // guest-order backlink matches by exact email); the Customer beforeSave
        // hook derives normalized_email.
        if ($this->accountExistsFor($order->email)) {
            return null;
        }

        return ['teaser' => $teaser, 'email' => $order->email, 'name' => (string) $order->first_name];
    }

    public function register(): void
    {
        $order = $this->order();

        // Re-assert every guard server-side (never trust the rendered state).
        if (Auth::isLogged() || !$order || $order->customer_id || !filled($order->email)
            || $this->accountExistsFor((string)$order->email)) {
            return;
        }

        // ⚠️ 'status' => 1 is required (mirrors the orange RegisterForm):
        // CustomerObserver::saved only auto-activates ENABLED customers —
        // without it the account is created disabled and can never log in.
        // Password = random + hashed, never used: login is by email code.
        resolve(RegisterCustomer::class)->handle([
            'first_name' => (string) $order->first_name,
            'last_name' => (string) $order->last_name,
            'email' => $order->email,
            'telephone' => (string) $order->telephone,
            'password' => Str::random(40),
            'newsletter' => 0,
            'status' => 1,
        ]);

        // Auth::isLogged() is true only when the group auto-activates (no approval).
        $this->registered = Auth::isLogged();
    }

    public function render()
    {
        $done = null;
        if ($this->registered && class_exists(\Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::class)) {
            $done = \Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::successTeaser()['done'] ?? null;
        }

        return view('jamasa::livewire.signup-from-order', [
            'offer' => $this->registered ? null : $this->offer(),
            'doneText' => $done,
        ]);
    }
}
