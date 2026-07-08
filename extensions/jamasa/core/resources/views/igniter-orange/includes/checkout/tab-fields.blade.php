<div class="row g-3 mb-1">
    @foreach ($fields as $field)
        {{-- Skip delivery_comment field when order is not delivery type --}}
        @if ($field->fieldName === 'delivery_comment' && !$order->isDeliveryType())
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
                    <label for="{{$field->getId()}}">@lang($field->label)</label>
                </div>
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
    const FIELDS = ['first_name', 'last_name', 'email', 'telephone', 'comment', 'delivery_comment'];

    function saveFields() {
        const data = {};
        FIELDS.forEach(name => {
            const input = document.querySelector('[data-checkout-control="' + name + '"]');
            if (input && input.value) {
                data[name] = input.value;
            }
        });
        if (Object.keys(data).length > 0) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        }
    }

    function restoreFields() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return;

        let data;
        try {
            data = JSON.parse(saved);
        } catch(e) {
            return;
        }

        FIELDS.forEach(name => {
            if (data[name]) {
                $wire.set('fields.' + name, data[name]);
            }
        });
    }

    // Save on blur
    document.addEventListener('blur', function(e) {
        if (e.target.matches && e.target.matches('[data-checkout-control]')) {
            saveFields();
        }
    }, true);

    // Save before leaving page
    window.addEventListener('beforeunload', saveFields);

    // Restore on load
    restoreFields();

    // DELIBERATE (decided 2026-07-09): fields are NOT cleared after a successful
    // order — a returning customer finds name/phone/email prefilled on their next
    // visit, which is the right UX for a repeat-order takeaway business. (An earlier
    // clear-on-success check here was dead code anyway: this script only renders on
    // the checkout form page, never on /checkout/success.)
</script>
@endscript
