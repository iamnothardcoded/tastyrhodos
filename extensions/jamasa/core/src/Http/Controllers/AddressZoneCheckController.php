<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Controllers;

use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\User\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Delivery-zone pre-check for the account Adressbuch: famedo is one restaurant
 * per site, so an address the restaurant can never deliver to is garbage data —
 * the form blocks saving it. Geocodes the composed address (jamasa
 * NominatimProvider::geocodeQuery — re-attaches the typed Hausnummer) and runs
 * the SAME searchDeliveryArea check the fulfillment sheet uses.
 *
 * Response: {covered: true|false|null} — null = could not geocode (Nominatim
 * down / address unknown). Null FAILS OPEN client-side: never punish a customer
 * for a geocoder hiccup; checkout's zone gate remains the authoritative check.
 */
class AddressZoneCheckController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!Auth::customer()) {
            return response()->json([], 401);
        }

        $query = trim((string)$request->query('q', ''));
        if (mb_strlen($query) < 5) {
            return response()->json(['covered' => null]);
        }

        try {
            $position = Geocoder::geocode($query)->first();
            if (!$position || !$position->hasCoordinates()) {
                return response()->json(['covered' => null]);
            }

            // The geocoder-blind fail-open synthesizes a result at the
            // RESTAURANT's coordinates — reporting that as covered:true would
            // hide "we couldn't actually verify this" from the customer. Be
            // honest: null = unverified (soft notice, never blocks).
            if ($position->getValue('famedoBlindFallback')) {
                return response()->json(['covered' => null]);
            }

            $location = \Igniter\Local\Facades\Location::current()
                ?? \Igniter\Local\Models\Location::getDefault();
            if (!$location) {
                return response()->json(['covered' => null]);
            }

            return response()->json([
                'covered' => (bool)$location->searchDeliveryArea($position->getCoordinates()),
            ]);
        } catch (Throwable) {
            return response()->json(['covered' => null]);
        }
    }
}
