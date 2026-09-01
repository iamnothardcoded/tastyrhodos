<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Middleware;

use Closure;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Illuminate\Http\Request;
use Throwable;

/**
 * Correct an unusable session order type BEFORE the page renders.
 *
 * Why this exists (found at the pickup-only-badge build, 2026-09-01):
 * `Location::orderType()` defaults to DELIVERY. On a location where delivery
 * is disabled, a fresh session therefore starts on a disabled type; orange's
 * FulfillmentModal::updateCurrentOrderType() repairs that at Livewire mount
 * by writing the session and REDIRECTING to the same URL. Browsers follow it
 * once (cookie saved) — but a cookie-less client gets the identical 302 on
 * every attempt and loops forever. Crawlers keep no cookies, so every page
 * of a pickup-only tenant would be invisible to search engines.
 *
 * Doing the same correction here — session write, no redirect — means the
 * vendor mount finds a valid type and never redirects; the first response is
 * a plain 200 for everyone, crawlers included.
 *
 * Deliberately narrow: acts ONLY when the effective type (session or the
 * delivery default) is disabled — exactly the case where the vendor would
 * redirect. Normal two-type tenants never hit the branch, so nothing changes
 * for them (no early session writes, no extra events). The choice mirrors
 * the vendor's: prefer the delivery default, else the first active type.
 * `location.orderType.updated` fires just as it would on the vendor path.
 *
 * Appended to `web` (after StartSession — same pattern as
 * CaptureChannelSource). Fails open: this is a convenience repair and must
 * never break a request.
 */
final class EnsureSessionOrderType
{
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            if (Location::current() && !Location::hasOrderType(Location::orderType())) {
                $active = Location::getActiveOrderTypes();
                if ($active->isNotEmpty()) {
                    $code = Location::hasOrderType(LocationModel::DELIVERY)
                        ? LocationModel::DELIVERY
                        : $active->first()->getCode();
                    Location::updateOrderType($code);
                }
            }
        } catch (Throwable) {
            // fail open — never block the request over a session repair
        }

        return $next($request);
    }
}
