{{-- Cancellation notice to the RESTAURANT. Plain Laravel view (not a TI mail
     template — those are sandboxed). Design goal: readable in two seconds on a
     phone behind the counter, and matchable against a slip already printed —
     so the pickup code is the largest thing on the page. --}}
<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#26221c;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;">
  <tr><td align="center" style="padding:28px 12px;">
    <table role="presentation" width="480" cellpadding="0" cellspacing="0" border="0" style="width:480px;max-width:480px;background:#ffffff;border-radius:14px;border:1px solid #e6e6e9;">
      <tr><td style="height:4px;line-height:4px;font-size:4px;background:#c0392b;border-radius:14px 14px 0 0;">&nbsp;</td></tr>

      <tr><td align="left" style="padding:26px 32px 0;font-size:20px;line-height:28px;font-weight:700;color:#c0392b;">
        Bestellung storniert
      </td></tr>

      <tr><td align="left" style="padding:8px 32px 0;font-size:14px;line-height:22px;color:#6b7280;">
        Falls der Bon bereits gedruckt wurde: bitte aus der Küche entfernen.
      </td></tr>

      <tr><td align="center" style="padding:20px 32px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f7f8;border:1px solid #e6e6e9;border-radius:12px;">
          <tr><td align="center" style="padding:16px 12px 4px;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#8a8f98;">Abholcode</td></tr>
          <tr><td align="center" style="padding:0 12px 16px;font-family:'SFMono-Regular',Consolas,Menlo,monospace;font-size:34px;line-height:42px;font-weight:700;color:#111827;letter-spacing:6px;text-indent:6px;">{{ $code }}</td></tr>
        </table>
      </td></tr>

      <tr><td align="left" style="padding:18px 32px 0;font-size:15px;line-height:26px;color:#26221c;">
        <strong>Bestell-Nr.:</strong> {{ $orderId }}<br>
        <strong>Kunde:</strong> {{ $customerName }}<br>
        <strong>Art:</strong> {{ $orderType }}<br>
        <strong>Summe:</strong> {{ $orderTotal }}
      </td></tr>

      <tr><td align="left" style="padding:22px 32px 28px;font-size:12px;line-height:19px;color:#9096a1;">
        Automatische Nachricht von {{ $siteName }}.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
