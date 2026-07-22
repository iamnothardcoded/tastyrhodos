{{--
    famedo self-contained error shell. Included by 404/500/503 via
    `@include('errors::famedo-shell', [...])`. Named `famedo-shell` on purpose:
    the `errors` view namespace is shared with core (which registers its
    `system/errors` dir AHEAD of the app path and ships `layout` + `minimal`),
    so those two names resolve to CORE — but any other name (404, 500, 503,
    famedo-shell) resolves to the app path. Hence @include of a uniquely-named
    partial, not @extends of `layout`. No core/vendor edit.

    Self-contained by design: renders outside theme context and even mid-500 —
    no external font/CSS/JS (DSGVO), no `/vendor/igniter/...` asset, only a
    rescued site_name.
--}}
@php($siteName = rescue(fn() => (string) setting('site_name'), '', false) ?: config('app.name'))
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $siteName }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f7f7f5; color: #1a1a1a; padding: 24px; line-height: 1.5;
        }
        .card { max-width: 30rem; width: 100%; text-align: center; }
        .brand { font-size: .875rem; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; opacity: .55; margin-bottom: 2rem; }
        .code { font-size: 4.5rem; font-weight: 800; line-height: 1; margin: 0 0 .5rem; letter-spacing: -.03em; }
        h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 .75rem; }
        p.msg { margin: 0 0 2rem; opacity: .7; }
        .btn {
            display: inline-block; padding: .75rem 1.5rem; border-radius: .625rem; text-decoration: none;
            font-weight: 600; background: #1a1a1a; color: #fff; transition: opacity .15s;
        }
        .btn:hover { opacity: .85; }
        @media (prefers-color-scheme: dark) {
            body { background: #17171a; color: #f2f2f2; }
            .btn { background: #f2f2f2; color: #17171a; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">{{ $siteName }}</div>
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p class="msg">{{ $message }}</p>
        <a class="btn" href="{{ url('/') }}">Zur Startseite</a>
    </div>
</body>
</html>
