subject = "@mailLang('order_update.subject', ['code' => \Jamasa\Core\Helpers\PickupCode::fromHash($order->hash)])"
==
@mailLang('order_update.heading')

@mailLang('order_update.text_updated', ['code' => \Jamasa\Core\Helpers\PickupCode::fromHash($order->hash)])
{{ $status_name }}

@mailLang('order_update.text_comments')
{{ $status_comment }}

@mailLang('order_update.text_view_url')
{{ $order_view_url }}
==
@mailLang('order_update.greeting', ['name' => $first_name.' '.$last_name])

@mailLang('order_update.html_updated', ['code' => \Jamasa\Core\Helpers\PickupCode::fromHash($order->hash)]) <br>
**{{ $status_name }}**

@mailLang('order_update.text_comments') <br>
**{{ $status_comment }}**

@partial('button', ['url' => $order_view_url, 'type' => 'primary'])
@mailLang('order_update.button_view')
@endpartial
