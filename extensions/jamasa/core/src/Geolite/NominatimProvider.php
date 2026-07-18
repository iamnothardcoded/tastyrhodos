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
    #[Override]
    public function placesAutocomplete(GeoQueryInterface $query): Collection
    {
        $endpoint = array_get($this->config, 'endpoints.places');
        $url = sprintf($endpoint.'search?q=%s&format=json&addressdetails=1&limit=%d',
            rawurlencode($query->getText()),
            $query->getLimit(),
        );

        $result = $this->cacheCallback($url, fn(): array => $this->requestPlacesUrl($url, $query));

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
            ->unique(fn(Place $place): string => mb_strtolower(
                $place->getData('road').'|'.$place->getData('postcode').'|'.$place->getData('city'),
            ))
            ->values();
    }
}
