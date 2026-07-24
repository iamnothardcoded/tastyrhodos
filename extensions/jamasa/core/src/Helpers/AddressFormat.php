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
}
