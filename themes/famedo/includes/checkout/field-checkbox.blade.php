{{-- famedo override of igniter-orange::includes.checkout.field-checkbox (forked from ti-theme-orange v4.2.0) --}}
{{-- Single change vs vendor: the checkbox gets is-invalid via has_form_error()
     — the vendor never marks it, so an AGB rejection had no red state and the
     scroll helper (famedo.js reveal()) found nothing to focus (Global TODO #1
     (a)/(f)). Contracts kept verbatim: hidden input value="0" + wire:model
     pair (the "0"/"1" the `accepted` rule expects), data-checkout-control,
     the -feedback error id. --}}
@php
    $fieldOptions = $field->options();
    $checkedValues = (array)$field->value;
@endphp
<div class="p-3">
    <div class="form-group">
        <input
            type="hidden"
            wire:model="{{$field->getName()}}"
            value="0"
        />
        <div
            id="{{$field->getId('container')}}"
            @class(['form-check', 'form-check-inline' => $field->placeholder])
        >
            <input
                wire:model="{{$field->getName()}}"
                data-checkout-control="{{$field->fieldName}}"
                type="checkbox"
                @class(['form-check-input', 'is-invalid' => has_form_error($field->getName())])
                id="{{$field->getId()}}"
                name="{{$field->getName()}}"
                value="1"
                aria-describedby="{{$field->getName()}}-feedback"
            >
            @if($field->placeholder)
                <label class="form-check-label ms-2" for="{{ $field->getId() }}">@lang($field->placeholder)</label>
            @endif
        </div>
        <x-igniter-orange::forms.error field="{{$field->getName()}}" id="{{$field->getName()}}-feedback"
            class="text-danger"/>
    </div>
</div>
