<?php

declare(strict_types=1);

namespace Jamasa\Core\Geolite;

use Igniter\Flame\Geolite\Contracts\GeoQueryInterface;
use Igniter\Flame\Geolite\GeoQuery;
use Igniter\Flame\Geolite\Model\Location as GeoliteLocation;
use Igniter\Flame\Geolite\Place;
use Igniter\Flame\Geolite\Provider\NominatimProvider as BaseNominatimProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Override;
use Throwable;

/**
 * Nominatim provider with a fixed placesAutocomplete.
 *
 * Upstream bug: the vendor provider maps the suggestion title from Nominatim's
 * `name` field, which is EMPTY for plain street addresses (only POIs carry a
 * name). Selecting such a suggestion blanks the theme's searchQuery, and
 * FulfillmentModal::onConfirm() then silently skips storing the delivery
 * position (`if ($this->searchQuery && ...)`) — checkout ends up with
 * "No delivery address provided".
 *
 * Fix: fall back to the full display_name as the title. It must stay a
 * GEOCODABLE string (onConfirm re-geocodes the title verbatim), so the
 * complete display_name — Nominatim's own output — is the safe choice.
 *
 * Registered over the built-in 'nominatim' creator in Extension::boot();
 * the chain provider resolves through the same custom creator.
 *
 * ⚠️ TEMPORARY WORKAROUND — submitted upstream as
 * https://github.com/tastyigniter/core/pull/65
 * Once that PR is merged AND our installed tastyigniter/core includes it
 * (check the release notes / `->title(` line in the vendor provider), DELETE
 * this class and its Geocoder::extend('nominatim', ...) registration in
 * Extension::boot(). Keep the region/locale DE config block — that part is
 * a famedo default, not a bug workaround.
 */
class NominatimProvider extends BaseNominatimProvider
{
    /**
     * Customer-truth house numbers (famedo address flow): when the QUERY
     * carries a house number ("Cottenburgstraße 12, 44575 …") but OSM doesn't
     * know that building, Nominatim matches the street and the result has an
     * EMPTY street number — which then silently drops the number from the
     * session address and the saved order ("valid street address" checkout
     * error). The flat exists even if OSM's map doesn't: re-attach the
     * customer's typed number to number-less results.
     */
    #[Override]
    public function geocodeQuery(GeoQueryInterface $query): Collection
    {
        try {
            $results = parent::geocodeQuery($query);
        } catch (Throwable) {
            // Nominatim down = "can't tell" — same rule as an empty result below.
            $results = collect();
        }

        // house number = trailing token of the first comma-segment
        if (preg_match('/^[^,]*?[\pL.]\s+(\d+\s?[a-zA-Z]?)\s*(?:,|$)/u', trim($query->getText()), $m)) {
            $number = preg_replace('/\s+/', '', $m[1]);
            $results->each(function($location) use ($number): void {
                if (empty($location->getStreetNumber()) && filled($location->getStreetName())) {
                    $location->setStreetNumber($number);
                }
            });
        }

        if ($results->isEmpty() && ($parts = $this->famedoParseAddress($query->getText())) !== null) {
            // BEFORE rescuing, retry ONCE with the canonical German composition:
            // Nominatim's free-form parser chokes on the vendor's comma-less
            // implode shape even for REAL addresses — the clean retry geocodes
            // those correctly (real result, no rescue, no ACHTUNG stamp). Only
            // an address that fails BOTH attempts is genuinely geocoder-blind.
            $canonical = sprintf('%s %s, %s %s', $parts['street'], $parts['number'], $parts['postcode'], $parts['city']);
            if ($canonical !== trim($query->getText())) {
                try {
                    $results = parent::geocodeQuery(GeoQuery::create($canonical));
                } catch (Throwable) {
                    $results = collect();
                }
                $results->each(function($location) use ($parts): void {
                    if (empty($location->getStreetNumber()) && filled($location->getStreetName())) {
                        $location->setStreetNumber($parts['number']);
                    }
                });
            }

            if ($results->isEmpty() && ($blind = $this->famedoSynthesize($parts, $query))) {
                $results = collect([$blind]);
            }
        }

        return $results;
    }

    /**
     * Geocoder-blind FAIL-OPEN (user decision 2026-07-24): when Nominatim
     * cannot place a full delivery address at all, the customer must still be
     * able to order — the restaurant judges by phone, offline. The platform
     * rule: "block only when the system is CONFIDENT; uncertainty never blocks
     * a human." A confidently-geocoded out-of-zone address still gets rejected
     * by the normal area check — this fallback never fires then (results
     * non-empty).
     *
     * Mechanics: synthesize a result carrying the CUSTOMER'S OWN address parts
     * (parsed from the query we composed ourselves) but the RESTAURANT'S
     * coordinates — so every downstream gate works unchanged: the zone check
     * lands in the restaurant's own (default) area → default delivery fee,
     * and checkout's prepareDeliveryAddress extracts the customer's real
     * street/number/PLZ/city for the order.
     *
     * Strictly scoped: only fires for a full street address, in one of the
     * THREE shapes our own flows produce:
     *   A: "Straße Nr, 44575 Stadt"        (fulfillment sheet compose)
     *   B: "Straße Nr, Stadt 44575"        (format_address() on saved addresses)
     *   C: "Nr Straße Stadt [Stadt] 44575 [Germany]"
     *      (OrderManager::validateDeliveryAddress implode(' ', fields) at final
     *       submission — comma-less, number-first, state usually duplicating
     *       the city, country appended)
     * Arbitrary queries (admin location geocoding etc.) keep their honest
     * empty result.
     */
    /** @return ?array{street: string, number: string, postcode: string, city: string} */
    protected function famedoParseAddress(string $text): ?array
    {
        $text = trim($text);
        $text = trim((string)preg_replace('/[\s,]*(Germany|Deutschland)\s*$/iu', '', $text));

        if (preg_match('/^(.+?)\s+(\d+\s?[a-zA-Z]?)\s*,\s*(.+)$/u', $text, $m)) {
            // shapes A/B — PLZ + city in either order after the comma
            [, $street, $number, $rest] = $m;
            if (!preg_match('/\b(\d{5})\b/', $rest, $pm)) {
                return null;
            }
            $postcode = $pm[1];
            $city = trim(str_replace($postcode, '', $rest), " \t,");
        } elseif (preg_match('/^(\d+\s?[a-zA-Z]?)\s+(.+?)\s+(\d{5})$/u', $text, $m)) {
            // shape C — collapse the "city city" repetition (state == city),
            // else assume the last token is the city
            [, $number, $middle, $postcode] = $m;
            if (preg_match('/^(.*?)\s+(.+?)\s+\2$/u', $middle, $mm)) {
                $street = trim($mm[1]);
                $city = trim($mm[2]);
            } else {
                $tokens = preg_split('/\s+/', $middle);
                $city = trim((string)array_pop($tokens));
                $street = trim(implode(' ', $tokens));
            }
        } else {
            return null;
        }

        $street = trim($street);
        $city = trim($city);
        if ($street === '' || $city === '') {
            return null;
        }

        return [
            'street' => $street,
            'number' => (string)preg_replace('/\s+/', '', $number),
            'postcode' => $postcode,
            'city' => $city,
        ];
    }

    /** @param array{street: string, number: string, postcode: string, city: string} $parts */
    protected function famedoSynthesize(array $parts, GeoQueryInterface $query): ?GeoliteLocation
    {
        $famedoLocation = \Igniter\Local\Facades\Location::current()
            ?? \Igniter\Local\Models\Location::getDefault();
        $coordinates = $famedoLocation?->getCoordinates();
        if (!$coordinates) {
            return null;
        }

        Log::notice(sprintf('famedo geocoder-blind fallback: "%s" accepted at restaurant coordinates (default zone/fee applies)', $query->getText()));

        return (new GeoliteLocation('famedo-blind-fallback'))
            ->setCoordinates($coordinates->getLatitude(), $coordinates->getLongitude())
            ->setStreetName($parts['street'])
            ->setStreetNumber($parts['number'])
            ->setPostalCode($parts['postcode'])
            ->setSubLocality($parts['city'])   // checkout maps `city` FROM subLocality
            // Locality deliberately NOT set: checkout maps it to `state`, which
            // then duplicates the city in every rendered address ("…, Stadt PLZ,
            // Stadt") and re-enters the validation implode twice.
            ->setCountryCode('DE')
            ->setCountryName('Deutschland')
            ->setValue('famedoBlindFallback', true)
            ->withFormattedAddress(sprintf('%s %s, %s %s', $parts['street'], $parts['number'], $parts['postcode'], $parts['city']));
    }

    /**
     * Famedo address suggestions run on PHOTON (photon.komoot.io), not on
     * Nominatim's search endpoint: Nominatim's own docs state it is NOT
     * suitable for autocomplete, and it showed — no prefix matching, no typo
     * tolerance, importance ranking that hid the tenant-town street behind
     * big-city namesakes. Photon is the established OSM autocomplete engine
     * (built by Komoot, DE): prefix search-as-you-type, typo tolerance,
     * native proximity bias via lat/lon, building-level hits incl. house
     * numbers. Self-hostable later if volume demands it.
     *
     * Our layer on top stays minimal and deterministic:
     * - structured parts (road/houseNumber/postcode/city) into suggestion data
     * - drop hits without a street; hard radius filter (per-tenant, famedoSuggestionRadiusKm)
     * - dedupe by road|nr|postcode|city (way-segments come back per type)
     * - number-stripped retry: a typed house number OSM doesn't know returns
     *   ZERO hits — retry with the number removed so the street still
     *   suggests; the sheet re-attaches the typed number (customer truth).
     *
     * Geocode VERIFICATION (onConfirm / checkout) stays on Nominatim — that
     * path works and carries our house-number injection above.
     */
    /**
     * Suggestion radius derives PER TENANT from the configured delivery
     * areas: farthest reach of any area (circle: restaurant→center + radius;
     * polygon: farthest vertex) × BUFFER, clamped to [FLOOR, CAP] km.
     * Buffer + floor exist because street search points are way-segment
     * centers (can sit a few hundred meters from the buildings) and because
     * hiding a deliverable street is worse than showing a borderline one —
     * the delivery-area check at confirm stays the real gate. Tenants with
     * no usable area geometry fall back to FALLBACK km.
     */
    private const SUGGESTION_RADIUS_BUFFER = 1.25;

    private const SUGGESTION_RADIUS_FLOOR_KM = 5.0;

    private const SUGGESTION_RADIUS_CAP_KM = 30.0;

    private const SUGGESTION_RADIUS_FALLBACK_KM = 10.0;

    private const SUGGESTION_SHOW_LIMIT = 6;

    private const PHOTON_ENDPOINT = 'https://photon.komoot.io/api/';

    #[Override]
    public function placesAutocomplete(GeoQueryInterface $query): Collection
    {
        // current() needs the storefront session; fall back to the default
        // location so CLI/queue contexts behave identically
        $famedoLocation = \Igniter\Local\Facades\Location::current()
            ?? \Igniter\Local\Models\Location::getDefault();
        $coordinates = $famedoLocation?->getCoordinates();
        $lat = $coordinates?->getLatitude() ?: null;
        $lng = $coordinates?->getLongitude() ?: null;

        $radiusKm = $this->famedoSuggestionRadiusKm($famedoLocation, $lat, $lng);

        $text = trim($query->getText());
        $places = $this->famedoFetchPhoton($text, $lat, $lng, $radiusKm);

        // typed-but-unmapped house number → zero hits; suggest the street
        if ($places->isEmpty()
            && preg_match('/^(.*[\pL.])\s+\d+\s*[a-zA-Z]?\s*$/u', $text, $m)
        ) {
            $places = $this->famedoFetchPhoton(trim($m[1]), $lat, $lng, $radiusKm);
        }

        return $places
            ->unique(fn(Place $place): string => mb_strtolower(
                $place->getData('road').'|'.$place->getData('houseNumber')
                .'|'.$place->getData('postcode').'|'.$place->getData('city'),
            ))
            ->take(self::SUGGESTION_SHOW_LIMIT)
            ->values();
    }

    protected function famedoSuggestionRadiusKm($location, ?float $lat, ?float $lng): float
    {
        if (!$location || !$lat || !$lng) {
            return self::SUGGESTION_RADIUS_FALLBACK_KM;
        }

        $extentKm = 0.0;
        foreach ($location->delivery_areas ?? [] as $area) {
            if ($area->isPolygonBoundary()) {
                foreach ($area->vertices as $vertex) {
                    if (isset($vertex->lat, $vertex->lng)) {
                        $extentKm = max($extentKm, $this->famedoDistanceKm($lat, $lng, (float)$vertex->lat, (float)$vertex->lng));
                    }
                }
            } elseif (($circle = $area->circle) && isset($circle->lat, $circle->lng, $circle->radius)) {
                // circle radius is stored in METERS
                $extentKm = max($extentKm,
                    $this->famedoDistanceKm($lat, $lng, (float)$circle->lat, (float)$circle->lng) + (float)$circle->radius / 1000);
            }
        }

        if ($extentKm <= 0) {
            return self::SUGGESTION_RADIUS_FALLBACK_KM;
        }

        return min(max($extentKm * self::SUGGESTION_RADIUS_BUFFER, self::SUGGESTION_RADIUS_FLOOR_KM), self::SUGGESTION_RADIUS_CAP_KM);
    }

    protected function famedoFetchPhoton(string $text, ?float $lat, ?float $lng, float $radiusKm = self::SUGGESTION_RADIUS_FALLBACK_KM): Collection
    {
        // limit=50: Photon assembles its candidate pool by IMPORTANCE first
        // and applies the proximity bias when ranking — with a small limit a
        // residential street in the tenant's town loses every pool slot to
        // big-city namesakes (repro: "borns" returned only Dortmund at
        // limit=12, Castrop first at 50). layer restricts at the source:
        // streets only — plus address points when a house number was typed
        // (villages/farms named "Born" were eating the pool otherwise).
        $layers = preg_match('/\d/', $text) ? '&layer=house&layer=street' : '&layer=street';
        $url = self::PHOTON_ENDPOINT.'?q='.rawurlencode($text).'&limit=50&lang=de'.$layers;
        if ($lat && $lng) {
            $url .= sprintf('&lat=%F&lon=%F', $lat, $lng);
        }

        try {
            $features = $this->cacheCallback($url, function() use ($url): array {
                $response = $this->httpClient->get($url, [
                    'timeout' => 5,
                    'headers' => ['User-Agent' => 'famedo-storefront (kontakt: iam@nothardcoded.io)'],
                ]);

                $data = json_decode((string)$response->getBody());
                // throw on invalid payloads (throttle/error pages) so they are
                // NEVER cached — a poisoned 30-day cache entry once made a
                // street "not exist"; a valid empty features list stays cacheable
                throw_unless(isset($data->features) && is_array($data->features),
                    new \RuntimeException('invalid photon response'));

                return $data->features;
            });
        } catch (\Throwable $throwable) {
            // a failed lookup degrades to "no suggestions", never an error toast
            $this->log(sprintf('Photon suggestion lookup failed: %s', $throwable->getMessage()));

            return new Collection;
        }

        return collect($features)
            ->map(function($feature) {
                $p = $feature->properties ?? new \stdClass;
                // street hits carry the name in `name`; building/POI hits in `street`
                $road = $p->street ?? (($p->osm_key ?? '') === 'highway' ? ($p->name ?? null) : null);
                [$pLng, $pLat] = ($feature->geometry->coordinates ?? [null, null]);

                return (new Place)
                    ->placeId((string)($p->osm_id ?? md5(json_encode($p))))
                    ->title(trim(($road ?? ($p->name ?? '')).' '.($p->housenumber ?? '')))
                    ->description(trim(($p->postcode ?? '').' '.($p->city ?? '')))
                    ->provider('nominatim')
                    ->withData('osmKey', $p->osm_key ?? null)
                    ->withData('latitude', $pLat)
                    ->withData('longitude', $pLng)
                    ->withData('road', $road)
                    ->withData('houseNumber', $p->housenumber ?? null)
                    ->withData('postcode', $p->postcode ?? null)
                    ->withData('city', $p->city ?? null)
                    ->withData('suburb', $p->district ?? null);
            })
            ->filter(fn(Place $place) => filled($place->getData('road')))
            // POIs whose NAME matched the query masquerade as their street
            // ("Viktoria-Gymnasium" surfaced as Kurfürstenplatz, Essen): with
            // no digit in the query only real streets qualify; a typed number
            // additionally admits address points carrying a house number
            ->filter(fn(Place $place): bool => $place->getData('osmKey') === 'highway'
                || (preg_match('/\d/', $text) && filled($place->getData('houseNumber'))))
            ->filter(function(Place $place) use ($lat, $lng, $radiusKm): bool {
                if (!$lat || !$lng) {
                    return true;
                }
                $pLat = (float)$place->getData('latitude');
                $pLng = (float)$place->getData('longitude');

                return $pLat && $pLng
                    && $this->famedoDistanceKm($lat, $lng, $pLat, $pLng) <= $radiusKm;
            })
            ->values();
    }

    protected function famedoDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
