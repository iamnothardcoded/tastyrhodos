{{-- jamasa/core override of igniter-orange::components.category-list (forked from ti-theme-orange v4.1.3) --}}
{{-- Change vs stock: the "All categories" tab is dropped — it anchored to the
     uncategorized-items section (#category-all-heading) which our menus don't
     have, and its filter-reset role only matters in non-anchor navigation.
     Contracts preserved: ul#navbar-categories + .nav-link (ScrollSpy target,
     rail JS), anchor hrefs, wire:navigate variant, hideEmpty continue. --}}
<div class="layout-scrollable w-100">
    <ul id="navbar-categories" class="nav nav-pills nav-inline flex-nowrap py-3 w-100">
        @foreach ($categories->toFlatTree() as $category)
            @continue($hideEmpty && $category->menus_count < 1)

            <li class="nav-item">
                <a
                    @class(['nav-link rounded py-1', 'active' => ($selectedCategory && $category->permalink_slug == $selectedCategory->permalink_slug)])
                    @if($useLinkAnchor)
                        href="#category-{{ strtolower(str_slug($category->name)) }}-heading"
                    @else
                        href="{{ page_url($menusPage, ['category' => $category->permalink_slug]) }}"
                        wire:navigate
                    @endif
                >{{ $category->name }}</a>
            </li>
        @endforeach
    </ul>
</div>
