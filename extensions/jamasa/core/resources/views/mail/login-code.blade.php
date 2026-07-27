{{-- Login-code mail. Plain Laravel view (NOT a TI mail-template — those are
     sandboxed); sent via Mail::send from EmailCodeLogin. Kept deliberately
     simple: the code IS the message. --}}
<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f5f5f6;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#23262b;">
<div style="max-width:440px;margin:0 auto;padding:32px 20px;">
    <div style="background:#ffffff;border-radius:16px;padding:28px 24px;text-align:center;">
        <p style="margin:0 0 6px;font-size:15px;font-weight:700;">{{ $siteName }}</p>
        <p style="margin:0 0 22px;font-size:13.5px;color:#7c828c;">Dein Anmeldecode:</p>
        <p style="margin:0 0 22px;font-size:38px;font-weight:800;letter-spacing:10px;font-family:'SF Mono',Menlo,Consolas,monospace;">{{ $code }}</p>
        <p style="margin:0;font-size:12.5px;color:#7c828c;line-height:1.6;">
            Der Code ist 10 Minuten gültig.<br>
            Nicht angefordert? Dann kannst du diese E-Mail einfach ignorieren.
        </p>
    </div>
</div>
</body>
</html>
