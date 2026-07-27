<?php

declare(strict_types=1);

namespace Jamasa\Core\Livewire;

use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\User\Actions\RegisterCustomer;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Customer;
use Livewire\Component;

/**
 * Inline "create an account from your order" prompt on the order-success page.
 *
 * A guest who just checked out already gave us their name, email, phone and
 * address on the order — so the only thing needed to open an account is a
 * password. On submit we register them with the order's own details (via the
 * core RegisterCustomer action, which auto-logs them in and back-links this
 * guest order by email), so next time the signup discount applies.
 *
 * Shows ONLY for: a guest (not logged in), on a real guest order (no
 * customer_id), whose email has no account yet, and only when a welcome
 * discount is actually on (there's a reason to register). The password is the
 * only user input — the email comes from the order server-side, so nobody can
 * register an address that isn't theirs.
 */
class SignupFromOrder extends Component
{
    public ?string $hash = null;

    public string $password = '';

    public bool $registered = false;

    public function mount(?string $hash = null): void
    {
        $this->hash = $hash ?? request()->route()?->parameter('hash');
    }

    protected function order(): ?Order
    {
        return $this->hash ? resolve(OrderManager::class)->getOrderByHash($this->hash) : null;
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

        // Variant-aware exists-check (gmail dots/+tags): if the person already
        // has an account under the canonical form, don't offer a duplicate.
        // Creation below keeps the ORDER's email as-is so the observer's
        // guest-order backlink (exact email match) links THIS order.
        $emails = array_unique([$order->email, \Jamasa\Core\Helpers\EmailNormalizer::normalize($order->email)]);
        if (Customer::query()->whereIn('email', $emails)->exists()) {
            return null;
        }

        return ['teaser' => $teaser, 'email' => $order->email, 'name' => (string) $order->first_name];
    }

    public function register(): void
    {
        $order = $this->order();

        // Re-assert every guard server-side (never trust the rendered state).
        $emails = $order ? array_unique([$order->email, \Jamasa\Core\Helpers\EmailNormalizer::normalize((string)$order->email)]) : [];
        if (Auth::isLogged() || !$order || $order->customer_id || !filled($order->email)
            || Customer::query()->whereIn('email', $emails)->exists()) {
            return;
        }

        $this->validate(
            ['password' => ['required', 'string', 'min:6', 'max:32']],
            [],
            ['password' => lang('igniter.user::default.login.label_password')],
        );

        // ⚠️ 'status' => 1 is required (mirrors the orange RegisterForm):
        // CustomerObserver::saved only auto-activates ENABLED customers —
        // without it the account is created disabled and can never log in.
        resolve(RegisterCustomer::class)->handle([
            'first_name' => (string) $order->first_name,
            'last_name' => (string) $order->last_name,
            'email' => $order->email,
            'telephone' => (string) $order->telephone,
            'password' => $this->password,
            'newsletter' => 0,
            'status' => 1,
        ]);

        // Auth::isLogged() is true only when the group auto-activates (no approval).
        $this->registered = Auth::isLogged();
        $this->password = '';
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
