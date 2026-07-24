<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Controllers;

use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\Flame\Geolite\GeoQuery;
use Igniter\User\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Photon address suggestions for the account Adressbuch (famedo address-book
 * form). Same provider pipeline as the checkout search (jamasa
 * NominatimProvider::placesAutocomplete — tenant radius, POI filter, cache),
 * exposed as a lean JSON endpoint because the vendor AddressBook Livewire
 * component is final and carries no suggestion machinery.
 */
class AddressSuggestionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        // Guarded here, not via auth middleware: the middleware's unauthenticated
        // path redirects to route('login'), which TI doesn't define (→ 500).
        if (!Auth::customer()) {
            return response()->json([], 401);
        }

        $query = trim((string)$request->query('q', ''));
        if (mb_strlen($query) < 3) {
            return response()->json([]);
        }

        try {
            $suggestions = Geocoder::driver()->placesAutocomplete(GeoQuery::create($query))->toArray();
        } catch (Throwable) {
            // Degrade silently — the UI shows "no suggestions", same as checkout.
            return response()->json([]);
        }

        return response()->json(array_values(array_map(fn(array $s): array => [
            'road' => array_get($s, 'data.road'),
            'houseNumber' => array_get($s, 'data.houseNumber'),
            'postcode' => array_get($s, 'data.postcode'),
            'city' => array_get($s, 'data.city'),
        ], $suggestions)));
    }
}
