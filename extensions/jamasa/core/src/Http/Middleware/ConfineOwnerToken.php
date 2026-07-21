<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Middleware;

use Closure;
use Igniter\Api\Models\Token;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confine an owner-scoped token to the owner-console surface.
 *
 * Why this exists: TI's stock REST API authorizes admin resources purely by
 * tokenable TYPE (any admin-owned token passes), never by ability. The owner
 * token is minted on an admin User (the natural login identity), so WITHOUT
 * this guard a leaked owner token would be accepted by every stock admin
 * endpoint (menus, orders, customers, staff…) — the exact blast radius the
 * console is meant to avoid.
 *
 * This middleware is appended to the `api` middleware group, so it runs for
 * every /api/* request. It resolves the bearer token directly (order-
 * independent — it does not depend on Authenticate having run) and, IF the
 * token is owner-scoped (carries `owner`, does NOT carry the `*` wildcard),
 * permits ONLY api/jamasa/* paths. Everything else 403s.
 *
 * Wildcard staff/print-server tokens (`['*']`) are never "owner-scoped", so
 * they are completely unaffected and keep full API access.
 */
class ConfineOwnerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($bearer = $request->bearerToken()) {
            $token = Token::findToken($bearer);
            $abilities = $token && is_array($token->abilities) ? $token->abilities : [];

            $isOwnerScoped = in_array('owner', $abilities, true)
                && !in_array('*', $abilities, true);

            if ($isOwnerScoped) {
                $prefix = config('igniter-api.prefix') ?: 'api';
                abort_unless(
                    $request->is($prefix.'/jamasa/*'),
                    403,
                    'This token is limited to the owner panel.',
                );
            }
        }

        return $next($request);
    }
}
