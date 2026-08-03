<?php

declare(strict_types=1);

namespace Iamnothardcoded\FoodLabels\Classes;

/**
 * The 14 mandatory allergen groups of Annex II, Regulation (EU) No 1169/2011
 * (LMIV), with the customary German letter codes A-N embedded in the name.
 *
 * These ship as seeded rows in TastyIgniter's native `ingredients` table
 * (is_allergen = 1) so operators attach them through the stock Ingredients
 * relation field on the menu form — no parallel allergen concept. The seed
 * matches by exact name; renaming a seeded row makes a future re-seed
 * recreate the canonical one (harmless duplicate, see README).
 */
class Allergens
{
    public const SEED = [
        ['code' => 'A', 'name' => 'Glutenhaltiges Getreide (A)'],
        ['code' => 'B', 'name' => 'Krebstiere (B)'],
        ['code' => 'C', 'name' => 'Eier (C)'],
        ['code' => 'D', 'name' => 'Fisch (D)'],
        ['code' => 'E', 'name' => 'Erdnüsse (E)'],
        ['code' => 'F', 'name' => 'Soja (F)'],
        ['code' => 'G', 'name' => 'Milch, einschl. Laktose (G)'],
        ['code' => 'H', 'name' => 'Schalenfrüchte/Nüsse (H)'],
        ['code' => 'I', 'name' => 'Sellerie (I)'],
        ['code' => 'J', 'name' => 'Senf (J)'],
        ['code' => 'K', 'name' => 'Sesam (K)'],
        ['code' => 'L', 'name' => 'Schwefeldioxid und Sulfite (L)'],
        ['code' => 'M', 'name' => 'Lupinen (M)'],
        ['code' => 'N', 'name' => 'Weichtiere (N)'],
    ];

    /** @return string[] the 14 canonical row names */
    public static function names(): array
    {
        return array_column(self::SEED, 'name');
    }
}
