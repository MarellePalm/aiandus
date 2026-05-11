<!DOCTYPE html>
<html lang="et" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>
        @PwaHead <!-- This includes the PWA meta tags -->
        <meta name="application-name" content="{{ config('app.name', 'Aiapäevik') }}">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Aiapäevik') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <title inertia>{{ config('app.name', 'Aiapäevik') }}</title>

        <link rel="icon" href="/favicon.png" type="image/png" sizes="64x64">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/logo.png" type="image/png">
        <link rel="apple-touch-icon" href="/logo.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <a class="skip-to-main" href="#main">Liigu põhisisu juurde</a>
        <main id="main" tabindex="-1">
            @inertia
        </main>
        @RegisterServiceWorkerScript <!-- This registers the service worker -->
    </body>
</html>
