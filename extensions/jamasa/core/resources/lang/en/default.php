<?php

declare(strict_types=1);

/**
 * Famedo custom copy — English source of truth.
 * German lives in ../de/default.php; keep both files key-identical.
 * Consumed by jamasa PHP (OrderingState, MailLang) and famedo theme views.
 */
return [
    'pause' => [
        'title' => 'Short ordering break',
        'printer_message' => 'The kitchen is taking a short break – we\'ll be right back for you!',
        'busy_message' => 'We\'re very busy at the moment – please try again shortly!',
    ],

    'closed' => [
        'browse_menu' => 'Feel free to browse the menu in the meantime.',
        'opens_at' => 'We open again at %s – feel free to browse the menu until then.',
    ],

    // Closed/paused acknowledge-overlay (arrival modal; banners above stay).
    'overlay' => [
        'closed_title' => 'Currently closed',
        'closed_text' => 'We open again at %s – see you then!',
        'closed_text_notime' => 'Please check back later!',
        'closed_browse' => 'Browse the menu',
        'pause_browse' => 'View the menu',
        'auto_check' => 'This page checks automatically – as soon as we\'re back, you can order right away.',
        'resumed_toast' => 'Ordering is possible again!',
        'sheet_locked' => 'Ordering is currently not possible.',
    ],

    'cart' => [
        'amount_missing' => ':amount to go',
    ],

    'address' => [
        'street' => 'Street',
        'number' => 'No.',
        'postcode' => 'Postcode',
        'city' => 'City',
        'number_missing' => 'Please add your house number.',
        'required_for_delivery' => 'Please enter a delivery address before ordering.',
        'search_again' => 'Search again',
        'no_suggestions' => 'No suggestions found',
        'searching' => 'Searching addresses …',
        'checking' => 'Checking address …',
        'search_label' => 'Search address',
        'search_placeholder' => 'Enter street and house number …',
        'address2_label' => 'Address extra (optional)',
        'address2_placeholder' => 'e.g. floor, doorbell, company',
        'manual_entry' => 'Address not listed? Enter it manually',
        'zone_checking' => 'Checking delivery area …',
        'unverified' => 'Address could not be verified automatically',
        'unverified_more' => 'Please double-check your address for typos. You can still place your order as usual — the restaurant may give you a quick call to confirm.',
    ],

    'hero' => [
        'open_until' => 'until %s',
        'lead_time' => 'approx. %s min',
        'min_order' => 'Minimum order %s',
    ],

    'footer' => [
        'allergens_q' => 'Questions about allergens & additives (LMIV)?',
        'allergens_phone' => 'We\'re happy to help:',
        'allergens' => 'Allergens & additives according to LMIV — please ask us.',
        'prices_vat' => 'All prices incl. VAT.',
        'powered_by' => 'Ordering system by',
    ],

    'status' => [
        'received' => 'Received',
        'in_progress' => 'In progress',
        'ready' => 'Ready',
    ],

    'order' => [
        'none_found' => 'No order found',
    ],

    'ui' => [
        'loading' => 'Loading...',
        'close' => 'Close',
        'toggle_navigation' => 'Toggle navigation',
    ],

    'mail' => [
        'order' => [
            'subject' => ':site order confirmation – :code',
            'heading' => 'Thank you for your order!',
            'greeting' => 'Hi :name,',
            'text_received' => 'Your order has been received and will be with you shortly.',
            'html_received' => 'Your :type order **:code** has been received and will be with you shortly.',
            'text_view_url' => 'To view your order progress, use the URL below:',
            'link_progress' => '[Click here](:url) to view your order progress.',
            'text_order_number' => 'Your order number is :code',
            'text_order_type' => 'This is a :type order.',
            'label_order_date' => 'Order date:',
            'label_requested_time' => 'Requested time (:type):',
            'label_payment' => 'Payment method:',
            'label_restaurant' => 'Restaurant:',
            'label_delivery_address' => 'Delivery address:',
            'column_name' => 'Name/Description',
            'column_price' => 'Unit price',
            'column_subtotal' => 'Sub total',
        ],
        'order_update' => [
            'subject' => 'Your order update – :code',
            'heading' => 'Order update!',
            'greeting' => 'Hi :name,',
            'text_updated' => 'Your order :code has been updated to the following status:',
            'html_updated' => 'Your order **:code** has been updated to the following status:',
            'text_comments' => 'The comments for your order are:',
            'text_view_url' => 'To view your order progress, use the URL below:',
            'button_view' => 'View your order progress',
        ],
    ],
];
