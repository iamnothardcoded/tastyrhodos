<?php

declare(strict_types=1);

namespace Iamnothardcoded\FoodLabels\Listeners;

use Iamnothardcoded\FoodLabels\Classes\Additives;
use Iamnothardcoded\FoodLabels\Classes\DietLabels;
use Iamnothardcoded\FoodLabels\Classes\InfoStatus;
use Igniter\Admin\Widgets\Form;
use Igniter\Cart\Models\Menu;

class AddsMenuFoodFields
{
    public function __invoke(Form $form): void
    {
        if (!$form->model instanceof Menu) {
            return;
        }

        $tab = 'lang:iamnothardcoded.foodlabels::default.text_tab';

        $form->addTabFields([
            'diet_labels' => [
                'tab' => $tab,
                'label' => 'lang:iamnothardcoded.foodlabels::default.label_diet',
                'comment' => 'lang:iamnothardcoded.foodlabels::default.help_diet',
                'type' => 'checkboxlist',
                'inlineMode' => true,
                'options' => DietLabels::options(),
                'span' => 'left',
            ],
            'additives' => [
                'tab' => $tab,
                'label' => 'lang:iamnothardcoded.foodlabels::default.label_additives',
                'comment' => 'lang:iamnothardcoded.foodlabels::default.help_additives',
                'type' => 'checkboxlist',
                'options' => Additives::options(),
                'span' => 'right',
            ],
            'food_info_status' => [
                'tab' => $tab,
                'label' => 'lang:iamnothardcoded.foodlabels::default.label_status',
                'comment' => 'lang:iamnothardcoded.foodlabels::default.help_status',
                'type' => 'select',
                'options' => InfoStatus::options(),
                'default' => InfoStatus::UNKNOWN,
                'span' => 'left',
            ],
            // renders only a <script>: repairs the dead select-all/none links
            // of the core checkboxlist widget (see the partial for details)
            '_foodlabels_checkboxlist_fix' => [
                'tab' => $tab,
                'type' => 'partial',
                'path' => 'iamnothardcoded.foodlabels::checkboxlist_fix',
            ],
        ]);
    }
}
