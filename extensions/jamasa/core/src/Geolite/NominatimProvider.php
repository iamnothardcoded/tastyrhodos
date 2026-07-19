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
     * - drop hits without a street; hard radius filter (SUGGESTION_RADIUS_KM)
     * - dedupe by road|nr|postcode|city (way-segments come back per type)
     * - number-stripped retry: a typed house number OSM doesn't know returns
     *   ZERO hits — retry with the number removed so the street still
     *   suggests; the sheet re-attaches the typed number (customer truth).
     *
     * Geocode VERIFICATION (onConfirm / checkout) stays on Nominatim — that
     * path works and carries our house-number injection above.
     */
    private const SUGGESTION_RADIUS_KM = 25;

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

        $text = trim($query->getText());
        $places = $this->famedoFetchPhoton($text, $lat, $lng);

        // typed-but-unmapped house number → zero hits; suggest the street
        if ($places->isEmpty()
            && preg_match('/^(.*[\pL.])\s+\d+\s*[a-zA-Z]?\s*$/u', $text, $m)
        ) {
            $places = $this->famedoFetchPhoton(trim($m[1]), $lat, $lng);
        }

        return $places
            ->unique(fn(Place $place): string => mb_strtolower(
                $place->getData('road').'|'.$place->getData('houseNumber')
                .'|'.$place->getData('postcode').'|'.$place->getData('city'),
            ))
            ->take(self::SUGGESTION_SHOW_LIMIT)
            ->values();
    }

    protected function famedoFetchPhoton(string $text, ?float $lat, ?float $lng): Collection
    {
        $url = self::PHOTON_ENDPOINT.'?q='.rawurlencode($text).'&limit=12&lang=de';
        if ($lat && $lng) {
            $url .= sprintf('&lat=%F&lon=%F', $lat, $lng);
        }

        try {
            $features = $this->cacheCallback($url, function() use ($url): array {
                $response = $this->httpClient->get($url, [
                    'timeout' => 5,
                    'headers' => ['User-Agent' => 'famedo-storefront (kontakt: iam@nothardcoded.io)'],
                ]);

                return json_decode((string)$response->getBody())->features ?? [];
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
                    ->withData('latitude', $pLat)
                    ->withData('longitude', $pLng)
                    ->withData('road', $road)
                    ->withData('houseNumber', $p->housenumber ?? null)
                    ->withData('postcode', $p->postcode ?? null)
                    ->withData('city', $p->city ?? null)
                    ->withData('suburb', $p->district ?? null);
            })
            ->filter(fn(Place $place) => filled($place->getData('road')))
            ->filter(function(Place $place) use ($lat, $lng): bool {
                if (!$lat || !$lng) {
                    return true;
                }
                $pLat = (float)$place->getData('latitude');
                $pLng = (float)$place->getData('longitude');

                return $pLat && $pLng
                    && $this->famedoDistanceKm($lat, $lng, $pLat, $pLng) <= self::SUGGESTION_RADIUS_KM;
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
