<?php

return [
    'text_settings' => 'Rabatte',
    'help_settings' => 'Automatische Rabatte für registrierte Kunden: Neukundenrabatt und zeitlich begrenzte Aktionen.',

    'text_signup_discount' => 'Rabatt',
    'help_signup_discount' => 'Automatischer Rabatt für angemeldete Kunden (Einstellungen → Rabatte).',

    'text_tab_new' => 'Neukundenrabatt',
    'text_tab_all' => 'Aktion für Registrierte',

    'help_new' => 'Automatischer Rabatt für Neukunden — Konten ohne abgeschlossene Bestellung. Frühere Gast-Bestellungen zählen bewusst NICHT (das Konto anlegen ist ja das Ziel). Gilt nur für angemeldete Kunden; Gäste sehen keinen Rabatt.',
    'help_all' => 'Zeitlich begrenzter Rabatt für ALLE angemeldeten Kunden (immer mit Enddatum — ein Dauerrabatt für alle wäre eine dauerhafte Preissenkung). Treffen Neukundenrabatt und Aktion gleichzeitig zu, gewinnt der höhere Rabatt — niemals beide zusammen.',

    'label_enabled' => 'Aktiv',

    'label_label' => 'Anzeigename',
    'help_label' => 'So heißt die Rabattzeile für den Kunden.',

    'label_type' => 'Rabattart',
    'text_type_percent' => 'Prozent (%)',
    'text_type_fixed' => 'Fester Betrag (€)',

    'label_amount' => 'Rabatt',
    'help_amount' => 'Prozentsatz oder Euro-Betrag, je nach Rabattart. Nur auf die Artikel berechnet — Liefergebühr und Trinkgeld werden nie rabattiert.',

    'label_mode' => 'Was ist rabattiert?',
    'help_mode' => 'Beispiel: 10 % nur auf die allererste Bestellung — oder 30 Tage lang auf jede Bestellung.',
    'text_mode_first_only' => 'Nur die 1. Bestellung',
    'text_mode_window' => 'Alle Bestellungen in den ersten X Tagen',

    'label_window_days' => 'Anzahl Tage (X)',
    'help_window_days' => 'Das Zeitfenster startet mit der 1. Bestellung — sie ist selbst rabattiert. Beispiel: 30 = jede Bestellung in den ersten 30 Tagen.',

    'label_date_from' => 'Von',
    'help_date_from' => 'Ab wann die Aktion gilt. Leer = ab sofort.',
    'label_date_to' => 'Bis (Ende der Aktion)',
    'help_date_to' => 'Pflicht: Ohne Enddatum läuft die Aktion NICHT. So kann ein Dauerrabatt für alle nie versehentlich für immer laufen.',

    'label_min_total' => 'Mindestbestellwert (€)',
    'help_min_total' => '0 = kein Minimum. Geprüft gegen die Artikelsumme.',

    // Storefront-Teaser (Gäste)
    'teaser_first' => ':amount Rabatt auf deine erste Bestellung',
    'teaser_window' => ':amount Rabatt in den ersten :days Tagen',
    'teaser_sub' => 'Jetzt registrieren – wird an der Kasse automatisch abgezogen.',
    'teaser_cart' => 'Mit Konto sparst du :amount',
    'teaser_cart_sub' => 'Kostenlos registrieren und Rabatt sichern.',
    'teaser_success' => 'Nächstes Mal :amount sparen',
    'teaser_success_sub' => 'Konto anlegen dauert 30 Sekunden – deine Daten sind schon da.',
    'teaser_success_done' => 'Konto erstellt! Beim nächsten Mal sparst du :amount.',

    // Aktiv-Banner für angemeldete Kunden
    'default_label_new' => 'Willkommensrabatt',
    'default_label_all' => 'Aktionsrabatt',
    'active_sub_first' => 'Auf deine erste Bestellung – wird automatisch abgezogen.',
    'active_sub_window' => 'In den ersten :days Tagen ab deiner 1. Bestellung – automatisch abgezogen.',
    'active_sub_days' => 'Noch :days Tage gültig – wird automatisch abgezogen.',
    'active_sub_days_one' => 'Noch 1 Tag gültig – wird automatisch abgezogen.',
    'active_sub_promo' => 'Für dich aktiv – wird automatisch abgezogen.',
    'active_sub_promo_days' => 'Für dich aktiv · noch :days Tage – wird automatisch abgezogen.',
    'active_sub_promo_days_one' => 'Für dich aktiv · nur noch heute – wird automatisch abgezogen.',
];
