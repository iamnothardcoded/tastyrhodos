<?php

declare(strict_types=1);

namespace Jamasa\Core\Geolite;

use Igniter\Flame\Geolite\Contracts\GeoQueryInterface;
use Igniter\Flame\Geolite\Place;
use Igniter\Flame\Geolite\Provider\NominatimProvider as BaseNominatimProvider;
use Illuminate\Support\Collection;
use Override;

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
        $results = parent::geocodeQuery($query);

        // house number = trailing token of the first comma-segment
        if (preg_match('/^[^,]*?[\pL.]\s+(\d+\s?[a-zA-Z]?)\s*(?:,|$)/u', trim($query->getText()), $m)) {
            $number = preg_replace('/\s+/', '', $m[1]);
            $results->each(function($location) use ($number): void {
                if (empty($location->getStreetNumber()) && filled($location->getStreetName())) {
                    $location->setStreetNumber($number);
                }
            });
        }

        return $results;
    }

    /**
     * Famedo address flow (2026-07-19, beyond the upstream title fix):
     * - surface the structured address parts (road, house number, postcode,
     *   city, suburb) into the suggestion data — the raw response carries them
     *   (addressdetails=1) but the base provider discards everything except
     *   name/display_name/coords;
     * - drop hits without a road (POIs are noise when entering a delivery
     *   address);
     * - dedupe by road|postcode|city: OSM stores streets as multiple
     *   way-segments and Nominatim returns every segment — customers saw three
     *   near-identical "Cottenburgstraße" rows.
     */
    /**
     * Suggestions are only useful near the restaurant (a Dortmund customer
     * never needs Berlin streets): viewbox biases Nominatim toward the
     * tenant's area, a haversine POST-filter guarantees the radius (bounded=1
     * is flaky with free-text street queries), and an empty first pass is
     * retried with the tenant's city appended — bare common street names
     * ("Hauptstraße") rank terribly in Nominatim's free-text search without
     * context. The delivery-area check at confirm stays the real gate — this
     * is suggestion hygiene, not enforcement.
     */
    private const SUGGESTION_RADIUS_KM = 25;

    /**
     * Ask Nominatim for MANY candidates: it ranks by global "importance"
     * (big-city streets first), so with the default limit of 5 a small local
     * street never even reaches us — the distance filter can only keep what
     * was returned. One request either way; we filter + sort + cap ourselves.
     */
    private const SUGGESTION_FETCH_LIMIT = 30;

    private const SUGGESTION_SHOW_LIMIT = 6;

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

        $places = $this->famedoFetchPlaces($query, $query->getText(), $lat, $lng);

        // second, city-scoped fetch when the first pass found nothing TRULY
        // local — Nominatim's importance ranking favors big-city streets, so
        // the same street name in the tenant's own town often doesn't make the
        // result list at all without city context. Gated to plausibly complete
        // street names: half-typed fragments must not pay a second round trip.
        $nearest = $places->min(fn(Place $place) => (float)($place->getData('distanceKm') ?? INF));
        if (($places->count() < 2 || $nearest > 5)
            && mb_strlen(trim($query->getText())) >= 6
            && ($city = array_get($famedoLocation?->getAddress() ?? [], 'city'))
            && !str_contains(mb_strtolower($query->getText()), mb_strtolower((string)$city))
        ) {
            $places = $places->concat(
                $this->famedoFetchPlaces($query, $query->getText().', '.$city, $lat, $lng),
            );
        }

        // rank: streets whose name actually starts with what was typed first,
        // then by distance — proximity alone would put a fuzzy side-match
        // nearer the restaurant above the street the customer meant
        $typedStreet = mb_strtolower(strtok(trim($query->getText()), " ,"));

        return $places
            ->unique(fn(Place $place): string => mb_strtolower(
                $place->getData('road').'|'.$place->getData('postcode').'|'.$place->getData('city'),
            ))
            ->sortBy(function(Place $place) use ($typedStreet): float {
                $prefixMiss = $typedStreet !== '' && $typedStreet !== false
                    && str_starts_with(mb_strtolower((string)$place->getData('road')), $typedStreet) ? 0 : 1;

                return $prefixMiss * 1000 + (float)($place->getData('distanceKm') ?? 999);
            })
            ->take(self::SUGGESTION_SHOW_LIMIT)
            ->values();
    }

    protected function famedoFetchPlaces(GeoQueryInterface $query, string $text, ?float $lat, ?float $lng): Collection
    {
        $endpoint = array_get($this->config, 'endpoints.places');
        $url = sprintf($endpoint.'search?q=%s&format=json&addressdetails=1&limit=%d',
            rawurlencode($text),
            max($query->getLimit(), self::SUGGESTION_FETCH_LIMIT),
        );

        if ($lat && $lng) {
            $dLat = self::SUGGESTION_RADIUS_KM / 111.32;
            $dLng = self::SUGGESTION_RADIUS_KM / (111.32 * max(cos(deg2rad($lat)), 0.01));
            $url .= sprintf('&viewbox=%F,%F,%F,%F', $lng - $dLng, $lat + $dLat, $lng + $dLng, $lat - $dLat);
        }

        try {
            $result = $this->cacheCallback($url, fn(): array => $this->requestPlacesUrl($url, $query));
        } catch (\Throwable $throwable) {
            // vendor requestPlacesUrl throws on EMPTY responses — a normal
            // outcome here; degrade to "no suggestions", never an error toast
            $this->log(sprintf('Provider "%s" places suggestion lookup empty/failed: %s',
                $this->getName(), $throwable->getMessage()));

            return new Collection;
        }

        return collect($result)
            ->map(function($item) {
                $addr = (array)($item->address ?? []);

                return (new Place)
                    ->placeId((string)$item->place_id)
                    ->title(filled($item->name ?? null) ? $item->name : $item->display_name)
                    ->description($item->display_name)
                    ->provider('nominatim')
                    ->withData('osmType', $item->osm_type)
                    ->withData('osmId', $item->osm_id)
                    ->withData('class', $item->category ?? null)
                    ->withData('latitude', $item->lat ?? null)
                    ->withData('longitude', $item->lon ?? null)
                    ->withData('road', $addr['road'] ?? $addr['pedestrian'] ?? null)
                    ->withData('houseNumber', $addr['house_number'] ?? null)
                    ->withData('postcode', $addr['postcode'] ?? null)
                    ->withData('city', $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['hamlet'] ?? null)
                    ->withData('suburb', $addr['suburb'] ?? null);
            })
            ->filter(fn(Place $place) => filled($place->getData('road')))
            ->map(function(Place $place) use ($lat, $lng): Place {
                if ($lat && $lng
                    && ($pLat = (float)$place->getData('latitude'))
                    && ($pLng = (float)$place->getData('longitude'))
                ) {
                    $place->withData('distanceKm', $this->famedoDistanceKm($lat, $lng, $pLat, $pLng));
                }

                return $place;
            })
            ->filter(fn(Place $place): bool => !$lat || !$lng
                || (float)($place->getData('distanceKm') ?? INF) <= self::SUGGESTION_RADIUS_KM)
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
