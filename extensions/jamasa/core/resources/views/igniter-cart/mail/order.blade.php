subject = "@lang('jamasa.core::default.mail.order.subject', ['site' => $site_name, 'code' => $order_number], $mail_locale)"
==
@lang('jamasa.core::default.mail.order.heading', [], $mail_locale)

@lang('jamasa.core::default.mail.order.greeting', ['name' => $first_name.' '.$last_name], $mail_locale)

@lang('jamasa.core::default.mail.order.text_received', [], $mail_locale)

@lang('jamasa.core::default.mail.order.text_view_url', [], $mail_locale)
{{$order_view_url}}

@lang('jamasa.core::default.mail.order.text_order_number', ['code' => $order_number], $mail_locale)

@lang('jamasa.core::default.mail.order.text_order_type', ['type' => $order_type], $mail_locale)

@lang('jamasa.core::default.mail.order.label_order_date', [], $mail_locale) {{$order_date}}
@lang('jamasa.core::default.mail.order.label_requested_time', ['type' => $order_type], $mail_locale) {{$order_time}}
@lang('jamasa.core::default.mail.order.label_payment', [], $mail_locale) {{$order_payment}}

{{$order_address}}
@lang('jamasa.core::default.mail.order.label_restaurant', [], $mail_locale) {{$location_name}}

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
@lang('jamasa.core::default.mail.order.greeting', ['name' => $first_name.' '.$last_name], $mail_locale)

## @lang('jamasa.core::default.mail.order.heading', [], $mail_locale)

@lang('jamasa.core::default.mail.order.html_received', ['type' => $order_type, 'code' => $order_number], $mail_locale)

@lang('jamasa.core::default.mail.order.link_progress', ['url' => $order_view_url], $mail_locale)

**@lang('jamasa.core::default.mail.order.label_requested_time', ['type' => $order_type], $mail_locale)** {{$order_time}}<br>
**@lang('jamasa.core::default.mail.order.label_payment', [], $mail_locale)** {{$order_payment}}<br>
**@lang('jamasa.core::default.mail.order.label_restaurant', [], $mail_locale)** {{$location_name}}<br>
**@lang('jamasa.core::default.mail.order.label_delivery_address', [], $mail_locale)** {{$order_address}}

{{$order_comment}}

@partial('table')
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <thead>
    <tr>
        <th width="50%" align="left">@lang('jamasa.core::default.mail.order.column_name', [], $mail_locale)</th>
        <th align="right">@lang('jamasa.core::default.mail.order.column_price', [], $mail_locale)</th>
        <th align="right">@lang('jamasa.core::default.mail.order.column_subtotal', [], $mail_locale)</th>
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
