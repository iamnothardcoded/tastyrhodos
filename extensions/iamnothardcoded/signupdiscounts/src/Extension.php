<?php

declare(strict_types=1);

namespace Iamnothardcoded\SignupDiscounts;

use Iamnothardcoded\SignupDiscounts\CartConditions\SignupDiscount;
use Iamnothardcoded\SignupDiscounts\Models\SignupDiscountSettings;
use Igniter\System\Classes\BaseExtension;
use Override;

class Extension extends BaseExtension
{
    public function registerCartConditions(): array
    {
        return [
            SignupDiscount::class => [
                'name' => 'signup_discount',
                'label' => 'lang:iamnothardcoded.signupdiscounts::default.text_signup_discount',
                'description' => 'lang:iamnothardcoded.signupdiscounts::default.help_signup_discount',
            ],
        ];
    }

    #[Override]
    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'lang:iamnothardcoded.signupdiscounts::default.text_settings',
                'description' => 'lang:iamnothardcoded.signupdiscounts::default.help_settings',
                'icon' => 'fa fa-bullhorn',
                'model' => SignupDiscountSettings::class,
                'permissions' => ['Module.CartModule'],
            ],
        ];
    }
}
