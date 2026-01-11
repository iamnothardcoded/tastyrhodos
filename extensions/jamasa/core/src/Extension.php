<?php

declare(strict_types=1);

namespace Jamasa\Core;

use Igniter\System\Classes\BaseExtension;
use Illuminate\Support\Facades\Blade;
use Jamasa\Core\Helpers\PickupCode;
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
    }
}
