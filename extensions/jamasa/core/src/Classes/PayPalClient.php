<?php

declare(strict_types=1);

namespace Jamasa\Core\Classes;

use Igniter\PayRegister\Classes\PayPalClient as BasePayPalClient;

/**
 * Fixed PayPal client: upstream selects the API host by app()->environment()
 * instead of the gateway's sandbox flag (PaypalExpress::createClient calls
 * setSandbox(), but endpoint() ignores $this->sandbox). Under APP_ENV=production
 * sandbox mode is impossible; under any other env LIVE mode would silently hit
 * the sandbox host. Remove once fixed upstream (ti-ext-payregister).
 *
 * Note: upstream also caches the access token under a mode-agnostic key
 * ('payregister_paypal_access_token') — flush the cache after switching
 * api_mode on a running install.
 */
class PayPalClient extends BasePayPalClient
{
    protected function endpoint(string $uri): string
    {
        return 'https://'.($this->sandbox ? 'api-m.sandbox.paypal.com/' : 'api-m.paypal.com/').$uri;
    }
}
