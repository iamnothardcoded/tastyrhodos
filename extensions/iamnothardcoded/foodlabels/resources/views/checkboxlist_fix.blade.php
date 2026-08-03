{{-- Repairs the checkboxlist "select all / select none" links on this form.
     UPSTREAM BUG (TI v4, verified 2026-08-03): field_checkboxlist.blade.php
     renders anchors with data-field-checkboxlist-all/-none for lists of >10
     options, but NO shipped JS binds those attributes (v3→v4 port casualty)
     — the links are dead on every v4 admin checkboxlist. Delegated handler,
     installed once per page; PR candidate for tastyigniter/core. --}}
<script>
    (function() {
        if (window.__foodlabelsCheckboxlistFix) return;
        window.__foodlabelsCheckboxlistFix = 1;
        document.addEventListener('click', function(e) {
            var link = e.target.closest && e.target.closest('[data-field-checkboxlist-all], [data-field-checkboxlist-none]');
            if (!link) return;
            e.preventDefault();
            var box = link.closest('.field-checkboxlist');
            if (!box) return;
            var checked = link.hasAttribute('data-field-checkboxlist-all');
            box.querySelectorAll('input[type="checkbox"]:not([disabled])').forEach(function(cb) {
                cb.checked = checked;
            });
        });
    })();
</script>
