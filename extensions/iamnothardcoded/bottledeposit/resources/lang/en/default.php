<?php

return [
    'text_settings' => 'Bottle Deposit',
    'help_settings' => 'Configure the deposit classes (Pfand / statiegeld / pant) and their amounts.',

    // Order-totals line + cart condition label
    'text_deposit' => 'Deposit',
    'help_deposit' => 'Charges the container deposit of the items in the cart as its own order line (deposit is never part of the displayed item price).',

    'label_deposit_class' => 'Bottle deposit',
    'help_deposit_class' => 'Deposit charged per unit on top of the price, as its own order line. Leave empty for items without a deposit.',
    'text_no_deposit' => 'No deposit',

    'label_classes' => 'Deposit classes',
    'help_classes' => 'Menu items reference a class by its code; the amount is charged per unit. Defaults are the German tiers (0.25 single-use, 0.15 reusable, 0.08 reusable beer bottle). Edit amounts freely — but keep the codes stable, they are stored on menu items.',
    'label_class_code' => 'Code',
    'help_class_code' => 'Stable identifier stored on menu items (e.g. einweg). Do not change after items reference it.',
    'label_class_label' => 'Label',
    'label_class_amount' => 'Amount per unit',

    // sprintf: %s = formatted amount — shown next to the item price (see README)
    'text_plus_deposit' => 'plus %s deposit',
];
