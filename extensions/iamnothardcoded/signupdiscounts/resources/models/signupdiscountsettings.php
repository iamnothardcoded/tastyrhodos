<?php

/**
 * Model configuration options for the signup-discounts settings model.
 *
 * Deliberately NOT a repeater: the admin repeater renders every item field as
 * one table column (one endless row), and show/hide triggers are unreliable
 * inside repeater items. Two fixed discount slots cover the real use cases
 * (welcome discount + storewide registered-customer promo) with a proper
 * two-column layout and working conditional fields.
 */

return [
    'form' => [
        'toolbar' => [
            'buttons' => [
                'save' => ['label' => 'lang:admin::lang.button_save', 'class' => 'btn btn-primary', 'data-request' => 'onSave'],
                'saveClose' => [
                    'label' => 'lang:admin::lang.button_save_close',
                    'class' => 'btn btn-default',
                    'data-request' => 'onSave',
                    'data-request-data' => 'close:1',
                ],
            ],
        ],
        'tabs' => [
            'fields' => [
                // ── Neukundenrabatt ─────────────────────────────────────────
                'new_enabled' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_new',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_enabled',
                    'commentAbove' => 'lang:iamnothardcoded.signupdiscounts::default.help_new',
                    'type' => 'switch',
                    'default' => false,
                ],
                'new_label' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_new',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_label',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_label',
                    'type' => 'text',
                    'span' => 'left',
                    'default' => 'Willkommensrabatt',
                ],
                'new_min_total' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_new',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_min_total',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_min_total',
                    'type' => 'number',
                    'span' => 'right',
                    'default' => 0,
                ],
                'new_type' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_new',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_type',
                    'type' => 'select',
                    'span' => 'left',
                    'default' => 'percent',
                    'options' => [
                        'percent' => 'lang:iamnothardcoded.signupdiscounts::default.text_type_percent',
                        'fixed' => 'lang:iamnothardcoded.signupdiscounts::default.text_type_fixed',
                    ],
                ],
                'new_amount' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_new',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_amount',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_amount',
                    'type' => 'number',
                    'span' => 'right',
                    'default' => 10,
                ],
                'new_mode' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_new',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_mode',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_mode',
                    'type' => 'select',
                    'span' => 'left',
                    'default' => 'first_order_only',
                    'options' => [
                        'first_order_only' => 'lang:iamnothardcoded.signupdiscounts::default.text_mode_first_only',
                        'window_days' => 'lang:iamnothardcoded.signupdiscounts::default.text_mode_window',
                    ],
                ],
                'new_window_days' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_new',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_window_days',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_window_days',
                    'type' => 'number',
                    'span' => 'right',
                    'default' => 30,
                    'trigger' => [
                        'action' => 'show',
                        'field' => 'new_mode',
                        'condition' => 'value[window_days]',
                    ],
                ],

                // ── Aktion für Registrierte ─────────────────────────────────
                'all_enabled' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_all',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_enabled',
                    'commentAbove' => 'lang:iamnothardcoded.signupdiscounts::default.help_all',
                    'type' => 'switch',
                    'default' => false,
                ],
                'all_label' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_all',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_label',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_label',
                    'type' => 'text',
                    'span' => 'left',
                    'default' => 'Aktionsrabatt',
                ],
                'all_min_total' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_all',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_min_total',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_min_total',
                    'type' => 'number',
                    'span' => 'right',
                    'default' => 0,
                ],
                'all_type' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_all',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_type',
                    'type' => 'select',
                    'span' => 'left',
                    'default' => 'percent',
                    'options' => [
                        'percent' => 'lang:iamnothardcoded.signupdiscounts::default.text_type_percent',
                        'fixed' => 'lang:iamnothardcoded.signupdiscounts::default.text_type_fixed',
                    ],
                ],
                'all_amount' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_all',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_amount',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_amount',
                    'type' => 'number',
                    'span' => 'right',
                    'default' => 10,
                ],
                'all_date_from' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_all',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_date_from',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_date_from',
                    'type' => 'datepicker',
                    'mode' => 'date',
                    'span' => 'left',
                ],
                'all_date_to' => [
                    'tab' => 'lang:iamnothardcoded.signupdiscounts::default.text_tab_all',
                    'label' => 'lang:iamnothardcoded.signupdiscounts::default.label_date_to',
                    'comment' => 'lang:iamnothardcoded.signupdiscounts::default.help_date_to',
                    'type' => 'datepicker',
                    'mode' => 'date',
                    'span' => 'right',
                ],
            ],
        ],
        // Reject malformed input at the source (runtime is also rescue-guarded).
        'rules' => [
            ['new_amount', 'lang:iamnothardcoded.signupdiscounts::default.label_amount', 'nullable|numeric|min:0'],
            ['new_min_total', 'lang:iamnothardcoded.signupdiscounts::default.label_min_total', 'nullable|numeric|min:0'],
            ['new_window_days', 'lang:iamnothardcoded.signupdiscounts::default.label_window_days', 'nullable|integer|min:1'],
            ['all_amount', 'lang:iamnothardcoded.signupdiscounts::default.label_amount', 'nullable|numeric|min:0'],
            ['all_min_total', 'lang:iamnothardcoded.signupdiscounts::default.label_min_total', 'nullable|numeric|min:0'],
            ['all_date_from', 'lang:iamnothardcoded.signupdiscounts::default.label_date_from', 'nullable|date'],
            ['all_date_to', 'lang:iamnothardcoded.signupdiscounts::default.label_date_to', 'nullable|date'],
        ],
    ],
];
