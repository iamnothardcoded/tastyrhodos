<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Middleware;

use Closure;
use Igniter\Api\Models\Token;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confine a SCOPED token to the surface it was minted for.
 *
 * Why this exists — TWO enforcement layers, they cover different ground:
 *
 *  1. STOCK resources (`api/orders`, `api/customers`, …) DO check abilities:
 *     each `ApiResource` declares `$requiredAbilities` (`orders:*`,
 *     `customers:*`, …) and `ApiController::authorizeToken()` enforces it.
 *     So a token minted with `['orders:*']` already cannot touch customers or
 *     menus. (An earlier version of this docblock claimed abilities were
 *     ignored — that is wrong, verified on dev 2026-08-07.)
 *
 *  2. JAMASA endpoints (`api/jamasa/*`) do NOT go through ApiController, so
 *     nothing checks abilities there. Without this middleware, ANY valid token
 *     reaches every jamasa endpoint — including `jamasa/owner/customers.csv`,
 *     the full customer export. THAT is the hole this closes.
 *
 * A `*` wildcard token bypasses both layers and reaches all 88 API routes,
 * including DELETE on customers, menus, orders and users (measured on
 * elgrecomarl 2026-08-07).
 *
 * Appended to the `api` middleware group, so it runs for every /api/* request.
 * It resolves the bearer token directly (order-independent — it does not depend
 * on Authenticate having run). A token is "scoped" when it carries one of the
 * abilities below and does NOT carry the `*` wildcard; it may then reach only
 * that scope's path patterns, and 403s everywhere else.
 *
 * Wildcard staff/print-server tokens (`['*']`) are never scoped, so they are
 * completely unaffected and keep full API access.
 *
 * ⚠️ `hub` exists because the Order Manager genuinely needs stock `api/orders`
 * (list + status update) alongside `api/jamasa/*` — an `owner`-scoped token
 * would 403 on it. The residual blast radius of a leaked `hub` token is
 * therefore order data (customer name/address/phone) plus order-status changes.
 * Closing that last gap means giving jamasa its own order endpoints so `hub`
 * can drop to `api/jamasa/*` — worth doing, not done yet.
 */
class ConfineOwnerToken
{
    /**
     * ability => path patterns it may reach (relative to the API prefix).
     * First matching ability wins; order is therefore narrowest-first.
     */
    private const SCOPES = [
        'owner' => ['jamasa/*'],
        // The Order Manager needs exactly two things. Deliberately NOT
        // `jamasa/*`: that would hand a leaked hub token the owner-console
        // endpoints, including `jamasa/owner/customers.csv` — the full customer
        // export. Stock `orders` is separately gated by TI's own ability check
        // (see the class docblock), so a hub token must ALSO carry `orders:*`.
        'hub' => ['jamasa/ordering-settings', 'orders', 'orders/*'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($bearer = $request->bearerToken()) {
            $token = Token::findToken($bearer);
            $abilities = $token && is_array($token->abilities) ? $token->abilities : [];

            if (!in_array('*', $abilities, true)) {
                $prefix = config('igniter-api.prefix') ?: 'api';

                foreach (self::SCOPES as $ability => $patterns) {
                    if (!in_array($ability, $abilities, true)) {
                        continue;
                    }

                    $allowed = array_map(static fn($p): string => $prefix.'/'.$p, $patterns);

                    abort_unless(
                        $request->is(...$allowed),
                        403,
                        sprintf('This token is limited to the %s surface.', $ability),
                    );

                    break;
                }
            }
        }

        return $next($request);
    }
}
