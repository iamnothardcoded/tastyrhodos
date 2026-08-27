<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

/**
 * German address-order normalization: TI's checkout composes address_1 as
 * "streetNumber streetName" ("2 Cottenburgstraße", US-style, from the vendor's
 * prepareDeliveryAddress) while Adressbuch-created rows are German
 * ("Cottenburgstraße 2"). One stored format everywhere: German.
 */
class AddressFormat
{
    /**
     * "2 Cottenburgstraße" → "Cottenburgstraße 2"; "2a Hugostraße" → "Hugostraße 2a".
     * MUST NOT touch: already-German rows (no leading number), numeral-starting
     * street names ("1. Maiweg" — the dot is the tell, a bare number has none),
     * and anything whose remainder doesn't start with a letter.
     */
    public static function germanize(?string $address1): ?string
    {
        if ($address1 === null) {
            return null;
        }

        if (preg_match('/^(\d+[a-zA-Z]?)\s+(\p{L}.*)$/u', trim($address1), $m)) {
            return trim($m[2]).' '.$m[1];
        }

        return $address1;
    }

    /**
     * THE one customer-facing address line: "Straße Nr, PLZ Stadt".
     *
     * Owner rule (2026-08-27 phone e2e): every surface reuses this ONE style —
     * it is the same composition the menu hero (local-header), the fulfillment
     * picker (famedo.js addrSync) and the printed Maps-QR already use. Never
     * invent another variant, never use core's format_address() for customer
     * addresses (its template renders "Stadt PLZ" + a trailing state line).
     *
     * Two traps this line dodges, both live-proven:
     *  - vendor prepareDeliveryAddress composes address_1 as "12 Musterstraße"
     *    (US order) → germanize() flips it; already-German input is untouched.
     *  - suggestion-picked positions carry the DISTRICT in `city` and the real
     *    city in `state` (Photon/Nominatim locality mapping — the district-in-
     *    city data trap). Prefer `state` when filled, exactly like the
     *    print-server's Maps-QR composition; manual/legacy rows have an empty
     *    `state` and fall back to `city`, which is the real city there.
     */
    public static function displayLine(?string $address1, ?string $city, ?string $state, ?string $postcode): string
    {
        $street = trim((string) self::germanize($address1));
        $town = trim(trim((string) $postcode).' '.(filled($state) ? trim((string) $state) : trim((string) $city)));

        return trim($street.($street !== '' && $town !== '' ? ', ' : '').$town, ', ');
    }
}
