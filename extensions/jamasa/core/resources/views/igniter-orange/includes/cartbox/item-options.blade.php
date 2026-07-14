{{-- jamasa/core override of igniter-orange::includes.cartbox.item-options (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo option-group head: title + "Pflicht" chip for required groups +
     min/max summary. Contracts preserved verbatim: x-data OrangeCartItemOptions,
     data-control="item-option", data-option-type, wire:key, hidden
     wire:model.fill menu_option_id, hidden-item-options + more/less toggles. --}}
@foreach ($menuItemData->getOptions() as $index => $menuOption)
    <div
        x-data="OrangeCartItemOptions({{ $menuOption->min_selected }}, {{ $menuOption->max_selected }})"
        class="menu-option optgroup"
        data-control="item-option"
        data-option-type="{{ $menuOption->display_type }}"
        wire:key="option-{{ $index }}"
    >
        <div class="option option-{{ $menuOption->display_type }}">
            <div class="optgroup__head">
                <h4>{{ $menuOption->option_name }}</h4>
                @if ($menuOption->isRequired())
                    <span class="optgroup__req">@lang('igniter.cart::default.text_required')</span>
                @endif
            </div>
            @if ($menuOption->min_selected > 0 || $menuOption->max_selected > 0)
                <p class="optgroup__sum">{!! sprintf(lang('igniter.cart::default.text_option_summary'), $menuOption->min_selected, $menuOption->max_selected) !!}</p>
            @endif

            @if (count($optionValues = $menuOption->menu_option_values))
                <input
                    type="hidden"
                    wire:model.fill="menuOptions.{{ $index }}.menu_option_id"
                    value="{{ $menuOption->menu_option_id }}"
                />
                <div class="option-group">
                    @if(!$menuOption->option->isSelectDisplayType() && $limitOptionsValues && $optionValues->count() >= $limitOptionsValues)
                        @include('igniter-orange::includes.cartbox.item-options-'.$menuOption->display_type, [
                            'optionValues' => $optionValues->sortBy('priority')->slice(0, $limitOptionsValues),
                        ])

                        <div class="hidden-item-options" style="display: none;">
                            @include('igniter-orange::includes.cartbox.item-options-'.$menuOption->display_type, [
                                'optionValues' => $optionValues->sortBy('priority')->slice($limitOptionsValues),
                            ])
                        </div>
                        <button
                            type="button"
                            data-toggle="more-options"
                            class="btn btn-link"
                        >@lang('igniter.orange::default.button_show_more_options')</button>
                        <button
                            type="button"
                            data-toggle="less-options"
                            class="btn btn-link"
                            style="display: none;"
                        >@lang('igniter.orange::default.button_show_less_options')</button>
                    @else
                        @include('igniter-orange::includes.cartbox.item-options-'.$menuOption->display_type)
                    @endif
                </div>
            @endif
        </div>
    </div>
@endforeach
