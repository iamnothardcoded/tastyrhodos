<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Controllers;

use Igniter\Cart\Classes\OrderManager;
use Igniter\User\Actions\RegisterCustomer;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Jamasa\Core\Helpers\EmailNormalizer;
use Jamasa\Core\Helpers\ReturnUrl;

/**
 * Full-page POST target for the guest success-page „Konto anlegen" card.
 *
 * Deliberately NOT a Livewire action: RegisterCustomer logs the customer in,
 * which rotates the CSRF token (LoginCustomer → Session::regenerate). A
 * wire:click left the success page's OTHER live components — the order-preview's
 * wire:poll.15s — on the stale token, and the poll then fired with it →
 * „Diese Seite ist abgelaufen" (419). A plain POST that ends in a full-page
 * redirect reloads EVERY component with the fresh token (the same pattern the
 * email-code login uses after regenerate). No in-page Livewire state survives to
 * race the rotation.
 *
 * Every guard is re-asserted server-side; the email comes from the ORDER (by
 * hash), never the client — nobody can register an address that isn't on their
 * own just-placed order. Possessing the success-page hash IS the capability to
 * view that order (same trust model as the order-preview on this page).
 */
class SignupFromOrderController
{
    public function __invoke(): RedirectResponse
    {
        $back = (string) request()->header('referer');
        $target = ($back && ReturnUrl::isLocal($back)) ? $back : page_url('local.menus');

        $hash = (string) request('hash');
        $order = $hash ? resolve(OrderManager::class)->getOrderByHash($hash) : null;

        // Re-assert every guard (never trust the form): guest, real guest order,
        // has an email, and no account (nor a gmail dot/+tag variant) exists yet.
        if (Auth::isLogged() || ! $order || $order->customer_id || ! filled($order->email)
            || Customer::query()
                ->where('normalized_email', EmailNormalizer::normalize((string) $order->email))
                ->exists()) {
            return redirect()->to($target);
        }

        // status=1 required (mirrors the orange RegisterForm): the observer only
        // auto-activates ENABLED customers. Password = random + hashed, never
        // used — login is by email code. Auto-logs in when the group needs no
        // approval; back-links this guest order by email.
        resolve(RegisterCustomer::class)->handle([
            'first_name' => (string) $order->first_name,
            'last_name' => (string) $order->last_name,
            'email' => $order->email,
            'telephone' => (string) $order->telephone,
            'password' => Str::random(40),
            'newsletter' => 0,
            'status' => 1,
        ]);

        if (Auth::isLogged()) {
            $done = class_exists(\Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::class)
                ? (\Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::successTeaser()['done'] ?? null)
                : null;
            flash()->success($done ?: lang('igniter.user::default.login.alert_account_created'));
        }

        return redirect()->to($target);
    }
}
