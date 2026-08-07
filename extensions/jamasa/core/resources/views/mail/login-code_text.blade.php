{!! $restaurant_name !!}

Dein Anmeldecode

Gib diesen Code im Bestellfenster ein, um dich anzumelden:

Code: {{ $code }}

Der Code gilt {{ $expiry_minutes ?? 15 }} Minuten und kann einmal verwendet werden.

Du hast dich nicht angemeldet? Dann ignoriere diese E-Mail einfach. Ohne den
Code passiert nichts. Wir fragen dich nie nach diesem Code - weder am Telefon
noch per Nachricht.

--
Diese E-Mail wurde automatisch versendet, weil bei {!! $restaurant_name !!} eine
Anmeldung mit dieser Adresse angefordert wurde.

Bestellsystem betrieben von {!! $operator_name ?? "" !!},
{!! $operator_address ?? "" !!}.
Fragen? Antworte einfach auf diese E-Mail.
