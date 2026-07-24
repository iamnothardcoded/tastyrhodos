<?php

declare(strict_types=1);

/**
 * Famedo custom copy — German (du-Form, famedo tone).
 * English source of truth in ../en/default.php; keep both files key-identical.
 */
return [
    'pause' => [
        'title' => 'Kurze Bestellpause',
        'printer_message' => 'Die Küche macht gerade eine kurze Pause – gleich wieder für dich da!',
        'busy_message' => 'Wir haben gerade sehr viel zu tun – bitte versuche es in Kürze noch einmal!',
    ],

    'closed' => [
        'browse_menu' => 'Stöber gern schon in der Karte.',
    ],

    'cart' => [
        'amount_missing' => ':amount fehlen noch',
    ],

    'address' => [
        'street' => 'Straße',
        'number' => 'Nr.',
        'postcode' => 'PLZ',
        'city' => 'Stadt',
        'number_missing' => 'Bitte gib noch deine Hausnummer an.',
        'search_again' => 'Neu suchen',
        'no_suggestions' => 'Keine Vorschläge gefunden',
        'searching' => 'Suche Adressen …',
        'search_label' => 'Adresse suchen',
        'search_placeholder' => 'Straße und Hausnummer eingeben …',
        'address2_label' => 'Adresszusatz (optional)',
        'address2_placeholder' => 'z. B. Etage, Klingel, Firma',
        'manual_entry' => 'Adresse nicht dabei? Manuell eingeben',
        'zone_checking' => 'Prüfe Liefergebiet …',
        'unverified' => 'Adresse konnte nicht automatisch überprüft werden',
        'unverified_more' => 'Bitte kontrolliere deine Adresse noch einmal auf Tippfehler. Deine Bestellung kannst du trotzdem ganz normal aufgeben — möglicherweise ruft dich das Restaurant zur Bestätigung kurz an.',
    ],

    'hero' => [
        'open_until' => 'bis %s',
        'lead_time' => 'ca. %s Min',
        'min_order' => 'Mindestbestellwert %s',
    ],

    'footer' => [
        'allergens_q' => 'Fragen zu Allergenen & Zusatzstoffen (LMIV)?',
        'allergens_phone' => 'Wir informieren dich gern:',
        'allergens' => 'Allergene & Zusatzstoffe gem. LMIV — bitte sprich uns an.',
        'prices_vat' => 'Alle Preise inkl. MwSt.',
        'powered_by' => 'Bestellsystem von',
    ],

    'status' => [
        'received' => 'Eingegangen',
        'in_progress' => 'In Arbeit',
        'ready' => 'Fertig',
    ],

    'order' => [
        'none_found' => 'Keine Bestellung gefunden',
    ],

    'ui' => [
        'loading' => 'Wird geladen …',
        'close' => 'Schließen',
        'toggle_navigation' => 'Navigation umschalten',
    ],

    'mail' => [
        'order' => [
            'subject' => ':site Bestellbestätigung – :code',
            'heading' => 'Danke für deine Bestellung!',
            'greeting' => 'Hallo :name,',
            'text_received' => 'Deine Bestellung ist bei uns eingegangen und wird zeitnah für dich vorbereitet.',
            'html_received' => 'Deine Bestellung **:code** (:type) ist bei uns eingegangen und wird zeitnah für dich vorbereitet.',
            'text_view_url' => 'Den Status deiner Bestellung kannst du hier verfolgen:',
            'link_progress' => '[Hier klicken](:url), um den Status deiner Bestellung zu verfolgen.',
            'text_order_number' => 'Deine Bestellnummer: :code',
            'text_order_type' => 'Bestellart: :type',
            'label_order_date' => 'Bestelldatum:',
            'label_requested_time' => 'Gewünschte Zeit (:type):',
            'label_payment' => 'Zahlungsart:',
            'label_restaurant' => 'Restaurant:',
            'label_delivery_address' => 'Lieferadresse:',
            'column_name' => 'Artikel',
            'column_price' => 'Einzelpreis',
            'column_subtotal' => 'Zwischensumme',
        ],
        'order_update' => [
            'subject' => 'Update zu deiner Bestellung – :code',
            'heading' => 'Update zu deiner Bestellung!',
            'greeting' => 'Hallo :name,',
            'text_updated' => 'Deine Bestellung :code hat jetzt den Status:',
            'html_updated' => 'Deine Bestellung **:code** hat jetzt den Status:',
            'text_comments' => 'Anmerkung zu deiner Bestellung:',
            'text_view_url' => 'Den Status deiner Bestellung kannst du hier verfolgen:',
            'button_view' => 'Bestellstatus ansehen',
        ],
    ],
];
