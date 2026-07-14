{{-- jamasa/core override of igniter-orange::includes.menu.items (forked from ti-theme-orange v4.1.3) --}}
{{-- Single-column famedo item list (no grid). wire:key preserved per row. --}}
<div class="menu-items">
    @forelse ($menuItems as $menuItemData)
        <div wire:key="{{ $menuItemData->id }}">
            @include('igniter-orange::includes.menu.item')
        </div>
    @empty
        <p class="px-3">@lang('igniter.local::default.text_empty_menus')</p>
    @endforelse
</div>
