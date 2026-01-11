<div class="row g-3 mb-1">
    @foreach ($fields as $field)
        {{-- Skip delivery_comment field when order is not delivery type --}}
        @if ($field->fieldName === 'delivery_comment' && !$order->isDeliveryType())
            @continue
        @endif
        <div @class(['col-sm-6', $field->cssClass])>
            @include('igniter-orange::includes.checkout.field-'.$field->type, [
                'field' => $field,
                'orderModel' => $order,
            ])
        </div>
    @endforeach
</div>
