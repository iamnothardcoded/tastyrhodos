{{-- jamasa/core override of igniter-orange::includes.menu.grouped (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo category sections: colored left-border headers (9-color cycle via
     data-accent) + item count. ScrollSpy attrs, heading/collapse ids and the
     collapse toggle contract preserved verbatim (menu-item-list JS depends on
     them). root-margin top tuned to the famedo sticky tab bar height. --}}
<div
    class="menu-group"
    data-bs-spy="scroll"
    data-bs-target="#navbar-categories"
    data-bs-root-margin="60px 0px -80%"
    data-bs-threshold="[1]"
    data-bs-smooth-scroll="true"
>
    @forelse ($groupedMenuItems as $categoryId => $menuList)
        <div @class(['menu-group-item cat']) @if($categoryId > 0) data-accent="{{ ($loop->index) % 9 }}" @endif>
            @if ($categoryId > 0)
                @php
                    $menuCategory = array_get($menuListCategories, $categoryId);
                    $menuCategoryAlias = strtolower(str_slug($menuCategory->name));
                @endphp
                <div id="category-{{ $menuCategoryAlias }}-heading" class="category-header" role="tab">
                    <div
                        @class(['cat__head menu-group-toggle', 'collapsed' => $loop->iteration >= $collapseCategoriesAfter])
                        data-bs-toggle="collapse"
                        data-bs-target="#category-{{ $menuCategoryAlias }}-collapse"
                        aria-expanded="false"
                        aria-controls="category-{{ $menuCategoryAlias }}-heading"
                    >
                        <div class="cat__head-row">
                            <h2>{{ $menuCategory->name }}</h2>
                            <span class="cat__count">{{ count($menuList) }}</span>
                        </div>
                        @if (strlen($menuCategory->description))
                            <p class="cat__tagline">{!! nl2br($menuCategory->description) !!}</p>
                        @endif
                    </div>
                </div>
                <div
                    id="category-{{ $menuCategoryAlias }}-collapse"
                    class="category-items collapse {{ $loop->iteration < $collapseCategoriesAfter ? 'show' : '' }}"
                    role="tabpanel" aria-labelledby="{{ $menuCategoryAlias }}"
                >
                    @include('igniter-orange::includes.menu.items', ['menuItems' => $menuList])
                </div>
            @else
                <div id="category-all-heading">
                    @include('igniter-orange::includes.menu.items', ['menuItems' => $menuList])
                </div>
            @endif
        </div>
    @empty
        <div class="menu-group-item">
            <p class="px-3">@lang('igniter.local::default.text_no_category')</p>
        </div>
    @endforelse
</div>
