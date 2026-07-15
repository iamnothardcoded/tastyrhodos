<?php

declare(strict_types=1);

namespace Jamasa\Core\Geolite;

use Igniter\Flame\Geolite\Contracts\GeoQueryInterface;
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
    #[Override]
    public function placesAutocomplete(GeoQueryInterface $query): Collection
    {
        return parent::placesAutocomplete($query)->map(function($place) {
            if ($place->getTitle() === '' || $place->getTitle() === null) {
                $place->title($place->getDescription());
            }

            return $place;
        });
    }
}
