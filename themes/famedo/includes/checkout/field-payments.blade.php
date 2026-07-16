{{-- jamasa/core override of igniter-orange::includes.checkout.field-payments (forked from ti-theme-orange v4.1.3) --}}
{{-- Famedo payment cards. checkout.js contract preserved verbatim:
     data-toggle="payments" container, [data-checkout-payment] + selected class,
     radio with data-checkout-control/data-payment-code/data-pre-validate-checkout,
     label data-checkout-control="payment-label", gateway form @include inside
     the selected row. Unselected rows stay in the DOM (never display:none). --}}
@if($this->paymentGateways->isNotEmpty())
    <div class="famedo-co-sec">
        <div class="co-sec">@lang($field->label)</div>
        <div data-toggle="payments" class="progress-indicator-container paylist">
            @foreach ($this->paymentGateways as $paymentMethod)
                @php
                    $paymentIsSelected = ($field->value == $paymentMethod->code);
                    $paymentIsNotApplicable = !$paymentMethod->isApplicable($order->order_total, $paymentMethod);
                @endphp
                <div
                    @class(['payrow', 'selected' => $paymentIsSelected, 'opacity-50' => $paymentIsNotApplicable])
                    data-checkout-payment
                >
                    <div class="form-check famedo-payrow-check">
                        <input
                            data-checkout-control="{{$field->fieldName}}"
                            data-payment-code="{{ $paymentMethod->code }}"
                            data-pre-validate-checkout="{{ $paymentMethod->completesPaymentOnClient() ? 'true' : 'false' }}"
                            type="radio"
                            name="{{$field->getName()}}"
                            id="payment-{{ $paymentMethod->code }}"
                            class="form-check-input"
                            value="{{ $paymentMethod->code }}"
                            @checked($paymentIsSelected)
                            @disabled($paymentIsNotApplicable)
                            autocomplete="off"
                        />
                        <label
                            class="form-check-label payrow__main"
                            for="payment-{{ $paymentMethod->code }}"
                            data-checkout-control="payment-label"
                        >
                            <div class="payrow__name">{{ $paymentMethod->name }}</div>
                            @if(strlen($paymentMethod->description))
                                <div class="payrow__sub">{!! $paymentMethod->description !!}</div>
                            @endif
                            @if($paymentIsNotApplicable)
                                <div class="payrow__sub">
                                    <em>
                                        {!! sprintf(
                                            lang('igniter.payregister::default.alert_min_order_total'),
                                            currency_format($paymentMethod->order_total),
                                            lang('igniter.payregister::default.text_this_payment')
                                        ) !!}
                                    </em>
                                </div>
                            @endif
                            @if($paymentMethod->hasApplicableFee())
                                <div class="payrow__sub">
                                    <em>
                                        {!! sprintf(
                                            lang('igniter.payregister::default.alert_order_fee'),
                                            $paymentMethod->getFormattedApplicableFee(),
                                            lang('igniter.payregister::default.text_this_payment')
                                        ) !!}
                                    </em>
                                </div>
                            @endif
                        </label>
                        @if($paymentIsSelected && ($viewName = $paymentMethod->getPaymentFormViewName()))
                            @include($viewName)
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <x-igniter-orange::forms.error field="{{$field->getName()}}" id="{{$field->getName()}}-feedback"
            class="text-danger"/>
    </div>
@endif
