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
        if (row) lastAddBtn = row.querySelector('.addbtn');
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
            if (lastAddBtn && document.contains(lastAddBtn)) {
                replay(lastAddBtn, 'famedo-added', 600);
            }
            lastAddBtn = null;
        }
        if (n !== null) lastCount = n;
    }).observe(document.documentElement, {subtree: true, childList: true, characterData: true});

    lastCount = readCount();
})();

/* Famedo delivery-address flow (fulfillment sheet). A suggestion click fills
   Straße/PLZ/Stadt + Hausnummer fields; the composed "Straße Nr, PLZ Stadt"
   is pushed to Livewire as a DEFERRED set (no round trip) and rides the
   onConfirm request, which geocodes it verbatim. The customer-typed house
   number always wins over Nominatim's (OSM answers with the nearest KNOWN
   building — the customer's input is delivery ground truth). */
(function () {
    document.addEventListener('alpine:init', function () {
        Alpine.data('FamedoAddress', function () {
            return {
                addrPicked: false,
                addrManualMode: false,
                addrRoad: '',
                addrNr: '',
                addrPlz: '',
                addrCity: '',

                addrPick(road, nr, plz, city) {
                    var typedEl = document.getElementById('search-query');
                    var typed = typedEl ? typedEl.value : '';
                    var m = typed.match(/(\d+\s*[a-zA-Z]?)\s*$/);

                    this.addrRoad = road || '';
                    this.addrPlz = plz || '';
                    this.addrCity = city || '';
                    this.addrNr = (m ? m[1].replace(/\s+/g, '') : '') || nr || '';
                    this.addrPicked = true;
                    this.addrManualMode = false;
                    this.addrSync();

                    if (!this.addrNr) {
                        var self = this;
                        this.$nextTick(function () {
                            if (self.$refs.addrNr) self.$refs.addrNr.focus();
                        });
                    }
                },

                addrSync() {
                    if (!this.addrPicked) return;
                    var composed = (this.addrRoad + ' ' + this.addrNr).trim()
                        + ', ' + (this.addrPlz + ' ' + this.addrCity).trim();
                    // deferred set: no network now, value rides the next request
                    this.$wire.set('searchQuery', composed, false);
                },

                addrManual() {
                    // Manual fallback (parity with the Adressbuch): open the field
                    // block with whatever was typed, parsed street/nr/PLZ/city.
                    var typedEl = document.getElementById('search-query');
                    var q = (typedEl ? typedEl.value : '').trim();
                    var parts = q.split(',');
                    var first = parts[0].trim();
                    var m = first.match(/^(.*?)\s+(\d+\s*[a-zA-Z]?)$/);
                    this.addrRoad = m ? m[1].trim() : first;
                    this.addrNr = m ? m[2].replace(/\s+/g, '') : '';
                    var rest = parts.slice(1).join(' ').trim();
                    var pm = rest.match(/\b(\d{5})\b/);
                    this.addrPlz = pm ? pm[1] : '';
                    this.addrCity = pm ? rest.replace(pm[1], '').replace(/,/g, ' ').trim() : rest;
                    this.addrPicked = true;
                    this.addrManualMode = true;
                    this.addrSync();
                    var self = this;
                    this.$nextTick(function () {
                        if (!self.addrRoad) return;
                        if (!self.addrNr && self.$refs.addrNr) self.$refs.addrNr.focus();
                    });
                },

                addrReset() {
                    this.addrPicked = false;
                    this.addrManualMode = false;
                    this.addrNr = '';
                    this.$wire.set('searchQuery', '', false);
                    this.$nextTick(function () {
                        var el = document.getElementById('search-query');
                        if (el) { el.value = ''; el.focus(); }
                    });
                },

                get addrBlocked() {
                    if (!this.addrPicked) return false;
                    if (!this.addrNr.trim()) return true;
                    // manual mode: geocoder likely knows nothing — the rescue path
                    // needs a full address, so require PLZ + Stadt before confirm
                    return this.addrManualMode
                        && (!/^\d{5}$/.test(this.addrPlz.trim()) || !this.addrCity.trim() || !this.addrRoad.trim());
                },
            };
        });
    });
})();

/* Famedo address-book flow (account Adressbuch modal). Same UX as the
   fulfillment sheet's FamedoAddress, but suggestions come from a plain fetch
   to the jamasa endpoint (the vendor AddressBook Livewire component is final
   and has no search pipeline), and the pick/edit state is synced into the
   STRUCTURED form.* wire props as DEFERRED sets that ride the onSave request.
   Same ground-truth rule: the customer-typed house number wins. */
(function () {
    document.addEventListener('alpine:init', function () {
        Alpine.data('FamedoAddressBook', function (address1, postcode, city, countryId) {
            return {
                abQuery: '',
                abSuggestions: [],
                abLoading: false,
                abSearched: false,
                abPicked: false,
                // 'picked' (from a suggestion — zone gate ENFORCED) | 'manual' |
                // 'edit' — manual/edit are the human fallback and never get the
                // zone nag; checkout stays the authoritative gate for usage.
                abMode: '',
                abRoad: '',
                abNr: '',
                abPlz: '',
                abCity: '',
                abCountryId: countryId || null,
                // delivery-zone pre-check: 'unknown' | 'checking' | 'ok' | 'out'
                // ('unknown'/geocoder-hiccup fails OPEN)
                abZone: 'unknown',
                abZoneTimer: null,
                abZoneQuery: '',

                init() {
                    // EDIT MODE: seed from the existing record — no search step.
                    if (address1) {
                        var m = address1.match(/^(.*?)[\s,]+(\d+\s*[a-zA-Z]?)\s*$/);
                        this.abRoad = m ? m[1].trim() : address1.trim();
                        this.abNr = m ? m[2].replace(/\s+/g, '') : '';
                        this.abPlz = postcode || '';
                        this.abCity = city || '';
                        this.abPicked = true;
                        this.abMode = 'edit';
                    }
                },

                abSearch() {
                    var q = this.abQuery.trim();
                    if (q.length < 3) {
                        this.abSuggestions = [];
                        this.abSearched = false;
                        return;
                    }
                    var self = this;
                    self.abLoading = true;
                    fetch('/jamasa/address-suggestions?q=' + encodeURIComponent(q), {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(function (r) { return r.ok ? r.json() : []; })
                        .then(function (list) { self.abSuggestions = Array.isArray(list) ? list : []; })
                        .catch(function () { self.abSuggestions = []; })
                        .finally(function () {
                            self.abLoading = false;
                            self.abSearched = true;
                        });
                },

                abPick(s) {
                    // customer-typed trailing house number beats the suggestion's
                    var m = this.abQuery.match(/(\d+\s*[a-zA-Z]?)\s*$/);
                    this.abRoad = s.road || '';
                    this.abPlz = s.postcode || '';
                    this.abCity = s.city || '';
                    this.abNr = (m ? m[1].replace(/\s+/g, '') : '') || s.houseNumber || '';
                    this.abPicked = true;
                    this.abMode = 'picked';
                    this.abSuggestions = [];
                    this.abSync();

                    if (!this.abNr) {
                        var self = this;
                        this.$nextTick(function () {
                            if (self.$refs.abNr) self.$refs.abNr.focus();
                        });
                    }
                },

                abSync() {
                    if (!this.abPicked) return;
                    // deferred sets: no network now, values ride the onSave request
                    this.$wire.set('form.address_1', (this.abRoad + ' ' + this.abNr).trim(), false);
                    this.$wire.set('form.postcode', this.abPlz.trim(), false);
                    this.$wire.set('form.city', this.abCity.trim(), false);
                    // ALWAYS ship country_id: the vendor component wipes the whole
                    // form (incl. the mount-time country default) on every
                    // addressId change — without this, saves after the first
                    // modal open/close fail "country required" invisibly.
                    if (this.abCountryId) {
                        this.$wire.set('form.country_id', this.abCountryId, false);
                    }
                    this.abZoneSchedule();
                },

                abComplete() {
                    return !!(this.abRoad.trim() && this.abNr.trim()
                        && /^\d{5}$/.test(this.abPlz.trim()) && this.abCity.trim());
                },

                abZoneSchedule() {
                    clearTimeout(this.abZoneTimer);
                    // Zone gate in EVERY mode (picked/manual/edit): a confident
                    // out-of-zone address would be rejected at checkout forever —
                    // saving it is useless, so blocking here is honesty, not
                    // nagging. The true last resort is untouched: when the
                    // geocoder CAN'T place an address (covered:null), the save
                    // passes silently — uncertainty never blocks a human.
                    if (!this.abComplete()) { this.abZone = 'unknown'; return; }
                    var composed = (this.abRoad + ' ' + this.abNr).trim()
                        + ', ' + (this.abPlz + ' ' + this.abCity).trim();
                    if (composed === this.abZoneQuery && this.abZone !== 'unknown') return;
                    var self = this;
                    this.abZone = 'checking';
                    this.abZoneTimer = setTimeout(function () { self.abZoneCheck(composed); }, 600);
                },

                abZoneCheck(composed) {
                    var self = this;
                    self.abZoneQuery = composed;
                    // Hard 6s cap: the check leans on Nominatim, which can crawl.
                    // A hanging check must never keep the save button dead —
                    // timeout = "can't tell" = 'unverified' (fail-open).
                    var abort = new AbortController();
                    var timer = setTimeout(function () { abort.abort(); }, 6000);
                    fetch('/jamasa/address-zone-check?q=' + encodeURIComponent(composed), {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                        signal: abort.signal,
                    })
                        .then(function (r) { return r.ok ? r.json() : { covered: null }; })
                        .then(function (res) {
                            if (self.abZoneQuery !== composed) return; // stale response
                            // covered:false = confident out → block.
                            // covered:null  = couldn't verify → 'unverified': soft
                            //                 non-blocking notice (fail-open).
                            // covered:true  = fine, silence.
                            self.abZone = res.covered === false ? 'out'
                                : (res.covered === null ? 'unverified' : 'ok');
                        })
                        .catch(function () { if (self.abZoneQuery === composed) self.abZone = 'unverified'; })
                        .finally(function () { clearTimeout(timer); });
                },

                abManual() {
                    // Fallback when Photon knows nothing: open the fields with
                    // whatever was typed (street + trailing number parsed out),
                    // customer completes by hand. abBlocked still enforces
                    // Straße/Nr/PLZ/Stadt before saving.
                    var q = this.abQuery.trim();
                    var m = q.match(/^(.*?)[\s,]+(\d+\s*[a-zA-Z]?)\s*$/);
                    this.abRoad = m ? m[1].trim() : q;
                    this.abNr = m ? m[2].replace(/\s+/g, '') : '';
                    this.abPlz = '';
                    this.abCity = '';
                    this.abPicked = true;
                    this.abMode = 'manual';
                    this.abSuggestions = [];
                    this.abSync();
                    var self = this;
                    this.$nextTick(function () {
                        var el = !self.abRoad ? 'abRoadEl' : (!self.abNr ? 'abNr' : 'abPlzEl');
                        if (self.$refs[el]) self.$refs[el].focus();
                    });
                },

                abReset() {
                    this.abPicked = false;
                    this.abNr = '';
                    this.abQuery = '';
                    this.abSuggestions = [];
                    this.abSearched = false;
                    var self = this;
                    this.$nextTick(function () {
                        if (self.$refs.abQuery) self.$refs.abQuery.focus();
                    });
                },

                get abBlocked() {
                    return !this.abPicked
                        || !this.abComplete()
                        || this.abZone === 'out'
                        || this.abZone === 'checking';
                },
            };
        });
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

/* ---------------------------------------------------------------
 * Ordering overlay (closed/paused): dismiss + resume-poll + toast.
 * Server renders overlay + body.ordering-* (layout); the partial's
 * inline script does the initial sessionStorage ack check so the
 * overlay never flashes. Here: dismiss buttons, the ~30s status
 * poll (reload on any state change; fresh server render beats DOM
 * surgery — banner/mode-info/Livewire state are server-rendered),
 * and the green toast on the reloaded open page.
 * --------------------------------------------------------------- */
(function () {
    var ACK = 'famedo-ack-', RESUMED = 'famedo-resumed';
    var store = {
        get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} },
        del: function (k) { try { sessionStorage.removeItem(k); } catch (e) {} }
    };

    var overlay = document.getElementById('famedoOrderingOverlay');
    var state = overlay ? overlay.getAttribute('data-famedo-ordering-state') : 'open';

    if (state === 'open') {
        // Freshly reloaded after a resume? Show the toast once.
        if (!store.get(RESUMED)) return;
        store.del(RESUMED);
        var toast = document.getElementById('famedoResumedToast');
        if (!toast) return;
        requestAnimationFrame(function () { toast.classList.add('show'); });
        setTimeout(function () { toast.classList.remove('show'); }, 3500);
        return;
    }

    // Dismiss -> browse (banner + lockout stay; once per session per state)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-famedo-overlay-dismiss]');
        if (!btn) return;
        store.set(ACK + state, '1');
        overlay.classList.remove('show');
    });

    // Simple items add-to-cart on ROW click (wire:click). While locked, swallow
    // the click BEFORE Livewire sees it (capture phase on document runs first;
    // stopPropagation keeps it from ever reaching the row) and answer with the
    // state-aware toast — the server's generic wording ("outside our hours")
    // is wrong during a pause, and a dead row reads as "broken".
    var lockedMsg = overlay.getAttribute('data-locked-toast') || '';
    var warnToast = null;
    document.addEventListener('click', function (e) {
        var row = e.target.closest && e.target.closest('[data-control="menu-item"]');
        if (!row) return;
        e.preventDefault();
        e.stopPropagation();
        if (!lockedMsg) return;
        if (!warnToast) {
            warnToast = document.createElement('div');
            warnToast.className = 'famedo-toast famedo-toast--warn';
            warnToast.setAttribute('role', 'status');
            warnToast.textContent = lockedMsg;
            document.body.appendChild(warnToast);
        }
        requestAnimationFrame(function () { warnToast.classList.add('show'); });
        clearTimeout(warnToast._famedoTimer);
        warnToast._famedoTimer = setTimeout(function () { warnToast.classList.remove('show'); }, 3000);
    }, true);

    // Poll while not open; on any state change reload for a fresh render.
    setInterval(function () {
        fetch('/jamasa/ordering-status', {headers: {Accept: 'application/json'}})
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.state || data.state === state) return;
                store.del(ACK + 'paused');
                store.del(ACK + 'closed');
                if (data.state === 'open') store.set(RESUMED, '1');
                location.reload();
            })
            .catch(function () { /* offline/hiccup -> next tick */ });
    }, 30000);
})();

/* ---------- Address-only fulfillment modal (checkout „Adresse ändern") ----------
   When the fulfillment modal is opened from the checkout address row
   (trigger carries data-famedo-addr-only), hide the timeslot section —
   changing the address mid-checkout shouldn't re-ask ASAP/time. The marker
   class lives on <body> (outside Livewire's morph reach, so re-renders inside
   the modal can't strip it); CSS does the hiding. */
(function () {
    /* Intent-scoped modal: address row → address-only; „Zeit ändern" link →
       time-only; the Liefern/Abholen pill → full modal. In every mode that
       SHOWS the address, the picker opens pre-expanded (search prefilled +
       saved addresses + manual link — no dead display-box step). Deliberately
       NOT auto-focused: the phone keyboard would cover the saved-address list. */
    var fmInitialQuery = null;
    var fmConfirmed = false;
    function fmComponent() {
        var root = document.getElementById('fulfillmentModal');
        root = root && root.closest('[wire\\:id]');
        return (root && window.Livewire) ? Livewire.find(root.getAttribute('wire:id')) : null;
    }
    document.addEventListener('show.bs.modal', function (e) {
        if (e.target && e.target.id === 'fulfillmentModal') {
            var rel = e.relatedTarget;
            var addrOnly = rel && rel.closest && rel.closest('[data-famedo-addr-only]');
            var timeOnly = rel && rel.closest && rel.closest('[data-famedo-time-only]');
            document.body.classList.toggle('famedo-addr-only', !!addrOnly);
            document.body.classList.toggle('famedo-time-only', !timeOnly ? false : !addrOnly);
            fmConfirmed = false;
            var comp = fmComponent();
            if (comp) {
                fmInitialQuery = comp.get('searchQuery');
                if (!timeOnly) comp.call('onChangeDeliveryAddress');
            }
        }
    });
    /* Confirm cost guard: the vendor's onConfirm geocodes the address (a LIVE
       Nominatim call, 1–3+s) whenever the picker flag is open — even when only
       the time changed. If the address text is untouched since the modal
       opened (or we're in time-only mode), drop the flag with a DEFERRED set
       (rides the same confirm request) so the geocode is skipped. A genuinely
       changed address keeps the flag → zone check runs as it must. */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.closest || !form.closest('#fulfillmentModal') || !window.Livewire) return;
        var comp = fmComponent();
        if (!comp) return;
        fmConfirmed = true;
        var timeOnly = document.body.classList.contains('famedo-time-only');
        var unchanged = fmInitialQuery !== null && comp.get('searchQuery') === fmInitialQuery;
        if (timeOnly || unchanged) {
            comp.set('showAddressPicker', false, false);
        }
    }, true);
    document.addEventListener('hidden.bs.modal', function (e) {
        if (e.target && e.target.id === 'fulfillmentModal') {
            document.body.classList.remove('famedo-addr-only');
            document.body.classList.remove('famedo-time-only');
            // Dismissed WITHOUT confirming? Revert any typed-but-unapplied query
            // to the applied address, so the next open's baseline is correct and
            // the geocode-skip can't silently discard a real change (or keep a
            // stale one). A confirmed close already applied it — leave it.
            var comp = fmComponent();
            if (comp && !fmConfirmed && fmInitialQuery !== null
                && comp.get('searchQuery') !== fmInitialQuery) {
                comp.set('searchQuery', fmInitialQuery, false);
            }
        }
    });
})();

/* ---------- Checkout notes: „+ Anmerkung hinzufügen" (collapse) ----------
   The notes row (comment/delivery_comment) is hidden until the customer asks
   for it — or until a note HAS content (draft order comment, localStorage
   restore), which force-opens it so typed text is never hidden. State =
   body class famedo-notes-open (outside Livewire's morph reach). */
(function () {
    document.addEventListener('click', function (e) {
        var t = e.target.closest && e.target.closest('[data-famedo-notes-toggle]');
        if (!t) return;
        document.body.classList.add('famedo-notes-open');
        setTimeout(function () {
            var ta = document.querySelector('.co-notes textarea');
            if (ta) ta.focus();
        }, 30);
    });
    function syncNotes() {
        if (document.body.classList.contains('famedo-notes-open')) return;
        var any = ['comment', 'delivery_comment'].some(function (n) {
            var el = document.querySelector('[data-checkout-control="' + n + '"]');
            return el && el.value && el.value.trim() !== '';
        });
        if (any) document.body.classList.add('famedo-notes-open');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncNotes);
    } else {
        syncNotes();
    }
    /* catches localStorage-restored + Livewire-morphed content */
    setInterval(syncNotes, 1500);
})();

/* ---------- Guest keep-prefilled hygiene ----------
   Once logged in, the ACCOUNT is the source of truth for checkout identity —
   the guest-era localStorage prefill is stale at best and, on a shared
   device, the previous customer's identity at worst. Clearing it while
   authed also means nothing leaks to the NEXT guest after logout. */
(function () {
    if (document.body.classList.contains('famedo-authed')) {
        try { localStorage.removeItem('checkout_fields'); } catch (e) { /* storage blocked */ }
    }
})();
