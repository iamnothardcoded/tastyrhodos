<?php

declare(strict_types=1);

namespace Iamnothardcoded\FoodLabels\Classes;

/**
 * The German additive disclosure formulae of § 9 ZZulV ("Kenntlichmachung":
 * mit Farbstoff, mit Konservierungsstoff, ...). The vocabulary is statutory,
 * hence constant — codes are stable identifiers stored on menus.additives
 * (JSON array); labels resolve through lang keys so other locales can
 * translate the display while the codes stay fixed.
 */
class Additives
{
    /** code => lang key suffix (label = statutory formula) */
    public const ALL = [
        'farbstoff' => 'additive_farbstoff',
        'konservierungsstoff' => 'additive_konservierungsstoff',
        'antioxidationsmittel' => 'additive_antioxidationsmittel',
        'geschmacksverstaerker' => 'additive_geschmacksverstaerker',
        'geschwefelt' => 'additive_geschwefelt',
        'geschwaerzt' => 'additive_geschwaerzt',
        'gewachst' => 'additive_gewachst',
        'phosphat' => 'additive_phosphat',
        'suessungsmittel' => 'additive_suessungsmittel',
        'phenylalanin' => 'additive_phenylalanin',
        'koffein' => 'additive_koffein',
        'chinin' => 'additive_chinin',
    ];

    /** @return string[] valid additive codes */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    /** @return array<string, string> code => translated formula (for checkboxlist) */
    public static function options(): array
    {
        $options = [];
        foreach (self::ALL as $code => $langKey) {
            $options[$code] = lang('iamnothardcoded.foodlabels::default.'.$langKey);
        }

        return $options;
    }

    /** @return string[] translated formulae for the given codes (unknown codes skipped) */
    public static function labelsFor(array $codes): array
    {
        $labels = [];
        foreach ($codes as $code) {
            if (isset(self::ALL[$code])) {
                $labels[] = lang('iamnothardcoded.foodlabels::default.'.self::ALL[$code]);
            }
        }

        return $labels;
    }
}
