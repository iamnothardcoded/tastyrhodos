<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Positive gate for the owner console API.
 *
 * TastyIgniter's stock API authorizes admin resources by tokenable TYPE
 * (admin vs customer) and ignores token abilities entirely. So the owner
 * scope is enforced HERE, per route: the resolved token must carry the
 * `owner` ability. A wildcard staff/print-server token (`['*']`) also
 * passes (Sanctum honours `*`), so the KDS/print-server keep working; a
 * customer token or an unscoped token without `owner` is rejected.
 *
 * The complementary ConfineOwnerToken middleware stops an owner-scoped
 * token from reaching anything OUTSIDE api/jamasa/* — together they bound
 * a leaked owner token to exactly the console's surface.
 */
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->tokenCan('owner'), 403, 'Owner scope required.');

        return $next($request);
    }
}
