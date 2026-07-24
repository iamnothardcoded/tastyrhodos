<?php

declare(strict_types=1);

namespace Jamasa\Core;

use Igniter\Local\Events\WorkingScheduleCreatedEvent;
use Igniter\System\Classes\BaseExtension;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Jamasa\Core\Console\CreateOwner;
use Jamasa\Core\Console\SyncSettings;
use Jamasa\Core\Helpers\PickupCode;
use Jamasa\Core\Listeners\AutoAcceptOrder;
use Jamasa\Core\Listeners\PauseWorkingSchedule;
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

        // famedo:create-owner — provision a locked-down owner-console account
        // per tenant (their e-mail = login username). See CreateOwner.
        $this->registerConsoleCommand('famedo.create-owner', CreateOwner::class);
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

            // De-brand: suppress the `X-Powered-By: TastyIgniter` response header
            // (core PoweredBy middleware, default on). Keeps the platform's
            // framework out of a competitor's first `curl -I`. Cosmetic — nothing
            // depends on the header. PHP's own expose_php is Off in the image so
            // no `X-Powered-By: PHP/x` replaces it.
            'igniter-system.sendPoweredByHeader' => false,
        ]);

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

        // When ordering is paused (jamasa_ordering_state.paused), force the
        // location's working schedule closed so the storefront shows the native
        // "CLOSED" state (browsable menu, checkout gated) instead of disabling
        // order types (redirect loop) or the location (500). See PauseWorkingSchedule.
        Event::listen(WorkingScheduleCreatedEvent::class, [PauseWorkingSchedule::class, 'handle']);

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
        Event::listen('igniter.checkout.afterSaveOrder', function ($order): void {
            if (!$order instanceof \Igniter\Cart\Models\Order || !$order->isDeliveryType()) {
                return;
            }

            // Wording is customer-visible too (comment shows on the success
            // page/mails) — must read as "WE may contact YOU", never as an
            // invitation for the customer to call the restaurant.
            $note = 'ACHTUNG: Adresse nicht automatisch geprüft - wir kontaktieren dich bei Rückfragen';
            $isRescue = (bool)\Igniter\Local\Facades\Location::userPosition()?->getValue('famedoBlindFallback');
            $hasNote = str_contains((string)$order->comment, $note);

            if ($isRescue && !$hasNote) {
                $order->comment = trim($note."\n".(string)$order->comment);
                $order->saveQuietly();
            } elseif (!$isRescue && $hasNote) {
                $order->comment = trim(str_replace($note, '', (string)$order->comment));
                $order->saveQuietly();
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

        // Confine owner-scoped tokens to api/jamasa/* on EVERY /api/* request.
        // TI's stock API authorizes admin resources by tokenable TYPE and ignores
        // abilities, so without this an owner token (minted on an admin user)
        // would be accepted by every stock admin endpoint. Appended to the `api`
        // group so it also covers the dynamically-registered stock resources.
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->appendMiddlewareToGroup('api', \Jamasa\Core\Http\Middleware\ConfineOwnerToken::class);
    }
}
