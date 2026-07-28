{{-- Returning logged-in customer with a complete profile: identity is a summary
     card (Wolt/Uber pattern), not editable fields — name/email change only in
     Meine Daten. The phone stays editable PER ORDER (dead-battery case; the
     jamasa profile-sync listener never overwrites the profile number). The
     Livewire props stay server-prefilled from the customer, so submitting
     without rendering these inputs is safe; the jamasa afterSaveOrder listener
     additionally server-enforces order email = account email. --}}
@php($identityCustomer = \Igniter\User\Facades\Auth::isLogged() ? \Igniter\User\Facades\Auth::customer() : null)
{{-- All three identity fields must be present before we hide them: hiding
     last_name (a required field) while it is empty makes checkout permanently
     un-submittable with no field to fix it (e.g. a Lieferando-import record). --}}
@php($identityLocked = $identityCustomer && filled($identityCustomer->first_name) && filled($identityCustomer->last_name) && filled($identityCustomer->email))
{{-- ⚠️ This partial is included FOUR times (details/comments/payments/terms).
     $identityLocked must stay global (it also drives the reduced localStorage
     FIELDS list in every script copy below), but the card renders ONLY in the
     include that carries the identity fields. --}}
@php($fieldsHaveIdentity = collect($fields)->contains(fn($f) => $f->fieldName === 'email'))
{{-- Notes section (comment/delivery_comment): collapsed behind „+ Anmerkung
     hinzufügen" (Wolt pattern, decided 2026-07-27). Expansion state = body
     class famedo-notes-open (survives Livewire morphs); famedo.js toggles it
     and force-opens whenever a note has content (draft/localStorage). --}}
@php($isNotesSection = collect($fields)->contains(fn($f) => in_array($f->fieldName, ['comment', 'delivery_comment'])))

@if($isNotesSection)
    <button type="button" class="addnote-link" data-famedo-notes-toggle>
        <span class="addnote-link__plus">+</span> Anmerkung hinzufügen
    </button>
@endif

@if($identityLocked && $fieldsHaveIdentity)
    <div class="checkout-ident">
        <div class="checkout-ident__body">
            <div class="checkout-ident__label">Bestellt als</div>
            <div class="checkout-ident__name">{{ $identityCustomer->first_name }} {{ $identityCustomer->last_name }}</div>
            <div class="checkout-ident__mail">{{ $identityCustomer->email }}</div>
        </div>
        <a class="checkout-ident__edit" href="{{ page_url('account.profile') }}?return=checkout">ändern <i class="fa fa-chevron-right"></i></a>
    </div>
@endif

<div @class(['row g-3 mb-1', 'co-notes' => $isNotesSection])>
    @foreach ($fields as $field)
        {{-- Skip delivery_comment field when order is not delivery type --}}
        @if ($field->fieldName === 'delivery_comment' && !$order->isDeliveryType())
            @continue
        @endif
        {{-- Identity lives in the card above for returning customers --}}
        @if ($identityLocked && in_array($field->fieldName, ['first_name', 'last_name', 'email']))
            @continue
        @endif
        <div @class(['col-sm-6', $field->cssClass])>
            @if ($field->type === 'text')
                {{-- Text field with wire:model.blur for immediate sync --}}
                <div @class(['form-floating', 'is-invalid' => has_form_error($field->getName())])>
                    <input
                        wire:model.blur="{{$field->getName()}}"
                        data-checkout-control="{{$field->fieldName}}"
                        type="text"
                        id="{{$field->getId()}}"
                        @class(['form-control', 'is-invalid' => has_form_error($field->getName())])
                        placeholder="{{ $field->placeholder }}"
                        aria-describedby="{{$field->getId()}}-feedback"
                        autocomplete="off"
                        {!! $field->hasAttribute('maxlength') ? '' : 'maxlength="255"' !!}
                        {!! $field->getAttributes() !!}
                    />
                    <label for="{{$field->getId()}}">@lang($field->label)</label>
                </div>
                <x-igniter-orange::forms.error
                    field="{{$field->getName()}}"
                    id="{{$field->getId()}}-feedback"
                    class="text-danger"
                />
            @elseif ($field->type === 'email')
                {{-- Email field with wire:model.blur for immediate sync --}}
                <div @class(['form-floating', 'is-invalid' => has_form_error($field->getName())])>
                    <input
                        wire:model.blur="{{$field->getName()}}"
                        data-checkout-control="{{$field->fieldName}}"
                        type="email"
                        id="{{$field->getId()}}"
                        @class(['form-control', 'is-invalid' => has_form_error($field->getName())])
                        placeholder="{{ $field->placeholder }}"
                        aria-describedby="{{$field->getId()}}-feedback"
                        autocomplete="off"
                        {!! $field->hasAttribute('maxlength') ? '' : 'maxlength="255"' !!}
                        {!! $field->getAttributes() !!}
                    />
                    <label for="{{$field->getId()}}">@lang($field->label)</label>
                </div>
                <x-igniter-orange::forms.error
                    field="{{$field->getName()}}"
                    id="{{$field->getId()}}-feedback"
                    class="text-danger"
                />
            @elseif ($field->type === 'textarea')
                {{-- Textarea field with wire:model.blur for immediate sync --}}
                <div @class(['form-floating', 'is-invalid' => has_form_error($field->getName())])>
                    <textarea
                        wire:model.blur="{{$field->getName()}}"
                        data-checkout-control="{{$field->fieldName}}"
                        name="{{$field->getName()}}"
                        id="{{$field->getId()}}"
                        autocomplete="off"
                        @class(['form-control', 'is-invalid' => has_form_error($field->getName())])
                        placeholder="{{ $field->placeholder }}"
                        aria-describedby="{{$field->getId()}}-feedback"
                        {!! $field->getAttributes() !!}
                    >{{ $field->value }}</textarea>
                    <label for="{{$field->getId()}}">@lang($field->label)</label>
                </div>
                <x-igniter-orange::forms.error
                    field="{{$field->getName()}}"
                    id="{{$field->getId()}}-feedback"
                    class="text-danger"
                />
            @elseif ($field->type === 'telephone')
                {{-- Telephone field with wire:model.blur for immediate sync --}}
                <div @class(['form-floating', 'is-invalid' => has_form_error($field->getName())])>
                    <input
                        wire:model.blur="{{$field->getName()}}"
                        data-checkout-control="{{$field->fieldName}}"
                        type="tel"
                        id="{{$field->getId()}}"
                        @class(['form-control', 'is-invalid' => has_form_error($field->getName())])
                        placeholder="{{ $field->placeholder }}"
                        aria-describedby="{{$field->getId()}}-feedback"
                        autocomplete="tel"
                        minlength="6"
                        pattern="[\d\s\-\+\(\)]{6,}"
                        title="@lang('igniter.orange::default.error_telephone_invalid')"
                        {!! $field->getAttributes() !!}
                    />
                    <label for="{{$field->getId()}}">{{ $identityLocked && $field->fieldName === 'telephone' ? 'Telefon für diese Bestellung' : lang($field->label) }}</label>
                </div>
                @if($identityLocked && $field->fieldName === 'telephone')
                    <div class="checkout-ident__phonehint">Änderungen gelten nur für diese Bestellung.</div>
                @endif
                <x-igniter-orange::forms.error
                    field="{{$field->getName()}}"
                    id="{{$field->getId()}}-feedback"
                    class="text-danger"
                />
            @else
                {{-- Other field types use original include --}}
                @include('igniter-orange::includes.checkout.field-'.$field->type, [
                    'field' => $field,
                    'orderModel' => $order,
                ])
            @endif
        </div>
    @endforeach
</div>

@script
<script>
    const STORAGE_KEY = 'checkout_fields';
    /* Field lifetimes (decided 2026-07-28, driver note un-stuck same day):
       - localStorage (persists across visits): guest identity fields only
         (July-9 keep-prefilled).
       - sessionStorage (this tab only, cleared on the success page): BOTH
         notes for everyone + the per-order phone for logged-in customers.
         They keep the core guarantee — content survives validation errors and
         Liefern/Abholen toggles WITHIN the order — without becoming defaults.
         A sticky driver note only makes sense bound to a specific ADDRESS
         (wrong house otherwise) → deferred, see the low-priority TODO. */
    const FIELDS = {!! json_encode($identityLocked ? [] : ['first_name', 'last_name', 'email', 'telephone']) !!};
    const IDENTITY_LOCKED = {!! json_encode((bool) $identityLocked) !!};
    const PHONE_SESSION_KEY = 'checkout_order_phone';
    const NOTE_SESSION_KEY = 'checkout_order_note';
    const DELNOTE_SESSION_KEY = 'checkout_delivery_note';

    /* Safe storage: touching window.localStorage throws SecurityError when site
       data is blocked (Chrome), setItem throws QuotaExceededError (old iOS
       private mode). Swallow both — the persistence layer must degrade, not take
       the whole checkout script down with an uncaught error. */
    function store(area, op, key, val) {
        try {
            const s = area === 'l' ? window.localStorage : window.sessionStorage;
            if (op === 'get') return s.getItem(key);
            if (op === 'del') { s.removeItem(key); return null; }
            s.setItem(key, val); return null;
        } catch (e) { return null; }
    }

    function saveSessionField(name, key) {
        const el = document.querySelector('[data-checkout-control="' + name + '"]');
        if (!el) return;
        if (el.value && el.value.trim() !== '') {
            store('s', 'set', key, el.value);
        } else {
            store('s', 'del', key);
        }
    }

    /* Restore = show it AND hand it to Livewire. The $wire.set is DEFERRED
       (third arg false: no network; the value rides the next real request,
       incl. submit) — undeferred sets caused one roundtrip per field on
       every checkout load. The direct el.value makes it visible instantly. */
    function setField(name, value) {
        $wire.set('fields.' + name, value, false);
        const el = document.querySelector('[data-checkout-control="' + name + '"]');
        if (el) el.value = value;
    }

    function saveFields() {
        const data = {};
        FIELDS.forEach(name => {
            const input = document.querySelector('[data-checkout-control="' + name + '"]');
            if (input && input.value) {
                data[name] = input.value;
            }
        });
        if (Object.keys(data).length > 0) {
            store('l', 'set', STORAGE_KEY, JSON.stringify(data));
        } else {
            store('l', 'del', STORAGE_KEY); // emptied → don't resurrect it next visit
        }
        if (IDENTITY_LOCKED) {
            saveSessionField('telephone', PHONE_SESSION_KEY);
        }
        saveSessionField('comment', NOTE_SESSION_KEY);
        saveSessionField('delivery_comment', DELNOTE_SESSION_KEY);
    }

    function restoreFields() {
        const saved = store('l', 'get', STORAGE_KEY);
        if (!saved) return;

        let data;
        try {
            data = JSON.parse(saved);
        } catch(e) {
            return;
        }

        FIELDS.forEach(name => {
            if (data[name]) {
                setField(name, data[name]);
            }
        });
    }

    function restoreSessionFields() {
        if (IDENTITY_LOCKED) {
            const phone = store('s', 'get', PHONE_SESSION_KEY);
            if (phone) setField('telephone', phone);
        }
        const note = store('s', 'get', NOTE_SESSION_KEY);
        if (note) setField('comment', note);
        const delnote = store('s', 'get', DELNOTE_SESSION_KEY);
        if (delnote) setField('delivery_comment', delnote);
    }

    // Save on blur
    document.addEventListener('blur', function(e) {
        if (e.target.matches && e.target.matches('[data-checkout-control]')) {
            saveFields();
        }
    }, true);

    // Save before leaving page
    window.addEventListener('beforeunload', saveFields);

    // DELIBERATE (decided 2026-07-09): fields are NOT cleared after a successful
    // order — a returning customer finds name/phone/email prefilled on their next
    // visit, which is the right UX for a repeat-order takeaway business. (An earlier
    // clear-on-success check here was dead code anyway: this script only renders on
    // the checkout form page, never on /checkout/success.)
    //
    // ⚠️ TWO LANDMINES in this block (both broke it silently 07-09 → 07-16):
    // 1. The block must NOT end with a line comment — Livewire trims the body
    //    and wraps it in an async IIFE, so a trailing comment swallows the
    //    closing braces ("Unexpected end of input", block never runs).
    // 2. Comments in here are still BLADE territory — never write a literal
    //    at-sign directive name (like the script/endscript wrappers around
    //    this block) inside them; Blade parses directives even in JS comments
    //    and shreds the block into broken fragments.

    // Restore on load
    restoreFields();
    restoreSessionFields();
</script>
@endscript
