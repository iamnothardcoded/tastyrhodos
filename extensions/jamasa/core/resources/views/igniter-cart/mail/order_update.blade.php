subject = "@lang('jamasa.core::default.mail.order_update.subject', ['code' => $order_number], $mail_locale)"
==
@lang('jamasa.core::default.mail.order_update.heading', [], $mail_locale)

@lang('jamasa.core::default.mail.order_update.text_updated', ['code' => $order_number], $mail_locale)
{{ $status_name }}

@lang('jamasa.core::default.mail.order_update.text_comments', [], $mail_locale)
{{ $status_comment }}

@lang('jamasa.core::default.mail.order_update.text_view_url', [], $mail_locale)
{{ $order_view_url }}
==
@lang('jamasa.core::default.mail.order_update.greeting', ['name' => $first_name.' '.$last_name], $mail_locale)

@lang('jamasa.core::default.mail.order_update.html_updated', ['code' => $order_number], $mail_locale) <br>
**{{ $status_name }}**

@lang('jamasa.core::default.mail.order_update.text_comments', [], $mail_locale) <br>
**{{ $status_comment }}**

@partial('button', ['url' => $order_view_url, 'type' => 'primary'])
@lang('jamasa.core::default.mail.order_update.button_view', [], $mail_locale)
@endpartial
