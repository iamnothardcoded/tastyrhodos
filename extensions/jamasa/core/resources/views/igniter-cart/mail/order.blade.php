subject = "@mailLang('order.subject', ['site' => $site_name, 'code' => \Jamasa\Core\Helpers\PickupCode::fromHash($order->hash)])"
==
@mailLang('order.heading')

@mailLang('order.greeting', ['name' => $first_name.' '.$last_name])

@mailLang('order.text_received')

@mailLang('order.text_view_url')
{{$order_view_url}}

@mailLang('order.text_order_number', ['code' => \Jamasa\Core\Helpers\PickupCode::fromHash($order->hash)])
@mailLang('order.text_order_type', ['type' => $order_type])

@mailLang('order.label_order_date') {{$order_date}}
@mailLang('order.label_requested_time', ['type' => $order_type]) {{$order_time}}
@mailLang('order.label_payment') {{$order_payment}}

{{$order_address}}
@mailLang('order.label_restaurant') {{$location_name}}

{{$order_comment}}

@if(!empty($order_menus))
    @foreach($order_menus as $order_menu)
        {{ $order_menu['menu_quantity'] }} x {{ $order_menu['menu_name'] }}
        {!! $order_menu['menu_options'] !!}
        - {{ $order_menu['menu_price'] }}
        - {{ $order_menu['menu_subtotal'] }}
        {!! $order_menu['menu_comment'] !!}
    @endforeach
@endif

@if(!empty($order_totals))
    @foreach($order_totals as $order_total)
        {{ $order_total['order_total_title'] }}
        {{ $order_total['order_total_value'] }}
    @endforeach
@endif

==
@mailLang('order.greeting', ['name' => $first_name.' '.$last_name])

## @mailLang('order.heading')

@mailLang('order.html_received', ['type' => $order_type, 'code' => \Jamasa\Core\Helpers\PickupCode::fromHash($order->hash)])

@mailLang('order.link_progress', ['url' => $order_view_url])

**@mailLang('order.label_requested_time', ['type' => $order_type])** {{$order_time}}<br>
**@mailLang('order.label_payment')** {{$order_payment}}<br>
**@mailLang('order.label_restaurant')** {{$location_name}}<br>
**@mailLang('order.label_delivery_address')** {{$order_address}}

{{$order_comment}}

@partial('table')
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <thead>
    <tr>
        <th width="50%" align="left">@mailLang('order.column_name')</th>
        <th align="right">@mailLang('order.column_price')</th>
        <th align="right">@mailLang('order.column_subtotal')</th>
    </tr>
    </thead>
    <tbody>
    @if(!empty($order_menus))
        @foreach($order_menus as $order_menu)
            <tr>
                <td>{{ $order_menu['menu_quantity'] }} x {{ $order_menu['menu_name'] }}<br>{!! $order_menu['menu_options'] !!}<br>{!! $order_menu['menu_comment'] !!}</td>
                <td align="right">{{ $order_menu['menu_price'] }}</td>
                <td align="right">{{ $order_menu['menu_subtotal'] }}</td>
            </tr>
        @endforeach
    @endif
    <tr>
        <td colspan="99">
            <hr>
        </td>
    </tr>
    @if(!empty($order_totals))
        @foreach($order_totals as $order_total)
            <tr>
                <td><br></td>
                <td align="right">{{ $order_total['order_total_title'] }}</td>
                <td align="right">{{ $order_total['order_total_value'] }}</td>
            </tr>
        @endforeach
    @endif
    </tbody>
</table>
@endpartial
