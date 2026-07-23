<?php

return [
    'text_settings' => 'Steuerklassen',
    'help_settings' => 'MwSt.-Sätze (ermäßigt/voll), Standardklasse und Steuerklasse der Liefergebühr festlegen.',

    'text_tax_reduced' => 'MwSt. ermäßigter Satz',
    'help_tax_reduced' => 'Enthaltene MwSt. auf Artikel der ermäßigten Klasse (z. B. Speisen mit 7 %).',
    'text_tax_standard' => 'MwSt. voller Satz',
    'help_tax_standard' => 'Enthaltene MwSt. auf Artikel der vollen Klasse (z. B. Getränke mit 19 %).',

    // sprintf: %s = Satz ("7", "19")
    'text_incl_vat' => 'inkl. %s%% MwSt.',

    'label_tax_class' => 'Steuerklasse',
    'help_tax_class_menu' => '„Erben“ übernimmt die Steuerklasse der Kategorie (sonst die Standardklasse).',
    'help_tax_class_category' => 'Artikel ohne eigene Steuerklasse erben diese Klasse.',

    'text_class_inherit' => 'Erben (Kategorie / Voreinstellung)',
    'text_class_default' => 'Voreinstellung',
    'text_class_reduced' => 'Ermäßigt (z. B. 7 %)',
    'text_class_standard' => 'Voll (z. B. 19 %)',

    'label_reduced_rate' => 'Ermäßigter Satz (%)',
    'help_reduced_rate' => 'MwSt.-Prozentsatz der ermäßigten Klasse. Deutschland: 7.',
    'label_standard_rate' => 'Voller Satz (%)',
    'help_standard_rate' => 'MwSt.-Prozentsatz der vollen Klasse. Deutschland: 19.',
    'label_default_class' => 'Standard-Steuerklasse',
    'help_default_class' => 'Gilt für Artikel ohne eigene Klasse und ohne klassifizierte Kategorie.',
    'label_delivery_charge_class' => 'Steuerklasse der Liefergebühr',
    'help_delivery_charge_class' => 'In welcher Klasse die Liefergebühr versteuert wird. Steuerberater fragen; üblich ist der volle Satz.',
];
