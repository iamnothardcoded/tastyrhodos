<?php

return [
    // Admin — menu form, "Food labelling" tab
    'text_tab' => 'Food labelling',
    'label_diet' => 'Dietary markers',
    'help_diet' => 'Small badges on the menu. "Vegan" automatically shows only the vegan badge (implies vegetarian).',
    'label_additives' => 'Additives (German § 9 ZZulV)',
    'help_additives' => 'Statutory disclosure formulae — shown in the dish info dialog. Allergens are assigned via the stock Ingredients field (General tab): the 14 EU allergens (A–N) come pre-seeded.',
    'label_status' => 'Information status',
    'help_status' => '"No data": guests see a notice that allergen information is not yet available — assigned allergens/additives are NOT shown. "Provided": transcribed from the restaurant\'s records, not yet verified — shown. "Confirmed": verified by the restaurant — shown. Empty data under "Provided/Confirmed" means: nothing declarable.',

    'status_unknown' => 'No data',
    'status_declared' => 'Provided (not verified)',
    'status_confirmed' => 'Confirmed by restaurant',

    // Diet badges
    'diet_veg' => 'Vegetarian',
    'diet_vegan' => 'Vegan',
    'diet_hot' => 'Spicy',

    // German statutory additive formulae (§ 9 ZZulV — keep German wording,
    // the disclosure text itself is prescribed by German law)
    'additive_farbstoff' => 'mit Farbstoff (with colouring)',
    'additive_konservierungsstoff' => 'mit Konservierungsstoff (with preservative)',
    'additive_antioxidationsmittel' => 'mit Antioxidationsmittel (with antioxidant)',
    'additive_geschmacksverstaerker' => 'mit Geschmacksverstärker (with flavour enhancer)',
    'additive_geschwefelt' => 'geschwefelt (sulphurated)',
    'additive_geschwaerzt' => 'geschwärzt (blackened)',
    'additive_gewachst' => 'gewachst (waxed)',
    'additive_phosphat' => 'mit Phosphat (with phosphate)',
    'additive_suessungsmittel' => 'mit Süßungsmittel(n) (with sweetener)',
    'additive_phenylalanin' => 'enthält eine Phenylalaninquelle (contains a source of phenylalanine)',
    'additive_koffein' => 'koffeinhaltig (contains caffeine)',
    'additive_chinin' => 'chininhaltig (contains quinine)',

    // Storefront dialog
    'text_dialog_title' => 'Allergens & additives',
    'text_dialog_allergens' => 'Allergens',
    'text_dialog_additives' => 'Additives',
    'text_dialog_unknown' => 'Allergen information for this dish is not yet available — please ask us:',
    'text_status_declared' => 'Not verified',
    'text_status_confirmed' => 'Confirmed by restaurant',
    'text_dialog_none' => 'No declarable allergens or additives.',
    'text_dialog_note' => 'Information per EU Regulation 1169/2011 (LMIV). If you have allergies, please check with the restaurant before ordering.',
];
