<?php

/**
 * Model configuration options for the tax-classes settings model.
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
            'reduced_rate' => [
                'label' => 'lang:iamnothardcoded.taxclasses::default.label_reduced_rate',
                'comment' => 'lang:iamnothardcoded.taxclasses::default.help_reduced_rate',
                'type' => 'number',
                'span' => 'left',
                'default' => 7,
            ],
            'standard_rate' => [
                'label' => 'lang:iamnothardcoded.taxclasses::default.label_standard_rate',
                'comment' => 'lang:iamnothardcoded.taxclasses::default.help_standard_rate',
                'type' => 'number',
                'span' => 'right',
                'default' => 19,
            ],
            'default_class' => [
                'label' => 'lang:iamnothardcoded.taxclasses::default.label_default_class',
                'comment' => 'lang:iamnothardcoded.taxclasses::default.help_default_class',
                'type' => 'select',
                'span' => 'left',
                'default' => 'reduced',
                'options' => [
                    'reduced' => 'lang:iamnothardcoded.taxclasses::default.text_class_reduced',
                    'standard' => 'lang:iamnothardcoded.taxclasses::default.text_class_standard',
                ],
            ],
            'delivery_charge_class' => [
                'label' => 'lang:iamnothardcoded.taxclasses::default.label_delivery_charge_class',
                'comment' => 'lang:iamnothardcoded.taxclasses::default.help_delivery_charge_class',
                'type' => 'select',
                'span' => 'right',
                'default' => 'standard',
                'options' => [
                    'reduced' => 'lang:iamnothardcoded.taxclasses::default.text_class_reduced',
                    'standard' => 'lang:iamnothardcoded.taxclasses::default.text_class_standard',
                ],
            ],
        ],
    ],
];
