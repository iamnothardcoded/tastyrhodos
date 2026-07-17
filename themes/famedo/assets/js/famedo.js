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

/* Add-to-cart microinteractions (W1b cartbar bump + A3 check-morph, picked from
   the microinteractions demos 2026-07-18). Orange's CartBox dispatches no
   completion event, so we watch the cartbar count via MutationObserver: count
   increased → the Livewire round trip succeeded and the bar is freshly
   rendered → bump it, and morph the last-tapped simple-item + into a ✓.
   Option items add via the sheet (no row button tapped) → only the bar bumps.
   A failed add (paused/closed toast) never increments → no false ✓.
   prefers-reduced-motion is honored in CSS. */
(function () {
    var lastCount = null;
    var lastAddBtn = null;

    document.addEventListener('click', function (e) {
        var row = e.target.closest && e.target.closest('[data-control="menu-item"]');
        if (!row) return;
        lastAddBtn = row.querySelector('.addbtn');
        if (lastAddBtn) {
            // + spins while the add is in flight; cleared on success below,
            // or by this fallback when the add fails (pause/closed toast)
            var btn = lastAddBtn;
            btn.classList.add('famedo-loading');
            setTimeout(function () { btn.classList.remove('famedo-loading'); }, 5000);
        }
    });

    function readCount() {
        var el = document.querySelector('.cartbar__count');
        return el ? parseInt(el.textContent, 10) : null;
    }

    function replay(el, cls, ms) {
        el.classList.remove(cls);
        void el.offsetWidth; // restart the animation on rapid re-adds
        el.classList.add(cls);
        setTimeout(function () { el.classList.remove(cls); }, ms);
    }

    new MutationObserver(function () {
        var n = readCount();
        if (n !== null && lastCount !== null && n > lastCount) {
            var bar = document.querySelector('.cartbar');
            if (bar) replay(bar, 'famedo-bump', 400);
            document.querySelectorAll('.addbtn.famedo-loading').forEach(function (b) {
                b.classList.remove('famedo-loading');
            });
            if (lastAddBtn && document.contains(lastAddBtn)) {
                replay(lastAddBtn, 'famedo-added', 600);
            }
            lastAddBtn = null;
        }
        if (n !== null) lastCount = n;
    }).observe(document.documentElement, {subtree: true, childList: true, characterData: true});

    lastCount = readCount();
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
