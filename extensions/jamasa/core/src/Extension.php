<?php

declare(strict_types=1);

namespace Jamasa\Core;

use Igniter\Local\Events\WorkingScheduleCreatedEvent;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\System\Classes\BaseExtension;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Facades\Route;
use Jamasa\Core\Console\CreateOwner;
use Jamasa\Core\Console\LegalFill;
use Jamasa\Core\Console\MailReport;
use Jamasa\Core\Console\SyncSettings;
use Jamasa\Core\Helpers\PickupCode;
use Jamasa\Core\Listeners\AutoAcceptOrder;
use Jamasa\Core\Listeners\PauseWorkingSchedule;
use Jamasa\Core\Livewire\AccountSettings;
use Jamasa\Core\Livewire\EmailCodeLogin;
use Jamasa\Core\Livewire\SignupFromOrder;
use Livewire\Livewire;
use Override;

/**
 * Jamasa Core Extension
 *
 * Business logic for the famedo platform: ordering pause machinery,
 * pickup codes, geocoder fixes, and the operator API.
 *
 * The storefront frontend (view/CSS/JS overrides, fonts) lives in the
 * famedo theme (themes/famedo). Only the igniter-cart mail/invoice
 * overrides stay here — a local theme cannot override non-parent view
 * namespaces (no prependNamespace hook, no service provider).
 *
 * Language overrides remain in /app/lang/vendor/ (Laravel's native mechanism).
 */
class Extension extends BaseExtension
{
    /**
     * Override the Mollie gateway registration with our fixed subclass (see
     * Payments\Mollie). PaymentGateways::listGateways keys by CODE with
     * last-writer-wins, and jamasa registers after igniter.payregister, so
     * fresh installs seed payments.class_name with the fixed class; existing
     * rows need the one-time class_name UPDATE (runbook §3.2).
     */
    public function registerPaymentGateways(): array
    {
        return [
            \Jamasa\Core\Payments\Mollie::class => [
                'code' => 'mollie',
                'name' => 'lang:igniter.payregister::default.mollie.text_payment_title',
                'description' => 'lang:igniter.payregister::default.mollie.text_payment_desc',
            ],
        ];
    }

    #[Override]
    public function register(): void
    {
        // famedo:sync-settings — idempotent per-tenant DB-settings convergence
        // (replaces the manual post-image-bump one-liners; see SyncSettings).
        $this->registerConsoleCommand('famedo.sync-settings', SyncSettings::class);
        // Impressum/Datenschutz from owner data — see LegalFill's header for
        // why tenant legal data must never come from a scraped platform.
        $this->registerConsoleCommand('famedo.legal-fill', LegalFill::class);

        // famedo:create-owner — provision a locked-down owner-console account
        // per tenant (their e-mail = login username). See CreateOwner.
        $this->registerConsoleCommand('famedo.create-owner', CreateOwner::class);

        // famedo:mail-report — "is our mail actually arriving?", the question
        // nobody could answer during the 2026-08 sender outage. Exits non-zero on
        // alarm so a dead-man's-switch can hang off it. See MailReport.
        $this->registerConsoleCommand('famedo.mail-report', MailReport::class);
    }

    #[Override]
    public function boot(): void
    {
        // Override views from the igniter.cart extension (mail templates + invoice).
        // Place overrides in: resources/views/igniter-cart/
        // MUST be prependNamespace on the DOTTED namespace: TI renders
        // 'igniter.cart::mail.order' etc.; loadViewsFrom under a parallel
        // 'igniter-cart' namespace is consulted by nothing (bug until 2026-07-17
        // — the overrides silently never applied), and appending to
        // 'igniter.cart' would lose to the vendor path registered first.
        $this->app['view']->prependNamespace('igniter.cart', __DIR__.'/../resources/views/igniter-cart');

        // Jamasa's own views (the `jamasa::` namespace) + Livewire components.
        $this->app['view']->addNamespace('jamasa', __DIR__.'/../resources/views');
        // Inline "create an account from your order" prompt on the success page
        // (guest-only; the storefront success page includes it). See SignupFromOrder.
        Livewire::component('jamasa::signup-from-order', SignupFromOrder::class);
        // Passwordless email-code login — mounted by the famedo /login page
        // override in place of orange's password login. See EmailCodeLogin.
        Livewire::component('jamasa::email-code-login', EmailCodeLogin::class);
        // Profile settings with immutable email + return-to-checkout — mounted
        // by the famedo profile page instead of the vendor component.
        Livewire::component('jamasa::account-settings', AccountSettings::class);

        // Keep customers.normalized_email in step with email on EVERY creation
        // path (email-code login, success-page card, admin, register page). The
        // normalized form is the canonical account identity — gmail dot/+tag
        // variants fold to one account (welcome-discount farming shield). See
        // the add_normalized_email migration + EmailNormalizer.
        \Igniter\User\Models\Customer::extend(function($model): void {
            $model->bindEvent('model.beforeSave', function() use ($model): void {
                $model->normalized_email = filled($model->email)
                    ? \Jamasa\Core\Helpers\EmailNormalizer::normalize((string)$model->email)
                    : null;
            });
        });

        // Register Blade directive for pickup code
        // Usage in views: @pickupCode($order->hash)
        Blade::directive('pickupCode', function (string $expression): string {
            return "<?php echo \Jamasa\Core\Helpers\PickupCode::fromHash({$expression}); ?>";
        });

        // Locale for customer mail templates: mail renders in the locale of the
        // TRIGGERING context (sync queue: an admin status change renders under
        // the ADMIN locale — English mail to German customers). Templates pass
        // this as @lang(..., $mail_locale) instead. Injected via View::share
        // because TI's mail TemplateSandbox forbids static/method calls in
        // templates, and ViewHelper::getGlobalVars() forwards shared scalars
        // into every mail render. rescue(): boots before install have no DB.
        \Illuminate\Support\Facades\View::share(
            'mail_locale',
            rescue(fn() => \Igniter\System\Models\Language::getDefault()?->code, null, false),
        );

        // German market defaults for geocoding (vendor config ships GB region;
        // the theme passes countrycodes per-query, but CLI/API paths fall back
        // to this config — keep it correct for famedo tenants).
        config([
            'igniter-geocoder.providers.nominatim.region' => 'DE',
            'igniter-geocoder.providers.nominatim.locale' => 'de',
            // famedo is Nominatim-only (DSGVO, no Google key). The shipped
            // default is "chain" (google → nominatim) and Geocoder::driver()
            // reads THIS config, not the default_geocoder DB setting — with no
            // API key, Google throws 403 inside the chain and (ChainProvider
            // has no per-provider try/catch) kills the checkout address
            // autocomplete before Nominatim is ever asked.
            'igniter-geocoder.default' => 'nominatim',

            // Bound the SMTP conversation. The login-code mail (EmailCodeLogin)
            // sends DELIBERATELY sync via Mail::send so the UI can report
            // sent-vs-failed honestly — which means the customer's request
            // waits on the relay. Without this, PHP's default socket timeout
            // lets a hung relay freeze the login screen for up to ~60s; with
            // it, worst case = 10s + a clean error + retry. Queued order
            // mails are unaffected (worker-side, customer never waits).
            'mail.mailers.smtp.timeout' => 10,

            // De-brand: suppress the `X-Powered-By: TastyIgniter` response header
            // (core PoweredBy middleware, default on). Keeps the platform's
            // framework out of a competitor's first `curl -I`. Cosmetic — nothing
            // depends on the header. PHP's own expose_php is Off in the image so
            // no `X-Powered-By: PHP/x` replaces it.
            'igniter-system.sendPoweredByHeader' => false,
        ]);

        // Self-hosted geocoders (shared box services, infra/shared-services).
        // NOMINATIM_URL / PHOTON_URL are BASE urls (scheme+host+port, no path)
        // and must be real process env (compose `environment:`), not .env keys —
        // once config is cached Laravel never loads .env, so env() here would
        // silently return null for .env-only vars. Unset ⇒ public OSM servers.
        // The distance endpoint stays untouched: that's OSRM routing
        // (routing.openstreetmap.de), not Nominatim.
        if ($nominatimUrl = rtrim((string)env('NOMINATIM_URL', ''), '/')) {
            config([
                'igniter-geocoder.providers.nominatim.endpoints.geocode' => $nominatimUrl.'/search?q=%s&format=json&addressdetails=1&limit=%d',
                'igniter-geocoder.providers.nominatim.endpoints.reverse' => $nominatimUrl.'/reverse?format=json&lat=%F&lon=%F&addressdetails=1&zoom=%d',
                'igniter-geocoder.providers.nominatim.endpoints.places' => $nominatimUrl.'/',
            ]);
        }
        if ($photonUrl = rtrim((string)env('PHOTON_URL', ''), '/')) {
            config(['igniter-geocoder.providers.nominatim.endpoints.photon' => $photonUrl.'/api/']);
        }

        // Fixed Nominatim provider (empty-title suggestions for plain addresses
        // break delivery-address selection — see Geolite\NominatimProvider).
        // Overrides the built-in creator; the chain driver resolves through it too.
        \Igniter\Flame\Geolite\Facades\Geocoder::extend('nominatim', function($container) {
            return new \Jamasa\Core\Geolite\NominatimProvider(
                $container['geocoder.client'],
                $container['config']['igniter-geocoder.providers.nominatim'] ?? [],
            );
        });

        // Fixed PayPal client (upstream picks the API host from APP_ENV instead
        // of the gateway's sandbox setting — see Classes\PayPalClient). Rebinding
        // the singleton here wins because extension boot runs after payregister's
        // $singletons registration; PaypalExpress resolves it from the container.
        $this->app->singleton(
            \Igniter\PayRegister\Classes\PayPalClient::class,
            \Jamasa\Core\Classes\PayPalClient::class,
        );

        // Fixed OrderManager (upstream getCartTotals persists STALE condition
        // values — a Liefern→Abholen switch before ordering silently charges
        // the pickup customer the delivery fee; see Classes\FixedOrderManager).
        $this->app->singleton(
            \Igniter\Cart\Classes\OrderManager::class,
            \Jamasa\Core\Classes\FixedOrderManager::class,
        );

        // When ordering is paused (jamasa_ordering_state.paused), force the
        // location's working schedule closed so the storefront shows the native
        // "CLOSED" state (browsable menu, checkout gated) instead of disabling
        // order types (redirect loop) or the location (500). See PauseWorkingSchedule.
        Event::listen(WorkingScheduleCreatedEvent::class, [PauseWorkingSchedule::class, 'handle']);

        // Every outgoing mail must have a REPLY PATH. Since 2026-08-07 tenants send
        // as bestellung@<their own domain>, and those domains have no MX — so a
        // customer hitting "Antworten" on an order mail (TI sets no Reply-To at all)
        // bounced. Fill it in, but never override one a mailable set deliberately:
        // the login-code mail points replies at the PLATFORM, because "the code
        // doesn't arrive" is our problem, not something the kitchen can fix.
        Event::listen(MessageSending::class, function(MessageSending $event): void {
            $message = $event->message;
            if ($message->getReplyTo() !== []) {
                return;
            }

            // ⚠️ location_email is a COLUMN on locations, not a setting — and mail
            // is sent by the QUEUE WORKER, where Location::current() is null. So the
            // model fallback is the path that actually runs in production.
            $address = (string)(optional(Location::current())->location_email
                ?: optional(LocationModel::query()->orderBy('location_id')->first())->location_email);

            if ($address === '' || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return;                     // no real address known — leave it alone
            }

            $message->replyTo(new Address($address, (string)setting('site_name')));
        });

        // MAIL DELIVERY VISIBILITY — record what we handed to the ESP.
        //
        // ⚠️ This exists because "the queue drained and failed_jobs is 0" is NOT
        // evidence that a mail arrived: Brevo answers 250 OK at SMTP and rejects
        // internally afterwards. From 2026-08-02 to 2026-08-09 every customer
        // order-confirmation was rejected that way and NOTHING here knew.
        //
        // MessageSent (not MessageSending) on purpose: the Message-ID is minted by
        // the transport at send time, and it is the ONLY join key Brevo echoes back
        // in its webhook payload — the payload carries no sender at all.
        // Failure telemetry then arrives via MailEventsController.
        //
        // ⚠️ Wrapped whole in catch(Throwable): telemetry must never be able to
        // break an actual send. A lost row is an inconvenience; a mail that did not
        // go out because bookkeeping threw is an outage.
        Event::listen(MessageSent::class, function(MessageSent $event): void {
            try {
                $messageId = trim((string)$event->sent->getMessageId(), " \t\n\r\0\x0B<>");
                if ($messageId === '') {
                    return;
                }

                $email = $event->message;
                $to = $email->getTo()[0] ?? null;
                $recipient = $to ? mb_strtolower(trim($to->getAddress())) : '';
                $from = $email->getFrom()[0] ?? null;

                DB::table('mail_messages')->insertOrIgnore([
                    // Template code when we know it (set by the mailable), else the
                    // subject is a good-enough discriminator for an alarm.
                    'mail_type' => mb_substr((string)($event->data['__famedo_mail_type'] ?? $email->getSubject() ?? ''), 0, 64),
                    'message_id' => $messageId,
                    'from_address' => $from ? mb_substr($from->getAddress(), 0, 191) : null,
                    // ⚠️ DSGVO: hash, never the address itself — it already lives
                    // lawfully in orders/customers. Domain kept because provider-
                    // shaped failures (gmx/web.de) are the operational signal.
                    'recipient_hash' => hash('sha256', $recipient),
                    'recipient_domain' => mb_substr((string)mb_strrchr($recipient, '@'), 1, 128) ?: null,
                    'status' => null,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('famedo mail-log: could not record outgoing mail: '.$e->getMessage());
            }
        });

        // Same-day preorder, constraint 1/2 (see Helpers\Preorder): future_orders
        // needs days>=1 to bypass core's closed-now gate, which would also offer
        // TOMORROW's slots — platform semantic is strictly same-day, so invalidate
        // every non-today slot at generation time (kills the „Morgen" date bubble
        // at the source). Reservation schedules keep their own rules. Deliberate
        // v1 tradeoff: past-midnight slots of tonight's window (open until 01:00)
        // are lost — no current tenant opens past midnight. Return null (not true)
        // when we don't object, so other listeners still get asked.
        Event::listen('igniter.workingSchedule.timeslotValid', function ($schedule, $timeslot): ?bool {
            if (!in_array($schedule->getType(), [
                \Igniter\Local\Models\Location::DELIVERY,
                \Igniter\Local\Models\Location::COLLECTION,
            ], true)) {
                return null;
            }

            return make_carbon($timeslot)->isToday() ? null : false;
        });

        // Same-day preorder, constraint 2/2: server-side checkout guard. The
        // timeslot filter above shapes the UI, but checkOrderTime() itself
        // (CartManager, FulfillmentModal, CartBox) accepts anything inside the
        // future_orders day window — a crafted request could still book tomorrow.
        // Reject at the money moment instead.
        Event::listen('igniter.orange.validateCheckout', function ($data = null, $order = null): void {
            if (!\Igniter\Local\Facades\Location::orderDateTime()->isToday()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order_time' => lang('jamasa.core::default.preorder.same_day_only'),
                ]);
            }
        });

        // Auto-accept: the instant an order is paid (admin.order.paymentProcessed),
        // promote it 1 -> 10 ("Angenommen") in auto mode so the printer prints it.
        // In manual mode it stays at 1 for the owner to accept in the app. This is
        // the ONE home of "acceptance" — the printer stays dumb. See AutoAcceptOrder.
        Event::listen('admin.order.paymentProcessed', [AutoAcceptOrder::class, 'handle']);

        // Mollie hosted-checkout / bank-statement / report line: the pickup code
        // IS the customer-facing order reference — for BOTH order types. It
        // obfuscates sequential internal ids (they'd reveal the restaurant's
        // real order volume) and matches the code shown on success page,
        // customer order list, invoice and kitchen receipt → the restaurant can
        // reconcile Mollie report lines against those artifacts. ONLY
        // $fields['description'] is touched — metadata.order_id (unique,
        // internal) stays untouched: webhook/return verification + dashboard
        // tiebreak for code collisions.
        // fireSystemEvent prepends the gateway instance ($this) to the args.
        Event::listen('payregister.mollie.extendFields', function ($gateway, &$fields, $order, $data): void {
            $fields['description'] = sprintf('Bestellung #%s', PickupCode::fromHash($order->hash));
        });

        // PayPal parity: upstream sends NO description at all — add the same
        // obfuscated reference to the purchase unit (shows on the PayPal
        // approval page / transaction details). reference_id (hash) and
        // custom_id (internal order id) stay untouched.
        Event::listen('payregister.paypalexpress.extendFields', function ($gateway, &$fields, $order, $data): void {
            $fields['purchase_units'][0]['description'] = sprintf('Bestellung #%s', PickupCode::fromHash($order->hash));
        });

        // Customer mails: expose the pickup code as a plain mail-data variable.
        // The @pickupCode blade directive CANNOT be used in mail templates (the
        // core TemplateSandbox truncates on non-whitelisted directives), but a
        // data variable is always safe — jamasa's customer templates
        // (igniter-cart/mail/order + order_update) consume it instead of
        // order_number. Admin alert mails keep the internal id.
        \Igniter\Cart\Models\Order::extend(function (\Igniter\Cart\Models\Order $model): void {
            $model->bindEvent('model.mailGetData', function (array &$data) use ($model): void {
                $data['pickup_code'] = PickupCode::fromHash($model->hash);
            });
        });

        // German address order on every save: checkout-created rows arrive as
        // "2 Cottenburgstraße" (vendor prepareDeliveryAddress, US-style) —
        // normalize so admin/OM/receipts/mails show one consistent format.
        // Existing rows: jamasa migration 2026_07_24_000001 (same helper).
        \Igniter\User\Models\Address::extend(function (\Igniter\User\Models\Address $model): void {
            $model->bindEvent('model.beforeSave', function () use ($model): void {
                $model->address_1 = \Jamasa\Core\Helpers\AddressFormat::germanize($model->address_1);
            });
        });

        // Geocoder-blind rescue orders: stamp a warning into the order comment so
        // the kitchen receipt (prints comments inverted) and the Order Manager
        // show it — the restaurant verifies BEFORE the driver leaves.
        // ⚠️ Deliberately at PLACEMENT (afterSaveOrder), NOT model.beforeCreate:
        // TI creates a DRAFT order row when checkout is first opened, and a
        // create-time stamp reads whatever the session held at page load — a
        // rescue selected then abandoned earlier leaks into the draft's comment
        // and shows up PREFILLED in the checkout note textarea (bit us: order
        // #247, 2026-07-24). At afterSaveOrder, validateCheckout has just synced
        // the session position to THIS order's final address, so marker ↔ order
        // can't desync; the un-stamp branch heals any leftover from earlier
        // attempts. Session position is null for API/POS-created orders.
        // ONE afterSaveOrder listener does both order mutations then a SINGLE
        // ---------- Delivery orders MUST carry an address (2026-08-03) ----------
        // Backstop for a hole found on dev: three delivery orders (666/667/668)
        // were accepted with address_id=NULL and no address data whatsoever.
        // Upstream's own check lives in Checkout::validateCheckout() as a
        // $validator->after() callback — it demonstrably did not block, even
        // though OrderManager::validateDeliveryAddress() rejects that exact
        // (empty) input when called directly, and the withValidator/after/rescue
        // mechanism blocks correctly when exercised in isolation. Root cause
        // therefore still UNKNOWN — this guard is deliberately independent of it.
        //
        // Placement: 'igniter.orange.validateCheckout' fires at the END of
        // validateCheckout, i.e. AFTER upstream validation passed but BEFORE
        // onConfirm calls saveOrder() — so throwing here blocks the order and
        // nothing is persisted.
        //
        // Gate on Location::orderType(), NOT $order->order_type: orderType() is
        // exactly what applyRequiredAttributes() writes onto the order at save
        // time, so "will this be SAVED as delivery?" cannot desync from what we
        // check. (A stale/absent session key defaults to delivery, which fails
        // safe — it can only ever ask for an address, never skip asking.)
        // The 'delivery_address' key matches the theme's existing error surface.
        Event::listen('igniter.orange.validateCheckout', function($data = null, $order = null): void {
            if (\Igniter\Local\Facades\Location::orderType() !== \Igniter\Local\Models\Location::DELIVERY) {
                return;
            }

            $fields = (array)$data;
            if (filled($fields['address_1'] ?? null) || filled($fields['address_id'] ?? null)) {
                return;
            }

            \Illuminate\Support\Facades\Log::warning('famedo: blocked a delivery order with no address', [
                'order_id' => $order?->order_id,
                'order_type_in_memory' => $order?->order_type,
                'isDeliveryType' => $order?->isDeliveryType(),
                'session_orderType' => \Igniter\Local\Facades\Location::orderType(),
                'userPositionValid' => \Igniter\Local\Facades\Location::userPosition()?->isValid(),
            ]);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'delivery_address' => lang('jamasa.core::default.address.required_for_delivery'),
            ]);
        });

        // order write (was two listeners → two writes on the hottest path):
        //  (a) rescue-note stamp for geocoder-blind delivery orders;
        //  (b) order ↔ customer identity for logged-in customers.
        Event::listen('igniter.checkout.afterSaveOrder', function ($order): void {
            if (!$order instanceof \Igniter\Cart\Models\Order) {
                return;
            }

            $orderDirty = false;

            // (a) Geocoder-blind rescue marker (delivery only). Stamped at
            // afterSaveOrder — NOT model.beforeCreate: TI creates a DRAFT order
            // when checkout opens, and a create-time stamp reads whatever the
            // session held at page load — a rescue selected then abandoned leaks
            // into the draft comment and shows up PREFILLED in the note textarea
            // (bit us: order #247, 2026-07-24). Wording is customer-visible
            // (success page/mails) → "WE may contact YOU", never an invitation
            // for the customer to call. Session position is null for API/POS.
            if ($order->isDeliveryType()) {
                $position = \Igniter\Local\Facades\Location::userPosition();
                $note = 'ACHTUNG: Adresse nicht automatisch geprüft - wir kontaktieren dich bei Rückfragen';
                $isRescue = (bool)$position?->getValue('famedoBlindFallback');
                $hasNote = str_contains((string)$order->comment, $note);
                if ($isRescue && !$hasNote) {
                    $order->comment = trim($note."\n".(string)$order->comment);
                    $orderDirty = true;
                } elseif (!$isRescue && $hasNote) {
                    $order->comment = trim(str_replace($note, '', (string)$order->comment));
                    $orderDirty = true;
                }

                // address_verified stamp (delivery-slip Maps-QR gate): a VALID
                // position (has coordinates) ⇒ validateCheckout just geocoded
                // THIS order's address — rescue ⇒ 0 (QR suppressed on the
                // slip), else ⇒ 1. ⚠️ userPosition() NEVER returns null — a
                // positionless session (API/POS orders) yields an empty
                // default object, so gate on isValid(), which leaves the
                // tri-state at NULL there (no QR, no warning — fail-safe).
                // See the 2026-08-05 migration.
                if ($position?->isValid()) {
                    $verified = !$isRescue;
                    if ((bool)$order->address_verified !== $verified || $order->address_verified === null) {
                        $order->address_verified = $verified;
                        $orderDirty = true;
                    }
                }
            }

            // (b) Identity (logged-in customers). ORDER-side: the order email is
            // ALWAYS the account email (email isn't a checkout-editable identity);
            // blank order names fill from the profile (the card variant posts no
            // name). PROFILE-side (below): names SYNC from every order (stable
            // identity, corrected at checkout); phone is FILL-ONLY (an order phone
            // can be situational — dead battery, partner's — never overwrites it).
            $customer = $order->customer_id ? $order->customer : null;
            if ($customer) {
                if (filled($customer->email) && $order->email !== $customer->email) {
                    $order->email = $customer->email;
                    $orderDirty = true;
                }
                foreach (['first_name', 'last_name', 'telephone'] as $field) {
                    if (blank($order->{$field}) && filled($customer->{$field})) {
                        $order->{$field} = $customer->{$field};
                        $orderDirty = true;
                    }
                }
            }

            if ($orderDirty) {
                $order->saveQuietly();
            }

            if ($customer) {
                $customerDirty = false;
                foreach (['first_name', 'last_name'] as $field) {
                    if (filled($order->{$field}) && $customer->{$field} !== $order->{$field}) {
                        $customer->{$field} = $order->{$field};
                        $customerDirty = true;
                    }
                }
                if (blank($customer->telephone) && filled($order->telephone)) {
                    $customer->telephone = $order->telephone;
                    $customerDirty = true;
                }
                if ($customerDirty) {
                    $customer->saveQuietly();
                }
            }
        });

        // ---------- German decimal input on admin forms (2026-08-03) ----------
        // Core's field_currency partial DISPLAYS values with the currency's
        // decimal_sign ("11,50" for EUR/de) while the `numeric` validation rule
        // only accepts machine format — the field renders a value its own form
        // refuses to save (owner types 11,50 → "price must be a number").
        // Normalize German-formatted decimals ("11,50", "1.234,56") to machine
        // format for every field that is explicitly `numeric`-validated, BEFORE
        // the validator is built (dataHolder->data feeds both validation and
        // validated()/save). Dot-decimal input ("11.50") stays untouched.
        Event::listen('system.formRequest.extendValidator', function ($request, $dataHolder): void {
            foreach ($dataHolder->rules as $key => $rules) {
                if (str_contains((string)$key, '*')) {
                    continue; // wildcard rules: no single data path to rewrite
                }
                $ruleList = is_array($rules) ? $rules : explode('|', (string)$rules);
                if (!in_array('numeric', $ruleList, true)) {
                    continue;
                }
                $value = \Illuminate\Support\Arr::get($dataHolder->data, $key);
                if (is_string($value) && preg_match('/^-?(\d{1,3}(\.\d{3})*|\d+),\d+$/', $value)) {
                    \Illuminate\Support\Arr::set(
                        $dataHolder->data,
                        $key,
                        str_replace(',', '.', str_replace('.', '', $value)),
                    );
                }
            }
        });

        // Admin → Orders list: show the customer-facing pickup code as a column.
        // The pickup code is DERIVED from the order hash (not a DB column), so the
        // column is backed by the real `hash` column (the Lists widget emits
        // `SELECT hash AS pickup_code`) and the value is transformed on render.
        // Both listeners are scoped to the Orders list only.
        Event::listen('admin.list.extendColumns', function ($widget): void {
            // Scope to the Orders admin list ONLY — by controller, not by model, so
            // a future Order-based list or dashboard widget can't silently inherit
            // this column (and its `hash` select).
            if (!$widget->getController() instanceof \Igniter\Cart\Http\Controllers\Orders) {
                return;
            }
            $config = [
                'label' => 'Pickup Code',
                'select' => 'hash',        // aliased AS pickup_code (hash is unique to `orders` → no join ambiguity)
                'type' => 'text',
                'sortable' => false,        // derived value, no SQL sort
                'searchable' => false,      // not a real column to search on
            ];
            // Register in BOTH places: addColumns() populates allColumns (render),
            // but TI's List Setup validates a user's saved column override against
            // the raw $widget->columns (Lists::getVisibleColumns). If we only
            // addColumns(), then once a user ticks "Pickup Code" in List Setup and
            // saves, the override contains a column not in $widget->columns and the
            // orders page 500s ("Invalid column name used: pickup_code"). Adding it
            // to columns too keeps the override valid.
            $widget->columns['pickup_code'] = $config;
            $widget->addColumns(['pickup_code' => $config]);
        });
        Event::listen('admin.list.overrideColumnValue', function ($widget, $record, $column, $value) {
            // Only our column, and only a scalar value (overrideColumnValue also
            // fires for button columns where $value is an attributes ARRAY — the
            // is_scalar guard makes the (string) cast crash-proof regardless).
            if ($column->columnName !== 'pickup_code' || !is_scalar($value)) {
                return null;
            }

            return $value ? PickupCode::fromHash((string) $value) : null;
        });

        // Register the ordering-settings + owner-console API under the same prefix
        // + Sanctum auth as the rest of the TastyIgniter API. Falls back to
        // hardcoded values so a boot-order/config timing issue can't leave the
        // route unregistered.
        $apiPrefix = config('igniter-api.prefix') ?: 'api';
        Route::prefix($apiPrefix)
            ->middleware(config('igniter-api.middleware') ?: ['api', \Igniter\Api\Http\Middleware\Authenticate::class])
            ->group(__DIR__.'/../routes/api.php');

        // Owner-console LOGIN — unauthenticated (no token yet), but strictly
        // throttled. Validates admin credentials and mints a short-lived
        // `owner`-scoped token. Kept out of the authenticated group above.
        Route::prefix($apiPrefix)
            ->middleware(['api', 'throttle:10,1'])
            ->post('jamasa/owner/login', [\Jamasa\Core\Http\Controllers\OwnerAuthController::class, 'login']);

        // Photon address suggestions for the account Adressbuch (web session,
        // logged-in customers only — the address page itself is security:customer).
        // Auth is checked IN the controller (401 JSON): the auth middleware's
        // unauthenticated path redirects to route('login'), which TI doesn't
        // define → 500. Throttled to protect photon.komoot.io; the provider's
        // cacheCallback additionally dedupes repeat queries.
        Route::middleware(['web', 'throttle:30,1'])
            ->get('jamasa/address-suggestions', \Jamasa\Core\Http\Controllers\AddressSuggestionsController::class);

        // Delivery-zone pre-check for the Adressbuch save gate (same auth-in-
        // controller pattern). Tighter throttle: one check per completed address,
        // not per keystroke.
        Route::middleware(['web', 'throttle:15,1'])
            ->get('jamasa/address-zone-check', \Jamasa\Core\Http\Controllers\AddressZoneCheckController::class);

        // Public (anonymous) ordering-state poll for the closed/paused overlay —
        // the storefront checks ~2x/min while ordering is unavailable and reloads
        // on a state change. Returns only {state, message}; fails open as 'open'.
        Route::middleware(['web', 'throttle:60,1'])
            ->get('jamasa/ordering-status', \Jamasa\Core\Http\Controllers\OrderingStatusController::class);

        // Guest success-page „Konto anlegen" — a full-page POST (NOT Livewire): the
        // login rotates the CSRF token, which 419'd the order-preview poll when this
        // ran as a wire:click. A POST→redirect reloads the page with a fresh token.
        Route::middleware(['web', 'throttle:10,1'])
            ->post('jamasa/signup-from-order', \Jamasa\Core\Http\Controllers\SignupFromOrderController::class);

        // Brevo transactional-webhook sink (delivery/bounce/rejection telemetry).
        //
        // ⚠️ Registered under `api`, NOT `web`, deliberately: the api group has no
        // CSRF and no session, so an unauthenticated third-party POST needs no
        // VerifyCsrfToken::except() hack. Auth is a bearer token checked IN the
        // controller (Brevo does not sign webhooks — there is no HMAC to verify).
        //
        // ⚠️ NO throttle. Brevo discards an event permanently on any 4xx except
        // 429, so a throttle would silently destroy telemetry during exactly the
        // failure storm we most need to see.
        Route::middleware(['api'])
            ->post('api/jamasa/mail-events', \Jamasa\Core\Http\Controllers\MailEventsController::class);

        // Confine owner-scoped tokens to api/jamasa/* on EVERY /api/* request.
        // TI's stock API authorizes admin resources by tokenable TYPE and ignores
        // abilities, so without this an owner token (minted on an admin user)
        // would be accepted by every stock admin endpoint. Appended to the `api`
        // group so it also covers the dynamically-registered stock resources.
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->appendMiddlewareToGroup('api', \Jamasa\Core\Http\Middleware\ConfineOwnerToken::class);
    }
}
