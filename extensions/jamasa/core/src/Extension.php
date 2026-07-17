<?php

declare(strict_types=1);

namespace Jamasa\Core;

use Igniter\Local\Events\WorkingScheduleCreatedEvent;
use Igniter\System\Classes\BaseExtension;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Jamasa\Core\Helpers\PickupCode;
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

        // Register the ordering-settings API under the same prefix + Sanctum auth
        // as the rest of the TastyIgniter API. Falls back to hardcoded values so a
        // boot-order/config timing issue can't leave the route unregistered.
        Route::prefix(config('igniter-api.prefix') ?: 'api')
            ->middleware(config('igniter-api.middleware') ?: ['api', \Igniter\Api\Http\Middleware\Authenticate::class])
            ->group(__DIR__.'/../routes/api.php');
    }
}
