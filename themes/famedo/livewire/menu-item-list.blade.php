{{-- jamasa/core override of igniter-orange::livewire.menu-item-list (forked from ti-theme-orange v4.1.3) --}}
{{-- Markup unchanged. Only the tab-rail JS differs: instead of the vendor's
     debounced scrollIntoView (only fired when the active tab left the view →
     felt laggy), the rail continuously centers the active tab CLAMPED to
     [0, maxScroll]. Result: rail pinned left while the active tab is in the
     left half, slides to keep it centered through the middle, sticks at the
     right end while the active bubble travels on. --}}
<div>
    @unless($hideMenuSearch)
        <div class="menu-search pb-4">
            @include('igniter-orange::includes.menu.search')
        </div>
    @endunless

    <div class="menu-list">
        @if (!$selectedCategorySlug && $isGrouped)
            @include('igniter-orange::includes.menu.grouped', ['groupedMenuItems' => $menuList])
        @else
            @include('igniter-orange::includes.menu.items', ['menuItems' => $menuList])
        @endif

        @if($itemsPerPage > 0)
            <div class="pagination-bar text-right">
                <div class="links">{{ $menuList->links('igniter-orange::pagination.simple_default') }}</div>
            </div>
        @endif
    </div>

    @if($selectedMenuId)
        <button
            x-data="OrangeShowSelectedMenuItemModal()"
            x-ref="selectedMenuItemTrigger"
            type="button"
            class="d-none"
            data-toggle="orange-modal"
            data-component="igniter-orange::cart-item-modal"
            data-arguments='{"menuId": {{ $selectedMenuId }}}'
        ></button>
    @endif
</div>

@script
<script>
    document.addEventListener('livewire:initialized', () => {
        document.querySelectorAll('[data-control="menu-item"]').forEach((el) => {
            el.addEventListener('click', (event) => {
                if (el.classList.contains('disabled')) {
                    event.preventDefault();
                    return;
                } else {
                    el.classList.add('disabled');
                }

                el.querySelectorAll('i').forEach((icon) => {
                    if (icon.hasAttribute('wire:loading.class')) {
                        icon.classList.add('fa-spinner', 'fa-spin');
                    }
                });
            });
        });
    })
    Livewire.hook('commit', ({respond}) => {
        respond(() => {
            document.querySelectorAll('[data-control="menu-item"]').forEach((el) => {
                el.classList.remove('disabled');
                el.querySelectorAll('i.fa-spin').forEach((icon) => {
                    if (icon.hasAttribute('wire:loading.class')) {
                        icon.classList.remove('fa-spinner', 'fa-spin');
                    }
                })
            });
        })
    });
    $(function () {
        // Famedo tab rail: keep the active tab centered, clamped to the rail's
        // scroll range. Left section => rail pinned at 0 (bubble travels);
        // middle => rail slides, bubble visually centered; right end => rail
        // sticks at maxScroll, bubble travels to the far right.
        function famedoPositionRail(activeEl, smooth) {
            const nav = document.getElementById('navbar-categories');
            if (!nav || !activeEl) return;
            const item = activeEl.closest('.nav-item') || activeEl;
            const navRect = nav.getBoundingClientRect();
            const itemRect = item.getBoundingClientRect();
            const itemCenter = itemRect.left - navRect.left + nav.scrollLeft + itemRect.width / 2;
            const target = Math.max(0, Math.min(
                itemCenter - nav.clientWidth / 2,
                nav.scrollWidth - nav.clientWidth
            ));
            if (Math.abs(nav.scrollLeft - target) < 2) return;
            nav.scrollTo({left: target, behavior: smooth ? 'smooth' : 'auto'});
        }

        let lastActive = null;
        $(document)
            .on('activate.bs.scrollspy', function (e) {
                lastActive = $(e.relatedTarget);
                famedoPositionRail(e.relatedTarget, true);
            })
            .on('scroll', () => {
                // Keep last active nav-link highlighted when no section is in view
                if (!$('#navbar-categories .nav-link.active').length && lastActive) {
                    lastActive.addClass('active');
                }
            });

        // Initial position (e.g. page loaded mid-scroll / anchor deep link)
        setTimeout(() => {
            famedoPositionRail(document.querySelector('#navbar-categories .nav-link.active'), false);
        }, 300);

        // Smooth scroll to anchor links
        $('a[href*="#"]:not([href="#"])').click(function (event) {
            if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
                var $target = $(this.hash);
                $target = $target.length ? $target : $('[name='+this.hash.slice(1)+']');
                if ($target.length) {
                    event.preventDefault()
                    var offset = $('.sticky-top').outerHeight() || 0;
                    $('html, body').animate({
                        scrollTop: $target.offset().top-offset
                    }, 500, function () {
                        $('[data-bs-toggle="collapse"]:is(.collapsed)', $target).trigger('click');
                        var spy = bootstrap.ScrollSpy.getInstance($target.closest('[data-bs-spy="scroll"]'));
                        if (spy) spy.refresh();
                    });
                }

                return false;
            }
        });
    });
</script>
@endscript
