<?php

declare(strict_types=1);

namespace Jamasa\Core\Livewire;

use Igniter\Cart\Facades\Cart;
use Igniter\Local\Facades\Location;
use Igniter\Main\Helpers\MainHelper;
use Igniter\User\Actions\LoginCustomer;
use Igniter\User\Actions\RegisterCustomer;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Jamasa\Core\Helpers\EmailNormalizer;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Passwordless email-code login (famedo /login page): email → 6-digit code →
 * signed in. Unified sign-in AND register — if the email has no account yet,
 * one is created on successful verification (random hashed password the
 * customer never sees; guest orders auto-link by email via CustomerObserver).
 *
 * Design notes:
 * - Codes are stored HASHED in cache (never the reset_code column — that slot
 *   is shared with password-reset/invites and would collide), 10-min TTL,
 *   single-use, capped verify attempts. 6 digits are brute-forceable without
 *   the attempt cap — never remove it.
 * - No account enumeration: requesting a code behaves identically whether the
 *   email exists or not; "returning" is only computed AFTER the inbox is proven.
 * - After login we mirror LoginCustomer's tail (Session::regenerate keeps the
 *   cart — data preserved, id rotated — and the igniter.user.login event feeds
 *   listeners like abandoned-cart), but we must NOT use Auth::attempt (password).
 */
class EmailCodeLogin extends Component
{
    // Server-owned state. #[Locked]: a client can't tamper the snapshot to jump
    // to 'success', flip 'returning', or point $continueUrl off-site. Only
    // $email stays writable (wire:model on the input) — it is re-validated on
    // every server entry point (onRequestCode + sendCode).
    #[Locked]
    public string $step = 'email'; // email | code | success

    public string $email = '';

    public string $emailError = '';

    public string $codeError = '';

    /** Set only after successful verification (no enumeration via snapshot). */
    #[Locked]
    public bool $returning = false;

    /** The verified customer's currently-active discount, for the success chip. */
    #[Locked]
    public ?string $successOffer = null;

    /** Where „Weiter" goes — resolved at verify time. ⚠️ Must be a PLAIN link:
     *  Session::regenerate() rotates the CSRF token, so any Livewire call after
     *  success would 419 ("page expired"). Navigation only. */
    #[Locked]
    public string $continueUrl = '';

    protected const CODE_TTL_SECONDS = 600; // 10 min

    protected const MAX_VERIFY_ATTEMPTS = 5;

    protected const MAX_SENDS_PER_EMAIL = 3; // per 10-min window

    protected const MAX_SENDS_PER_IP = 10; // per 10-min window

    public function render()
    {
        return view('jamasa::livewire.email-code-login', [
            'perkTeaser' => $this->perkTeaser(),
        ]);
    }

    public function onRequestCode()
    {
        $this->emailError = '';
        $email = mb_strtolower(trim($this->email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->emailError = 'Bitte eine gültige E-Mail-Adresse eingeben.';

            return;
        }

        $this->email = $email;
        $this->sendCode();
    }

    /** Client JS gates resend behind a 30s timer; the server limits are the truth. */
    public function onResend()
    {
        if ($this->step !== 'code') {
            return;
        }

        $this->sendCode();
    }

    protected function sendCode(): void
    {
        // Re-validate: $email is the one writable prop, and onResend() reaches
        // here without re-checking it (a tampered snapshot would otherwise hit
        // Mail::to with an arbitrary string).
        $this->email = mb_strtolower(trim($this->email));
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL) || strlen($this->email) > 96) {
            $message = 'Bitte eine gültige E-Mail-Adresse eingeben.';
            $this->step === 'code' ? $this->codeError = $message : $this->emailError = $message;

            return;
        }

        $emailKey = 'famedo-login-send:'.EmailNormalizer::normalize($this->email);
        $ipKey = 'famedo-login-send-ip:'.request()->ip();

        if (RateLimiter::tooManyAttempts($emailKey, self::MAX_SENDS_PER_EMAIL)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_SENDS_PER_IP)) {
            $message = 'Zu viele Versuche. Bitte in ein paar Minuten erneut probieren.';
            $this->step === 'code' ? $this->codeError = $message : $this->emailError = $message;

            return;
        }

        RateLimiter::hit($emailKey, self::CODE_TTL_SECONDS);
        RateLimiter::hit($ipKey, self::CODE_TTL_SECONDS);

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->codeKey(), Hash::make($code), self::CODE_TTL_SECONDS);
        Cache::forget($this->attemptsKey());

        // Restaurant as the From-NAME (the diner trusts the restaurant, not the
        // platform); the address stays the platform sender. Subject carries the
        // code so it's readable straight from the notification.
        $siteName = (string)(setting('sender_name') ?: setting('site_name'));
        Mail::send('jamasa::mail.login-code', [
            'code' => $code,
            'siteName' => $siteName,
        ], function($message) use ($siteName, $code): void {
            $message->to($this->email)
                ->subject($code.' ist dein Anmeldecode'.($siteName !== '' ? ' – '.$siteName : ''))
                ->from(config('mail.from.address'), $siteName ?: config('mail.from.name'));
        });

        $this->codeError = '';
        $this->step = 'code';
        $this->dispatch('auth-code-step');
    }

    public function onVerifyCode(string $code)
    {
        if ($this->step !== 'code') {
            return;
        }

        $this->codeError = '';
        $code = preg_replace('/\D/', '', $code);

        $hash = Cache::get($this->codeKey());
        if (strlen($code) !== 6 || !$hash) {
            $this->codeError = 'Der Code ist abgelaufen. Bitte einen neuen anfordern.';
            $this->dispatch('auth-code-invalid');

            return;
        }

        if (!Hash::check($code, $hash)) {
            // Cache::add creates the key WITH a TTL if absent (atomic) — required
            // before increment(): on database/memcached, increment on a missing
            // key returns false → the cap silently never trips (unlimited guesses).
            Cache::add($this->attemptsKey(), 0, self::CODE_TTL_SECONDS);
            $attempts = (int)Cache::increment($this->attemptsKey());

            if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
                Cache::forget($this->codeKey());
                $this->codeError = 'Zu oft falsch eingegeben. Bitte einen neuen Code anfordern.';
            } else {
                $this->codeError = 'Falscher Code. Bitte nochmal versuchen.';
            }

            $this->dispatch('auth-code-invalid');

            return;
        }

        // Code correct — single-use.
        Cache::forget($this->codeKey());
        Cache::forget($this->attemptsKey());

        try {
            $this->signIn();
        } catch (Throwable $throwable) {
            report($throwable);
            $this->codeError = 'Anmeldung gerade nicht möglich. Bitte später erneut versuchen.';
            $this->dispatch('auth-code-invalid');

            return;
        }

        $this->step = 'success';
    }

    protected function signIn(): void
    {
        // Identity keys on normalized_email (the beforeSave hook keeps it in step
        // with email) — gmail dot/+tag variants resolve to ONE account.
        $customer = Customer::query()
            ->where('normalized_email', EmailNormalizer::normalize($this->email))
            ->first();
        $this->returning = $customer !== null;

        if (!$customer) {
            // New account: store the LITERAL typed email (for display + the
            // guest-order backlink's exact-email match); the hook computes
            // normalized_email. Random password (auto-hashed via the model cast,
            // never used — login is by code). ⚠️ 'status' => 1 is REQUIRED:
            // Customer::register ignores the $activate flag; the
            // CustomerObserver::saved auto-activation only fires for enabled
            // customers. Names stay empty until first checkout / profile edit.
            $customer = resolve(RegisterCustomer::class)->handle([
                'first_name' => '',
                'last_name' => '',
                'email' => $this->email,
                'telephone' => '',
                'password' => Str::random(40),
                'newsletter' => 0,
                'status' => 1,
            ], true);
            $customer->refresh(); // observer completed activation post-save
        }

        throw_unless($customer->status && $customer->is_activated, new \RuntimeException(
            'famedo email-code login blocked for '.$this->email.' (disabled or not activated)',
        ));

        Auth::login($customer, true);
        Session::regenerate();
        Event::dispatch('igniter.user.login', [new LoginCustomer(['email' => $this->email], true)], true);

        // Cart truth lives under the LOCATION instance (CartMiddleware sets it
        // per web request; assert it here so the count below can't read the
        // empty 'default' instance in any context).
        if ($locationId = Location::getId()) {
            Cart::instance('location-'.$locationId);
        }

        // Resolve the return target NOW (regenerate kept session data, so the
        // intended URL captured by the /login page override is still there).
        // ⚠️ The fallback must carry the LOCATION SLUG — a bare '/local/menus'
        // resolves without location context (empty cart bubble, reset
        // fulfillment) — the suspected cart-lost-after-login mechanism.
        $fallback = MainHelper::pageUrl('local.menus',
            array_filter(['location' => Location::current()?->permalink_slug]));
        $this->continueUrl = (string)(session()->pull('url.intended') ?: $fallback);

        // Came here with a filled cart (e.g. via the cart teaser)? Have the menu
        // pop the cart sheet open on return so they finish the order with the
        // discount visible (the menus page reacts to ?welcome=1 + non-empty cart).
        if (Cart::content()->count() > 0 && !str_contains($this->continueUrl, 'welcome=1')) {
            $this->continueUrl .= (str_contains($this->continueUrl, '?') ? '&' : '?').'welcome=1';
        }

        // Ground truth for the cart-across-login hunt — one line per login.
        \Illuminate\Support\Facades\Log::notice(sprintf(
            'famedo login: %s customer #%d | cart[%s]=%d | continue=%s',
            $this->returning ? 'returning' : 'new',
            $customer->getKey(),
            Cart::currentInstance(),
            Cart::content()->count(),
            $this->continueUrl,
        ));

        if (class_exists(\Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::class)
            && ($offer = \Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::activeOfferFor($customer))) {
            $this->successOffer = $offer['headline'];
        }
    }

    public function onBack()
    {
        $this->step = 'email';
        $this->codeError = '';
    }

    /** Perk line under the email field — real benefits only, from live settings. */
    protected function perkTeaser(): ?array
    {
        if (!class_exists(\Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::class)) {
            return null;
        }

        return \Iamnothardcoded\SignupDiscounts\Classes\DiscountManager::welcomeTeaser();
    }

    /** Keys on the NORMALIZED address so gmail-variant requests share one
     *  code + one rate-limit bucket (blocks +tag resend/farming games). */
    protected function codeKey(): string
    {
        return 'famedo-login-code:'.EmailNormalizer::normalize($this->email);
    }

    protected function attemptsKey(): string
    {
        return 'famedo-login-attempts:'.EmailNormalizer::normalize($this->email);
    }
}
