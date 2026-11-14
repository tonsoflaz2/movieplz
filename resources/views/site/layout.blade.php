{{--
    Plain web pages the App Store listing links to (/privacy, /support).
    Not HAM: these are read in a browser.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · MoviePlz</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font: 17px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #fff; color: #1f2937; }
        header { background: #dc2626; color: #fff; padding: 28px 20px; }
        header a { color: #fff; text-decoration: none; font-weight: 700; font-size: 22px; }
        main { max-width: 720px; margin: 0 auto; padding: 24px 20px 64px; }
        h1 { font-size: 30px; margin: 8px 0 4px; }
        h2 { font-size: 20px; margin: 32px 0 8px; }
        .muted { color: #6b7280; }
        a { color: #b91c1c; }
        @media (prefers-color-scheme: dark) {
            body { background: #111; color: #e5e7eb; }
            .muted { color: #9ca3af; }
            a { color: #fca5a5; }
        }
    </style>
</head>
<body>
<header><a href="/support">MoviePlz</a></header>
<main>
    @yield('content')
</main>
</body>
</html>
