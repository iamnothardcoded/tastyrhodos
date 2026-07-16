/* Famedo storefront shims. */

/* Pill toggle: tapping a segment switches the order type inline (optimistic
   thumb slide + Livewire set on the FulfillmentModal, whose updating() hook
   persists to the session immediately) and opens the fulfillment sheet for
   time/address details. The data-bs-toggle attribute on the same button
   already opens the sheet (no-JS fallback); double .show() is idempotent. */
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-famedo-ordertype]');
        if (!btn) return;

        var code = btn.dataset.famedoOrdertype;
        var toggle = btn.closest('.toggle');
        if (toggle) {
            toggle.classList.toggle('delivery', code === 'delivery');
            toggle.classList.toggle('pickup', code !== 'delivery');
            toggle.querySelectorAll('[data-famedo-ordertype]').forEach(function (b) {
                b.classList.toggle('on', b === btn);
            });
        }

        var modalEl = document.getElementById('fulfillmentModal');
        if (modalEl && window.Livewire) {
            var root = modalEl.closest('[wire\\:id]');
            var comp = root && Livewire.find(root.getAttribute('wire:id'));
            if (comp && comp.get('orderType') !== code) {
                comp.set('orderType', code);
            }
        }
        // Sheet opening is left to the button's data-bs-toggle fallback.
    });
})();

/* Swipe-down on a .sheet__grip closes its bottom sheet (modal or offcanvas).
   Tap-to-close is handled separately by Bootstrap via data-bs-dismiss — a
   swipe suppresses the browser click, so both gestures coexist cleanly. */
(function () {
    var startY = null;

    document.addEventListener('touchstart', function (e) {
        var grip = e.target.closest && e.target.closest('.sheet__grip');
        startY = grip ? e.touches[0].clientY : null;
    }, {passive: true});

    document.addEventListener('touchend', function (e) {
        if (startY === null) return;
        var dy = e.changedTouches[0].clientY - startY;
        startY = null;
        if (dy < 30 || !window.bootstrap) return; // require a real downward swipe

        var grip = e.target.closest && e.target.closest('.sheet__grip');
        if (!grip) return;

        var host = grip.closest('.modal');
        if (host) {
            (bootstrap.Modal.getInstance(host) || new bootstrap.Modal(host)).hide();
            return;
        }
        host = grip.closest('.offcanvas');
        if (host) {
            (bootstrap.Offcanvas.getInstance(host) || new bootstrap.Offcanvas(host)).hide();
        }
    }, {passive: true});
})();
