<?php

declare(strict_types=1);

/**
 * German override for ti-theme-orange (namespace igniter.orange::).
 * Authored by us (famedo du-Form) — the community translation project has
 * 0% German coverage for the Orange theme (checked 2026-07-17).
 * Customer-facing keys only; admin-facing keys (component_, label_, help_)
 * stay English on purpose (admin locale is pinned to English).
 */
return [
    'title_separator' => ' - ',
    'home_title' => 'Startseite',
    'locations_title' => 'Standorte',
    'contact_title' => 'Kontakt',
    'cart_title' => 'Warenkorb',
    'reservation_title' => 'Tisch reservieren',
    'reservation_success_title' => 'Reservierungsbestätigung',
    'reviews_title' => 'Bewertungen',
    'menus_title' => 'Speisekarte',
    'checkout_title' => 'Kasse',
    'checkout_success_title' => 'Bestellbestätigung',
    'account_title' => 'Konto',
    'account_orders_title' => 'Bestellungen',
    'account_order_title' => 'Bestellung',
    'account_reservations_title' => 'Reservierungen',
    'account_reservation_title' => 'Reservierung',
    'account_address_title' => 'Adressbuch',
    'account_login_title' => 'Anmelden',
    'account_register_title' => 'Registrieren',
    'account_reset_title' => 'Passwort zurücksetzen',
    'account_socialite_title' => 'E-Mail-Adresse bestätigen',

    'text_restaurant' => 'Restaurant',
    'text_information' => 'Informationen',
    'text_control_title' => 'Wann und wohin möchtest du deine Bestellung?',
    'text_pick_time' => 'Zeit wählen',
    'text_delivering_to' => 'Lieferung an',
    'text_choose_address' => 'Wähle deine Adresse',
    'text_title_checkout' => 'Bestellung abschließen bei',
    'text_select_time' => 'Zeit auswählen',
    'text_signup_no_account' => 'Noch kein Konto?',
    'text_login_has_account' => 'Schon ein Konto?',
    'text_logged_in' => 'Schon ein Konto? <a href="%s">Hier anmelden</a>',
    'text_logged_out' => 'Willkommen zurück <b>%s</b>, nicht du? <a href="%s">Abmelden</a>',
    'text_reviews' => 'Bewertungen',
    'text_for_saved_addresses' => 'für deine gespeicherten Adressen',
    'text_select_saved_addresses' => 'Oder wähle eine gespeicherte Adresse',
    'text_no_saved_addresses' => 'Keine gespeicherten Adressen gefunden',
    'text_mark_your_location' => 'Markiere deinen Standort',
    'text_sort' => 'Sortieren',
    'text_customer_reviews' => 'Kundenbewertungen',
    'text_local_tab_info' => 'Info',
    'text_all' => 'Alle',
    'text_customer' => 'Kunde',
    'text_guest' => 'Gast',
    'text_login' => 'Anmelden',
    'text_no_delivery_address' => 'Keine Lieferadresse angegeben',
    'text_register' => 'Registrieren <small>Schnell und unkompliziert.</small>',
    'text_forgot' => 'Passwort vergessen?',
    'text_please_wait_info' => 'Dein Tisch wird reserviert. Bitte warten …',

    'menu_menu' => 'Speisekarte',
    'menu_reservation' => 'Reservierung',
    'menu_login' => 'Anmelden',
    'menu_logout' => 'Abmelden',
    'menu_register' => 'Registrieren',
    'menu_my_account' => 'Mein Konto',
    'menu_my_data' => 'Meine Daten', // famedo: the account-overview CHILD link (hub stays „Mein Konto")
    'menu_account' => 'Übersicht',
    'menu_detail' => 'Daten bearbeiten',
    'menu_address' => 'Adressbuch',
    'menu_recent_order' => 'Bestellungen',
    'menu_recent_reservation' => 'Reservierungen',
    'menu_locations' => 'Unsere Standorte',
    'menu_contact' => 'Kontakt',
    'menu_admin' => 'Administrator',

    'button_back' => 'Zurück',
    'button_confirm' => 'Bestätigen',
    'button_continue' => 'Weiter',
    'button_payment' => 'Weiter zur Zahlung',
    'button_more_reviews' => 'Mehr Bewertungen anzeigen',
    'button_view_cart' => 'Warenkorb ansehen',
    'button_register' => 'Registrieren',
    'button_show_more_options' => 'Mehr anzeigen',
    'button_show_less_options' => 'Weniger anzeigen',

    'alert_preview_mode' => 'Aktion im Vorschaumodus nicht erlaubt',
    'alert_saved_address_not_found' => 'Gewählte Adresse nicht gefunden',
    'alert_reservation_process_failed' => 'Deine Reservierung kann gerade nicht verarbeitet werden. Bitte versuche es gleich noch einmal.',

    'error_telephone_required' => 'Telefonnummer wird benötigt',
    'error_telephone_invalid' => 'Telefonnummer ist ungültig',

    'contact' => [
        'text_heading' => 'Kontakt',
        'text_summary' => 'Schreib uns gern eine Nachricht',
        'text_find_us' => 'Finde uns auf der Karte',
        'text_select_subject' => 'Betreff wählen',
        'text_contact_us' => 'Kontakt',
        'text_general_enquiry' => 'Allgemeine Anfrage',
        'text_comment' => 'Nachricht',
        'text_technical_issues' => 'Technisches Problem',

        'label_subject' => 'Betreff:',
        'label_full_name' => 'Vollständiger Name:',
        'label_email' => 'E-Mail-Adresse:',
        'label_telephone' => 'Telefon:',
        'label_comment' => 'Nachricht',

        'button_send' => 'SENDEN',

        'alert_contact_sent' => 'Nachricht gesendet – wir melden uns in Kürze bei dir!',
    ],

    'newsletter' => [
        'text_subscribe' => 'Abonniere unseren Newsletter',

        'label_email' => 'E-Mail',

        'alert_success_subscribed' => 'Danke! Deine E-Mail-Adresse ist jetzt für unsere Angebote angemeldet',
        'alert_success_existing' => 'Danke! Deine E-Mail-Adresse ist bereits angemeldet',
    ],
];
