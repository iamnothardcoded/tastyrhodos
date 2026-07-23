<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses;

use Iamnothardcoded\TaxClasses\CartConditions\TaxReduced;
use Iamnothardcoded\TaxClasses\CartConditions\TaxStandard;
use Iamnothardcoded\TaxClasses\Listeners\AddsCategoryTaxClassField;
use Iamnothardcoded\TaxClasses\Listeners\AddsMenuTaxClassField;
use Iamnothardcoded\TaxClasses\Models\TaxClassSettings;
use Igniter\Cart\Http\Controllers\Categories;
use Igniter\Cart\Http\Controllers\Menus;
use Igniter\Cart\Http\Requests\CategoryRequest;
use Igniter\Cart\Http\Requests\MenuRequest;
use Igniter\Cart\Models\Category;
use Igniter\Cart\Models\Menu;
use Igniter\System\Classes\BaseExtension;
use Illuminate\Support\Facades\Event;
use Override;

class Extension extends BaseExtension
{
    #[Override]
    public function boot(): void
    {
        // Admin form fields (menu form is tabbed, category form is flat).
        Menus::extendFormFields(new AddsMenuTaxClassField);
        Categories::extendFormFields(new AddsCategoryTaxClassField);

        // FormController saves ONLY fields the request class has rules for —
        // without this, the posted tax_class is silently dropped.
        Event::listen('system.formRequest.extendValidator', function($request, $dataHolder): void {
            if ($request instanceof MenuRequest || $request instanceof CategoryRequest) {
                $dataHolder->rules['tax_class'] = ['nullable', 'string', 'in:reduced,standard'];
                $dataHolder->attributes['tax_class'] = lang('iamnothardcoded.taxclasses::default.label_tax_class');
            }
        });

        // The select's "inherit" option posts '' — store NULL so the column stays clean.
        Menu::extend(function(Menu $model): void {
            $model->bindEvent('model.beforeSave', function() use ($model): void {
                if ($model->tax_class === '') {
                    $model->tax_class = null;
                }
            });
        });
        Category::extend(function(Category $model): void {
            $model->bindEvent('model.beforeSave', function() use ($model): void {
                if ($model->tax_class === '') {
                    $model->tax_class = null;
                }
            });
        });
    }

    public function registerCartConditions(): array
    {
        return [
            TaxReduced::class => [
                'name' => 'tax_reduced',
                'label' => 'lang:iamnothardcoded.taxclasses::default.text_tax_reduced',
                'description' => 'lang:iamnothardcoded.taxclasses::default.help_tax_reduced',
            ],
            TaxStandard::class => [
                'name' => 'tax_standard',
                'label' => 'lang:iamnothardcoded.taxclasses::default.text_tax_standard',
                'description' => 'lang:iamnothardcoded.taxclasses::default.help_tax_standard',
            ],
        ];
    }

    #[Override]
    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'lang:iamnothardcoded.taxclasses::default.text_settings',
                'description' => 'lang:iamnothardcoded.taxclasses::default.help_settings',
                'icon' => 'fa fa-percent',
                'model' => TaxClassSettings::class,
                'permissions' => ['Module.CartModule'],
            ],
        ];
    }
}
