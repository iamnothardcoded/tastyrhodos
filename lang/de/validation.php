<?php

/*
 * German validation overrides (famedo). Minimal on purpose: Laravel falls back
 * per-key to lang/en/validation.php for anything not defined here.
 *
 * The `custom.fields.email.unique` entry targets ONLY the checkout email field:
 * ti-ext-cart's CheckoutForm prefixes every rule key with `fields.` (so the
 * attribute is `fields.email`), which no other form uses. A GUEST whose email
 * already belongs to an account fails the `unique:customers,email` rule — this
 * turns the bare "already taken" dead-end into a signpost to log in.
 */

return [
    'custom' => [
        'fields' => [
            'email' => [
                'unique' => 'Diese E-Mail-Adresse gehört bereits zu einem Konto. Bitte melde dich an, um sie zu verwenden.',
            ],
        ],
    ],
];
