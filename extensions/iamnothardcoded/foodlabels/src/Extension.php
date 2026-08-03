<?php

declare(strict_types=1);

namespace Iamnothardcoded\FoodLabels;

use Iamnothardcoded\FoodLabels\Classes\Additives;
use Iamnothardcoded\FoodLabels\Classes\DietLabels;
use Iamnothardcoded\FoodLabels\Classes\InfoStatus;
use Iamnothardcoded\FoodLabels\Listeners\AddsMenuFoodFields;
use Igniter\Cart\Http\Controllers\Menus;
use Igniter\Cart\Http\Requests\MenuRequest;
use Igniter\Cart\Models\Menu;
use Igniter\System\Classes\BaseExtension;
use Illuminate\Support\Facades\Event;
use Override;

class Extension extends BaseExtension
{
    #[Override]
    public function boot(): void
    {
        Menus::extendFormFields(new AddsMenuFoodFields);

        // FormController saves ONLY fields the request class has rules for —
        // without this, the posted fields are silently dropped.
        // ⚠️ No 'array' rule on the checkboxlist parents: the core partial
        // renders a hidden value="" input, so all-unchecked posts the STRING
        // '' — an array rule would reject it. Element rules cover real input.
        Event::listen('system.formRequest.extendValidator', function($request, $dataHolder): void {
            if (!$request instanceof MenuRequest) {
                return;
            }

            $dataHolder->rules['diet_labels'] = ['nullable'];
            $dataHolder->rules['diet_labels.*'] = ['string', 'in:'.implode(',', DietLabels::codes())];
            $dataHolder->rules['additives'] = ['nullable'];
            $dataHolder->rules['additives.*'] = ['string', 'in:'.implode(',', Additives::codes())];
            $dataHolder->rules['food_info_status'] = ['nullable', 'string', 'in:'.implode(',', InfoStatus::codes())];

            $dataHolder->attributes['diet_labels'] = lang('iamnothardcoded.foodlabels::default.label_diet');
            $dataHolder->attributes['additives'] = lang('iamnothardcoded.foodlabels::default.label_additives');
            $dataHolder->attributes['food_info_status'] = lang('iamnothardcoded.foodlabels::default.label_status');
        });

        Menu::extend(function(Menu $model): void {
            $model->mergeCasts([
                'diet_labels' => 'array',
                'additives' => 'array',
            ]);

            $model->bindEvent('model.beforeSave', function() use ($model): void {
                // '' from the checkboxlist hidden input (all unchecked) -> NULL
                if ($model->diet_labels === '' || $model->diet_labels === []) {
                    $model->diet_labels = null;
                }

                if ($model->additives === '' || $model->additives === []) {
                    $model->additives = null;
                }

                if (!in_array($model->food_info_status, InfoStatus::codes(), true)) {
                    $model->food_info_status = InfoStatus::UNKNOWN;
                }
            });
        });
    }
}
