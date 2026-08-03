<?php

return [
    // Admin — Menü-Formular, Tab „Kennzeichnung"
    'text_tab' => 'Kennzeichnung',
    'label_diet' => 'Ernährungs-Kennzeichen',
    'help_diet' => 'Kleine Symbole in der Speisekarte. „Vegan" zeigt automatisch nur das Vegan-Symbol (schließt vegetarisch ein).',
    'label_additives' => 'Zusatzstoffe (§ 9 ZZulV)',
    'help_additives' => 'Gesetzliche Pflichtformeln — erscheinen im Info-Dialog des Gerichts. Allergene werden über das Feld „Zutaten/Ingredients" (Tab Allgemein) zugeordnet: die 14 LMIV-Allergene (A–N) sind dort vorangelegt.',
    'label_status' => 'Status der Angaben',
    'help_status' => '„Keine Angaben": Gäste sehen einen Hinweis, dass noch keine Allergenangaben vorliegen — zugeordnete Allergene/Zusatzstoffe werden NICHT angezeigt. „Übernommen": Angaben aus den Unterlagen des Restaurants übertragen, noch nicht geprüft — wird angezeigt. „Bestätigt": vom Restaurant geprüft — wird angezeigt. Leere Angaben bei „Übernommen/Bestätigt" bedeuten: keine deklarationspflichtigen Stoffe.',

    'status_unknown' => 'Keine Angaben',
    'status_declared' => 'Übernommen (nicht verifiziert)',
    'status_confirmed' => 'Vom Restaurant bestätigt',

    // Diet-Badges
    'diet_veg' => 'Vegetarisch',
    'diet_vegan' => 'Vegan',
    'diet_hot' => 'Scharf',

    // Zusatzstoff-Pflichtformeln (§ 9 ZZulV — Wortlaut nicht verändern)
    'additive_farbstoff' => 'mit Farbstoff',
    'additive_konservierungsstoff' => 'mit Konservierungsstoff',
    'additive_antioxidationsmittel' => 'mit Antioxidationsmittel',
    'additive_geschmacksverstaerker' => 'mit Geschmacksverstärker',
    'additive_geschwefelt' => 'geschwefelt',
    'additive_geschwaerzt' => 'geschwärzt',
    'additive_gewachst' => 'gewachst',
    'additive_phosphat' => 'mit Phosphat',
    'additive_suessungsmittel' => 'mit Süßungsmittel(n)',
    'additive_phenylalanin' => 'enthält eine Phenylalaninquelle',
    'additive_koffein' => 'koffeinhaltig',
    'additive_chinin' => 'chininhaltig',

    // Storefront-Dialog
    'text_dialog_title' => 'Allergene & Zusatzstoffe',
    'text_dialog_allergens' => 'Allergene',
    'text_dialog_additives' => 'Zusatzstoffe',
    'text_dialog_unknown' => 'Allergenangaben liegen für dieses Gericht noch nicht vor — bitte fragen Sie uns:',
    'text_status_declared' => 'Angaben nicht verifiziert',
    'text_status_confirmed' => 'Vom Restaurant bestätigt',
    'text_dialog_none' => 'Keine deklarationspflichtigen Allergene oder Zusatzstoffe.',
    'text_dialog_note' => 'Angaben gem. LMIV. Bei Allergien bitte vor der Bestellung Rücksprache mit dem Restaurant halten.',
];
