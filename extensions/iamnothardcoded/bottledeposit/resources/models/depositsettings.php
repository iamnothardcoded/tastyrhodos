<?php

/**
 * Model configuration options for the bottle-deposit settings model.
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
        'fields' => [
            'classes' => [
                'label' => 'lang:iamnothardcoded.bottledeposit::default.label_classes',
                'commentAbove' => 'lang:iamnothardcoded.bottledeposit::default.help_classes',
                'type' => 'repeater',
                'sortable' => false,
                'default' => [
                    ['code' => 'einweg', 'label' => 'Einwegpfand', 'amount' => 0.25],
                    ['code' => 'mehrweg', 'label' => 'Mehrwegpfand', 'amount' => 0.15],
                    ['code' => 'mehrweg_bier', 'label' => 'Mehrwegpfand (Bier)', 'amount' => 0.08],
                ],
                'form' => [
                    'fields' => [
                        'code' => [
                            'label' => 'lang:iamnothardcoded.bottledeposit::default.label_class_code',
                            'comment' => 'lang:iamnothardcoded.bottledeposit::default.help_class_code',
                            'type' => 'text',
                        ],
                        'label' => [
                            'label' => 'lang:iamnothardcoded.bottledeposit::default.label_class_label',
                            'type' => 'text',
                        ],
                        'amount' => [
                            'label' => 'lang:iamnothardcoded.bottledeposit::default.label_class_amount',
                            'type' => 'currency',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
