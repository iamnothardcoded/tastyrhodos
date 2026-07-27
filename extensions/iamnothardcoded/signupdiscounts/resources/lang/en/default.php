<?php

return [
    'text_settings' => 'Discounts',
    'help_settings' => 'Automatic discounts for registered customers: welcome discount and time-limited promotions.',

    'text_signup_discount' => 'Discount',
    'help_signup_discount' => 'Automatic discount for logged-in customers (Settings → Discounts).',

    'text_tab_new' => 'Welcome discount',
    'text_tab_all' => 'Registered-customer promo',

    'help_new' => 'Automatic discount for new customers — accounts without a completed order. Earlier guest orders deliberately do NOT disqualify (getting the account created is the point). Logged-in customers only; guests see no discount.',
    'help_all' => 'Time-limited discount for ALL logged-in customers (always with an end date — a permanent all-customer discount would just be a price cut). When both the welcome discount and the promo match, the higher discount wins — never both together.',

    'label_enabled' => 'Enabled',

    'label_label' => 'Display name',
    'help_label' => 'The discount line shown to the customer.',

    'label_type' => 'Discount type',
    'text_type_percent' => 'Percentage (%)',
    'text_type_fixed' => 'Fixed amount',

    'label_amount' => 'Discount',
    'help_amount' => 'Percentage or amount, depending on the discount type. Computed on the items only — delivery fee and tip are never discounted.',

    'label_mode' => 'What gets discounted?',
    'help_mode' => 'Example: 10% on the very first order only — or on every order for 30 days.',
    'text_mode_first_only' => 'The first order only',
    'text_mode_window' => 'Every order within the first X days',

    'label_window_days' => 'Number of days (X)',
    'help_window_days' => 'The window starts with the first order — which is itself discounted. Example: 30 = every order within the first 30 days.',

    'label_date_from' => 'From',
    'help_date_from' => 'When the promo starts. Empty = active immediately.',
    'label_date_to' => 'To (promo end)',
    'help_date_to' => 'Required: without an end date the promo does NOT run. This keeps an all-registered discount from ever silently running forever.',

    'label_min_total' => 'Minimum order total',
    'help_min_total' => '0 = no minimum. Checked against the items subtotal.',

    // Storefront teasers (guests)
    'teaser_first' => ':amount off your first order',
    'teaser_window' => ':amount off for your first :days days',
    'teaser_sub' => 'Sign up now – applied automatically at checkout.',
    'teaser_cart' => 'Save :amount with an account',
    'teaser_cart_sub' => 'Sign up free and secure the discount.',
    'teaser_success' => 'Save :amount next time',
    'teaser_success_sub' => 'Creating an account takes 30 seconds – your details are already filled in.',
    'teaser_success_done' => 'Account created! You’ll save :amount next time.',

    // Active banner for logged-in customers
    'default_label_new' => 'Welcome discount',
    'default_label_all' => 'Promo discount',
    'active_sub_first' => 'On your first order – applied automatically at checkout.',
    'active_sub_window' => 'For your first :days days from your first order – applied automatically.',
    'active_sub_days' => ':days days left – applied automatically at checkout.',
    'active_sub_days_one' => '1 day left – applied automatically at checkout.',
    'active_sub_promo' => 'Active for you – applied automatically at checkout.',
    'active_sub_promo_days' => 'Active for you · :days days left – applied automatically.',
    'active_sub_promo_days_one' => 'Active for you · today only – applied automatically.',
];
