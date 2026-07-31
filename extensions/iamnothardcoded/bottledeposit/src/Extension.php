<?php

declare(strict_types=1);

namespace Iamnothardcoded\BottleDeposit;

use Iamnothardcoded\BottleDeposit\CartConditions\BottleDeposit;
use Iamnothardcoded\BottleDeposit\Classes\DepositClasses;
use Iamnothardcoded\BottleDeposit\Listeners\AddsMenuDepositField;
use Iamnothardcoded\BottleDeposit\Listeners\ContributesTaxBase;
use Iamnothardcoded\BottleDeposit\Models\DepositSettings;
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
        Menus::extendFormFields(new AddsMenuDepositField);

        // FormController saves ONLY fields the request class has rules for —
        // without this, the posted deposit_class is silently dropped.
        Event::listen('system.formRequest.extendValidator', function($request, $dataHolder): void {
            if ($request instanceof MenuRequest) {
                $dataHolder->rules['deposit_class'] = [
                    'nullable', 'string', 'in:'.implode(',', DepositClasses::codes()),
                ];
                $dataHolder->attributes['deposit_class'] = lang('iamnothardcoded.bottledeposit::default.label_deposit_class');
            }
        });

        // The select's "no deposit" option posts '' — store NULL so the column stays clean.
        Menu::extend(function(Menu $model): void {
            $model->bindEvent('model.beforeSave', function() use ($model): void {
                if ($model->deposit_class === '') {
                    $model->deposit_class = null;
                }
            });
        });

        // Tax Classes interop (loose coupling): when that extension computes its
        // per-class VAT bases it collects extra amounts via this event — the
        // charged deposits belong in the carrying drink's class. Without Tax
        // Classes the event never fires and this listener is inert.
        Event::listen('iamnothardcoded.taxclasses.collectExtraBases', ContributesTaxBase::class);
    }

    public function registerCartConditions(): array
    {
        return [
            BottleDeposit::class => [
                'name' => 'bottle_deposit',
                'label' => 'lang:iamnothardcoded.bottledeposit::default.text_deposit',
                'description' => 'lang:iamnothardcoded.bottledeposit::default.help_deposit',
            ],
        ];
    }

    #[Override]
    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'lang:iamnothardcoded.bottledeposit::default.text_settings',
                'description' => 'lang:iamnothardcoded.bottledeposit::default.help_settings',
                'icon' => 'fa fa-recycle',
                'model' => DepositSettings::class,
                'permissions' => ['Module.CartModule'],
            ],
        ];
    }
}
