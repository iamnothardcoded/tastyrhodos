<?php

declare(strict_types=1);

namespace Jamasa\Core;

use Igniter\Local\Events\WorkingScheduleCreatedEvent;
use Igniter\Main\Classes\MainController;
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
 * Central extension for all TastyRhodos customizations.
 * Houses view overrides and business-specific logic.
 *
 * Language overrides remain in /app/lang/vendor/ (Laravel's native mechanism).
 * View overrides are registered here to take precedence over vendor packages.
 */
class Extension extends BaseExtension
{
    #[Override]
    public function boot(): void
    {
        // Override views from igniter-cart extension
        // Place your overrides in: resources/views/igniter-cart/
        $this->loadViewsFrom(__DIR__.'/../resources/views/igniter-cart', 'igniter-cart');

        // Override views from igniter-orange theme
        // Place your overrides in: resources/views/igniter-orange/
        $this->loadViewsFrom(__DIR__.'/../resources/views/igniter-orange', 'igniter-orange');

        // Register Blade directive for pickup code
        // Usage in views: @pickupCode($order->hash)
        Blade::directive('pickupCode', function (string $expression): string {
            return "<?php echo \Jamasa\Core\Helpers\PickupCode::fromHash({$expression}); ?>";
        });

        // Register CSS assets for all frontend pages
        MainController::extend(function ($controller): void {
            $controller->bindEvent('controller.beforeRemap', function () use ($controller): void {
                $controller->addCss('jamasa.core::/css/fixes.css', 'jamasa-fixes');
                // Famedo design system — layers over the theme CSS (added last =
                // wins the cascade). Scoped under the `famedo` body class.
                $controller->addCss('jamasa.core::/css/famedo.css', 'jamasa-famedo');
                // Famedo JS shims (sheet grip swipe-to-close, …)
                $controller->addJs('jamasa.core::/js/famedo.js', 'jamasa-famedo-js');
            });
        });

        // Self-hosted fonts + Font Awesome (DSGVO: no Google Fonts / cdnjs requests).
        // Deploy step: php artisan vendor:publish --tag=jamasa-assets --force
        // famedo.css references these via absolute /vendor/jamasa/... URLs because
        // TI's asset combiner rewrites relative url()s against its virtual route.
        $this->publishes([
            __DIR__.'/../resources/fonts' => public_path('vendor/jamasa/fonts'),
            __DIR__.'/../resources/fontawesome' => public_path('vendor/jamasa/fontawesome'),
        ], 'jamasa-assets');

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
