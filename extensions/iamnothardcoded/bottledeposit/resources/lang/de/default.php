<?php

return [
    'text_settings' => 'Flaschenpfand',
    'help_settings' => 'Pfandklassen und ihre Beträge konfigurieren.',

    // Order-totals line + cart condition label
    'text_deposit' => 'Pfand',
    'help_deposit' => 'Berechnet das Pfand der Artikel im Warenkorb als eigene Bestellzeile (Pfand ist nie Teil des angezeigten Artikelpreises — PAngV §7).',

    'label_deposit_class' => 'Pfand',
    'help_deposit_class' => 'Pfand pro Stück, zusätzlich zum Preis als eigene Bestellzeile berechnet. Leer lassen für Artikel ohne Pfand.',
    'text_no_deposit' => 'Kein Pfand',

    'label_classes' => 'Pfandklassen',
    'help_classes' => 'Artikel verweisen über den Code auf eine Klasse; der Betrag wird pro Stück berechnet. Voreingestellt sind die deutschen Sätze (0,25 Einweg, 0,15 Mehrweg, 0,08 Mehrweg-Bier). Beträge frei änderbar — Codes stabil lassen, sie sind an Artikeln gespeichert.',
    'label_class_code' => 'Code',
    'help_class_code' => 'Stabiler Bezeichner, an Artikeln gespeichert (z. B. einweg). Nach Verwendung nicht mehr ändern.',
    'label_class_label' => 'Bezeichnung',
    'label_class_amount' => 'Betrag pro Stück',

    // sprintf: %s = formatierter Betrag — neben dem Artikelpreis (siehe README)
    'text_plus_deposit' => 'zzgl. %s Pfand',
];
