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
        if (document.hidden) return; // skip backgrounded tabs (battery + no unlock-burst)
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

/* Slot dead-end recovery: after switching to a date whose slots don't include
   the previously-picked time, select the NEAREST free slot to that time (not
   just the first) so the choice stays as close as possible to what the customer
   wanted. Called from the date bubbles after their slots re-render; bails if a
   slot is already highlighted (the old time is still valid → keep it). */
window.fmSelectNearestSlot = function (prevTime) {
    var box = document.getElementById('local-timeslot');
    if (!box || box.querySelector('.slot.on')) return;
    var slots = [].slice.call(box.querySelectorAll('.pickslots .slot[data-slot]'));
    if (!slots.length) return;
    var toMin = function (k) {
        var d = String(k).replace(/\D/g, '');
        if (d.length < 3) return NaN;
        d = d.slice(0, 4).padStart(4, '0');
        return parseInt(d.slice(0, 2), 10) * 60 + parseInt(d.slice(2, 4), 10);
    };
    var target = slots[0], pm = toMin(prevTime);
    if (!isNaN(pm)) {
        target = slots.reduce(function (best, el) {
            return Math.abs(toMin(el.dataset.slot) - pm) < Math.abs(toMin(best.dataset.slot) - pm) ? el : best;
        }, slots[0]);
    }
    target.click();
};

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
    /* Catch localStorage-restored + Livewire-morphed content — EVENT-DRIVEN
       (was a forever setInterval(1500) that ran on every page for the tab's
       whole life, even on the menu where no notes control exists). Now: after
       each Livewire commit + on SPA navigation. */
    document.addEventListener('livewire:navigated', syncNotes);
    function attachNotesHook() {
        if (!window.Livewire || typeof Livewire.hook !== 'function') return false;
        Livewire.hook('commit', function (opts) {
            (opts && typeof opts.succeed === 'function') ? opts.succeed(syncNotes) : syncNotes();
        });
        return true;
    }
    if (!attachNotesHook()) document.addEventListener('livewire:init', attachNotesHook);
})();

/* ---------- Checkout: scroll to the first validation error ----------
   A failed „Bestellen" tap re-renders the form with error markers, but on a
   phone the offending field is usually off-screen (identity at the top, the
   button at the bottom) — the customer sees nothing happen. Contract used:
   every checkout error surface renders a div with an id ending in
   "-feedback" that EXISTS only while its error does (forms.error = @error
   wrapper; covers field errors, delivery_address, fields.payment, terms).
   Flow: arm on the #checkout-form submit → reveal once the commit BURST that
   the submit kicked off has gone quiet, then scroll the first error into view
   and focus its input.

   Why debounce-until-quiet instead of acting on the first commit: one tap
   fires several commits (the wire:model.blur of the field being left, then
   validate, then confirm). Acting on the earliest one would scroll to the
   PREVIOUS attempt's stale error markers, which the pending morph is about to
   remove. Each commit reschedules, so we always read the settled DOM.
   Finding no error does NOT disarm — a passing validate is followed by
   confirm, and payment/processing errors surface only on that second commit.
   Any fresh user interaction disarms, so a later unrelated commit (fulfillment
   modal, cart) can never scroll-jack. */
(function () {
    var armed = false;
    var timer = null;
    var SETTLE_MS = 160;

    document.addEventListener('submit', function (e) {
        if (e.target && e.target.id === 'checkout-form') armed = true;
    }, true);
    // Capture phase: the submit-button pointerdown disarms, then the submit
    // event re-arms a moment later — order is what makes this safe.
    // EXCEPT taps on the submit button itself: it greys out for ~1s during the
    // roundtrip, and an impatient customer tapping it again is retrying the SAME
    // submit, not moving on. Disarming there would swallow the scroll for
    // exactly the user who needs it most.
    document.addEventListener('pointerdown', function (e) {
        var t = e && e.target;
        if (t && t.closest && t.closest('[data-checkout-control="submit"]')) return;
        armed = false;
    }, true);
    document.addEventListener('keydown', function () { armed = false; }, true);

    function firstError() {
        var root = document.querySelector('[data-control="checkout"]');
        if (!root) return null;
        var nodes = root.querySelectorAll('[id$="-feedback"]');
        // Skip anything not actually on screen. No checkout error can reach a
        // hidden container today: the „+ Anmerkung" collapse holds only
        // comment/delivery_comment, whose sole rule is max:500 — hitting it
        // means the customer typed in there, which force-opens the collapse —
        // and the one path that targets fields.comment (order-processing
        // failure) needs two-page checkout, which famedo does not enable.
        // This is a cheap guard so a future hidden field can't make us scroll
        // to something invisible.
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].offsetParent !== null) return nodes[i];
        }
        return null;
    }

    function fullyVisible(el) {
        var r = el.getBoundingClientRect();
        var h = window.innerHeight || document.documentElement.clientHeight;
        return r.top >= 8 && r.bottom <= h - 8;
    }

    function reveal() {
        timer = null;
        if (!armed) return;
        var err = firstError();
        if (!err) return;
        armed = false;
        // Scroll the whole field wrapper (label + input + message), not the
        // bare error line, so the customer sees WHAT is wrong, not just why.
        var box = err.closest('.col-sm-6') || err.closest('.famedo-co-sec') || err;
        // Already on screen (desktop, where the form often fits): don't jump.
        var moved = !fullyVisible(box);
        if (moved) box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var input = box.querySelector('input.is-invalid, textarea.is-invalid, select.is-invalid');
        if (input) {
            // Focus AFTER the smooth scroll settles: focusing mid-scroll makes
            // the phone keyboard reflow the page and land somewhere else.
            setTimeout(function () {
                try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
            }, moved ? 450 : 0);
        }
    }

    function schedule() {
        if (!armed) return;
        if (timer) clearTimeout(timer);
        timer = setTimeout(reveal, SETTLE_MS);
    }

    function attachHook() {
        if (!window.Livewire || typeof Livewire.hook !== 'function') return false;
        Livewire.hook('commit', function (opts) {
            if (!opts || typeof opts.succeed !== 'function') return;
            opts.succeed(schedule);
        });
        return true;
    }
    if (!attachHook()) document.addEventListener('livewire:init', attachHook);
})();

/* ---------- Guest keep-prefilled hygiene ----------
   Once logged in, the ACCOUNT is the source of truth for checkout identity —
   the guest-era localStorage prefill is stale at best and, on a shared
   device, the previous customer's identity at worst. Clearing it while
   authed also means nothing leaks to the NEXT guest after logout. */
(function () {
    if (document.body.classList.contains('famedo-authed')) {
        // sessionStorage = current home (session-only since 2026-07-28);
        // localStorage = legacy cleanup for anyone still carrying a July-9 entry.
        try { sessionStorage.removeItem('checkout_fields'); } catch (e) { /* storage blocked */ }
        try { localStorage.removeItem('checkout_fields'); } catch (e) { /* storage blocked */ }
    }
})();

/* ---------- Food-info dialog (allergens/Zusatzstoffe, LMIV) ----------
   Every menu row carries an .infobtn with its payload in data-food-info
   (states: unknown | declared | none). One shared dialog shell lives outside
   the Livewire component (food-info-dialog.blade.php).
   ⚠️ CAPTURE phase is load-bearing: the row div itself has wire:click
   add-to-cart (simple items) or the bottom-sheet trigger — a bubble-phase
   handler runs after those, so tapping "i" would add the item to the cart. */
(function () {
    function els() {
        var d = document.getElementById('foodDialog');
        var b = document.getElementById('foodDialogBackdrop');
        return d && b ? { d: d, b: b } : null;
    }

    function fill(d, info) {
        var q = function (role) { return d.querySelector('[data-role="' + role + '"]'); };
        q('dish').textContent = info.name || '';
        var list = q('allergens'), adds = q('additives'), none = q('none'), unknown = q('unknown');
        var stDecl = q('status-declared'), stConf = q('status-confirmed');
        list.hidden = adds.hidden = none.hidden = unknown.hidden = true;
        // colored verified/unverified label next to the dish name (only when data publishes)
        stDecl.hidden = !(info.state !== 'unknown' && info.status === 'declared');
        stConf.hidden = !(info.state !== 'unknown' && info.status === 'confirmed');
        if (info.state === 'unknown') {
            unknown.hidden = false;
            return;
        }
        if (info.state === 'none') {
            none.hidden = false;
            return;
        }
        if (info.allergens && info.allergens.length) {
            list.innerHTML = '';
            info.allergens.forEach(function (name) {
                var s = document.createElement('span');
                s.textContent = name;
                list.appendChild(s);
            });
            list.hidden = false;
        }
        if (info.additives && info.additives.length) {
            adds.innerHTML = '';
            info.additives.forEach(function (label) {
                var s = document.createElement('span');
                s.textContent = label;
                adds.appendChild(s);
            });
            adds.hidden = false;
        }
    }

    function open(info) {
        var e = els();
        if (!e) return;
        fill(e.d, info);
        e.d.hidden = e.b.hidden = false;
        // next frame so the opacity transition actually runs from 0
        requestAnimationFrame(function () {
            e.d.classList.add('show');
            e.b.classList.add('show');
        });
    }

    function close() {
        var e = els();
        if (!e) return;
        e.d.classList.remove('show');
        e.b.classList.remove('show');
        setTimeout(function () { e.d.hidden = e.b.hidden = true; }, 220);
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('.infobtn');
        if (btn && btn.dataset.foodInfo) {
            ev.preventDefault();
            ev.stopPropagation();
            try { open(JSON.parse(btn.dataset.foodInfo)); } catch (e) { /* malformed payload */ }
            return;
        }
        if (ev.target.closest && (ev.target.closest('[data-role="close"]') || ev.target === document.getElementById('foodDialogBackdrop'))) {
            ev.stopPropagation();
            close();
        }
    }, true);

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') close();
    });
})();

/* ---------- Diet filter (foodlabels v2) ----------
   Client-side AND-filter over data-diet on the item rows (raw codes, with
   vegan→veg already expanded server-side by DietLabels::filterCodes). State
   lives in module scope and on morph-immune elements (chip row, sticky strip,
   rail links, empty block); everything applied to .item/.cat is wiped by the
   MenuItemList morph on every add-to-cart, so apply() re-runs after each
   Livewire commit settles (same debounce pattern as the checkout error
   scroller). The whole UI stays hidden when the rendered menu carries no
   diet-tagged rows — an untagged tenant never sees a filter. */
(function () {
    var active = new Set();
    var timer = null, SETTLE_MS = 160;
    // veg + vegan are LEVELS of one dimension, never both active: selecting
    // the second one FUSES the veg chip into the vegan chip (reverse cell
    // division — decided from the click-dummy 2026-08-03); deselecting vegan
    // splits it back out. `merged` = veg chip currently fused away.
    var merged = false;
    var RM = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    function ui() {
        var chips = document.querySelector('[data-diet-filter]');
        if (!chips) return null;
        return {
            chips: chips,
            strip: document.querySelector('[data-diet-strip]'),
            empty: document.querySelector('[data-diet-empty]'),
        };
    }

    function chipEl(f) {
        var u = ui();
        return u ? u.chips.querySelector('.fchip[data-f="' + f + '"]') : null;
    }

    function centerDx(from, to) {
        var a = from.getBoundingClientRect(), b = to.getBoundingClientRect();
        return (b.left + b.width / 2) - (a.left + a.width / 2);
    }

    function veganGulp(el, delay) {
        if (RM || !el || !el.animate) return;
        setTimeout(function () {
            el.animate([
                {transform: 'scale(1)'},
                {transform: 'scale(1.22,.84)', offset: .35},
                {transform: 'scale(.94,1.08)', offset: .65},
                {transform: 'scale(1)'}
            ], {duration: 420, easing: 'ease-out'});
        }, delay || 0);
    }

    // veg chip travels into the vegan chip, the row closes over its place.
    // Both animations hold their end state (fill:'forwards') until the chip is
    // display:none — without that, the chip snaps back to base styles for one
    // frame between animation end and hide, flashing "Vegetarisch" mid-fusion.
    var fuseAnims = [];
    function fuseVeg() {
        var veg = chipEl('veg'), vegan = chipEl('vegan');
        if (!veg || merged) return;
        merged = true;
        if (RM || !veg.animate || !vegan) {
            veg.classList.add('mergedaway');
            return;
        }
        var dx = centerDx(veg, vegan);
        veg.style.pointerEvents = 'none';
        var a1 = veg.animate([
            {transform: 'translateX(0) scale(1)', opacity: 1},
            {transform: 'translateX(' + dx * .6 + 'px) scale(.8)', opacity: .9, offset: .6},
            {transform: 'translateX(' + dx + 'px) scale(.2)', opacity: 0}
        ], {duration: 400, easing: 'cubic-bezier(.5,0,.65,1)', fill: 'forwards'});
        fuseAnims.push(a1);
        a1.onfinish = function () {
            var w = veg.offsetWidth;
            veg.style.overflow = 'hidden';
            var a2 = veg.animate([
                {width: w + 'px', paddingLeft: '12px', paddingRight: '12px', borderWidth: '1.5px'},
                {width: '0px', paddingLeft: '0px', paddingRight: '0px', borderWidth: '0px'}
            ], {duration: 200, easing: 'ease', fill: 'forwards'});
            fuseAnims.push(a2);
            a2.onfinish = function () {
                veg.classList.add('mergedaway'); // display:none first...
                fuseAnims.forEach(function (a) { a.cancel(); }); // ...then release the holds
                fuseAnims = [];
                veg.style.cssText = '';
            };
        };
        veganGulp(vegan, 300);
    }

    // cell division: the veg chip buds back out of the vegan chip
    function splitVeg(animate) {
        var veg = chipEl('veg'), vegan = chipEl('vegan');
        merged = false;
        fuseAnims.forEach(function (a) { a.cancel(); }); // fusion may still be mid-flight
        fuseAnims = [];
        if (!veg) return;
        veg.classList.remove('mergedaway');
        veg.style.cssText = '';
        if (!animate || RM || !veg.animate || !vegan) return;
        var dx = centerDx(veg, vegan);
        vegan.animate([
            {transform: 'scale(1)'},
            {transform: 'scale(1.14,.88)', offset: .4},
            {transform: 'scale(1)'}
        ], {duration: 320, easing: 'ease-out'});
        veg.animate([
            {transform: 'translateX(' + dx + 'px) scale(.2)', opacity: 0},
            {transform: 'translateX(' + dx * .35 + 'px) scale(.85)', opacity: 1, offset: .55},
            {transform: 'translateX(0) scale(1.06)', offset: .85},
            {transform: 'translateX(0) scale(1)'}
        ], {duration: 430, easing: 'cubic-bezier(.3,.7,.3,1)'});
    }

    function railLinkFor(sec) {
        var head = sec.querySelector('[id^="category-"][id$="-heading"]');
        return head ? document.querySelector('#navbar-categories a[href="#' + head.id + '"]') : null;
    }

    function rowMatches(el) {
        var tags = (el.dataset.diet || '').split(' ');
        var ok = true;
        active.forEach(function (f) { if (tags.indexOf(f) < 0) ok = false; });
        return ok;
    }

    function apply() {
        var u = ui();
        if (!u) return;

        // auto-hide: this render carries no tagged rows -> no filter UI, clean state
        if (!document.querySelector('.item[data-diet]')) {
            active.clear();
            if (merged) splitVeg(false);
            u.chips.hidden = true;
        } else {
            u.chips.hidden = false;
        }

        var total = 0;
        var secs = document.querySelectorAll('.menu-group-item.cat');
        if (secs.length) {
            secs.forEach(function (sec) {
                var items = sec.querySelectorAll('.item'), vis = 0;
                items.forEach(function (el) {
                    var show = rowMatches(el);
                    el.classList.toggle('fd-hide', !show);
                    if (show) { vis++; total++; }
                });
                sec.classList.toggle('fd-hide', vis === 0);
                var link = railLinkFor(sec);
                if (link) link.classList.toggle('gone', vis === 0);
                var count = sec.querySelector('.cat__count');
                // restore by recount: server count == .item nodes per section
                if (count) count.textContent = active.size ? vis : items.length;
            });
        } else {
            // category-permalink page: flat list, no sections/counts/rail
            document.querySelectorAll('.menu-items .item').forEach(function (el) {
                var show = rowMatches(el);
                el.classList.toggle('fd-hide', !show);
                if (show) total++;
            });
        }

        if (u.empty) u.empty.hidden = !(active.size && total === 0);
        syncStrip(u, total);
        syncChips(u);
        refreshSpy();
    }

    function syncChips(u) {
        u.chips.querySelectorAll('.fchip').forEach(function (c) {
            var f = c.dataset.f;
            c.classList.toggle('on', f === 'all' ? active.size === 0 : active.has(f));
        });
    }

    function syncStrip(u, total) {
        var s = u.strip;
        if (!s) return;
        s.hidden = active.size === 0;
        if (s.hidden) {
            s.className = 'fstrip';
            return;
        }
        var codes = Array.from(active);
        var names = codes.map(function (f) { return s.dataset['label' + f.charAt(0).toUpperCase() + f.slice(1)] || f; });
        var txt = codes.length === 1 ? s.dataset.txtOnly.replace('%s', names[0]) : names.join(' + ');
        s.querySelector('[data-diet-strip-txt]').textContent =
            txt + ' — ' + total + ' ' + (total === 1 ? s.dataset.txtDish : s.dataset.txtDishes);
        s.className = 'fstrip' + (total === 0 ? ' fstrip--none'
            : codes.length === 1 ? ' fstrip--' + codes[0] : ' fstrip--multi');
    }

    function refreshSpy() {
        var root = document.querySelector('[data-bs-spy="scroll"]');
        var spy = root && window.bootstrap && bootstrap.ScrollSpy && bootstrap.ScrollSpy.getInstance(root);
        if (spy) spy.refresh();
    }

    // bubble phase is fine here: chips/strip/empty all live OUTSIDE the
    // wire:click rows (unlike the .infobtn, which needs capture)
    document.addEventListener('click', function (e) {
        if (!e.target.closest) return;
        var chip = e.target.closest('[data-diet-filter] .fchip');
        if (chip) {
            var f = chip.dataset.f;
            if (f === 'all') {
                active.clear();
                if (merged) splitVeg(true);
            } else if (f === 'vegan') {
                if (active.has('vegan')) {
                    active.delete('vegan');
                    if (merged) splitVeg(true); // division on the way out
                } else {
                    // vegan active = veg chip absorbed, ALWAYS (also when
                    // vegan is tapped first — the fusion IS the explanation)
                    active.delete('veg');
                    active.add('vegan');
                    fuseVeg();
                }
            } else if (f === 'veg') {
                if (active.has('vegan')) {
                    // safety only — the veg chip is fused away while vegan
                    // is active and can't normally be tapped
                    fuseVeg();
                    return;
                }
                if (active.has('veg')) active.delete('veg');
                else active.add('veg');
            } else if (active.has(f)) {
                active.delete(f);
            } else {
                active.add(f);
            }
            apply();
            return;
        }
        if (e.target.closest('[data-diet-clear]')) {
            active.clear();
            if (merged) splitVeg(true);
            apply();
        }
    });

    // re-apply after every Livewire morph settles (empty-set apply is a cheap
    // no-op that also re-runs the auto-hide check)
    function schedule() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, SETTLE_MS);
    }
    function attachHook() {
        if (!window.Livewire || typeof Livewire.hook !== 'function') return false;
        Livewire.hook('commit', function (opts) {
            if (opts && typeof opts.succeed === 'function') opts.succeed(schedule);
        });
        return true;
    }
    if (!attachHook()) document.addEventListener('livewire:init', attachHook);

    apply();
})();
