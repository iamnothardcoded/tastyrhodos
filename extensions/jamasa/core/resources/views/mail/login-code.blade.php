<!DOCTYPE html>
<html lang="de" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<title>Dein Anmeldecode</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style>
  /* Nur defensive Regeln. Alles Wesentliche steht zusaetzlich inline. */
  body { margin:0 !important; padding:0 !important; width:100% !important; }
  table { border-collapse:collapse !important; }
  img { border:0; outline:none; text-decoration:none; }
  a { color:#1f2937; }
  @media only screen and (max-width:520px) {
    .fd-wrap { width:100% !important; }
    .fd-pad { padding-left:20px !important; padding-right:20px !important; }
    .fd-code { font-size:30px !important; letter-spacing:5px !important; text-indent:5px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5;">

<!-- Preheader: erscheint in der Vorschauzeile, danach Fuellzeichen, damit kein Body-Text nachrutscht -->
<div style="display:none; font-size:1px; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">
Dein Anmeldecode lautet {{ $code }}. Er gilt {{ $expiry_minutes ?? 15 }} Minuten.
&#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f5;">
  <tr>
    <td align="center" style="padding:32px 12px;">

      <table role="presentation" class="fd-wrap" width="480" cellpadding="0" cellspacing="0" border="0" style="width:480px; max-width:480px; background-color:#ffffff; border-radius:14px; border:1px solid #e6e6e9;">

        <!-- Akzentlinie in der Restaurantfarbe (kein Bild, kein externes Asset) -->
        <tr>
          <td style="height:4px; line-height:4px; font-size:4px; background-color:{{ $brand_color ?? '#FE6237' }}; border-radius:14px 14px 0 0;">&nbsp;</td>
        </tr>

        <!-- Absenderkennung als Text-Wortmarke -->
        <tr>
          <td class="fd-pad" align="left" style="padding:28px 36px 0 36px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:22px; font-weight:700; color:#111827; letter-spacing:0.2px;">
            {{ $restaurant_name }}
          </td>
        </tr>

        <!-- Ueberschrift -->
        <tr>
          <td class="fd-pad" align="left" style="padding:18px 36px 0 36px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:21px; line-height:29px; font-weight:600; color:#111827;">
            Dein Anmeldecode
          </td>
        </tr>

        <!-- Einleitung: Schluesselwort "Code" steht direkt vor dem Codeblock -->
        <tr>
          <td class="fd-pad" align="left" style="padding:10px 36px 0 36px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:24px; color:#4b5563;">
            Gib diesen Code im Bestellfenster ein, um dich anzumelden:
          </td>
        </tr>

        <!--
          WICHTIG: Der Code steht als EIN zusammenhaengender Textknoten in der Zelle.
          Keine Spans, keine <b> um einzelne Ziffern, keine &nbsp; dazwischen,
          kein Bild. Nur so lesen iOS Mail / Gmail den Code fuer die Autofill-Vorschlaege aus.
          letter-spacing ist reine Darstellung und veraendert den Textinhalt nicht.
        -->
        <tr>
          <td class="fd-pad" align="center" style="padding:22px 36px 0 36px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f7f7f8; border:1px solid #e6e6e9; border-radius:12px;">
              <tr>
                <td class="fd-code" align="center" style="padding:20px 12px; font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,Courier,monospace; font-size:34px; line-height:42px; font-weight:700; color:#111827; letter-spacing:7px; text-indent:7px;">{{ $code }}</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Gueltigkeit -->
        <tr>
          <td class="fd-pad" align="left" style="padding:16px 36px 0 36px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:22px; color:#6b7280;">
            Der Code gilt {{ $expiry_minutes ?? 15 }} Minuten und kann einmal verwendet werden.
          </td>
        </tr>

        <!-- Sicherheitshinweis -->
        <tr>
          <td class="fd-pad" align="left" style="padding:14px 36px 0 36px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:22px; color:#6b7280;">
            Du hast dich nicht angemeldet? Dann ignoriere diese E-Mail einfach. Ohne den Code passiert nichts. Wir fragen dich nie nach diesem Code &ndash; weder am Telefon noch per Nachricht.
          </td>
        </tr>

        <!-- Trenner -->
        <tr>
          <td class="fd-pad" style="padding:24px 36px 0 36px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr><td style="height:1px; line-height:1px; font-size:1px; background-color:#ececef;">&nbsp;</td></tr>
            </table>
          </td>
        </tr>

        <!-- Absenderidentitaet: Pflicht fuer Vertrauen und guten Spam-Score -->
        <tr>
          <td class="fd-pad" align="left" style="padding:16px 36px 30px 36px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:19px; color:#9096a1;">
            Diese E-Mail wurde automatisch versendet, weil bei {{ $restaurant_name }} eine Anmeldung mit dieser Adresse angefordert wurde.<br><br>
            Bestellsystem betrieben von {{ $operator_name ?? 'Polykatastima GmbH' }}, {{ $operator_address ?? '[Strasse, PLZ Ort]' }}.<br>
            Fragen? Antworte einfach auf diese E-Mail.
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>

</body>
</html>
