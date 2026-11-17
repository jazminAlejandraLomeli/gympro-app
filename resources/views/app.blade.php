<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        {{-- Barra del navegador móvil con el color del gimnasio --}}
        <meta name="theme-color" content="{{ $brand->primary }}">

        <title inertia>{{ $brand->name }}</title>

        @if ($brand->faviconUrl)
            <link rel="icon" href="{{ $brand->faviconUrl }}">
        @endif

        {{-- Marca blanca: colores sanitizados (#RRGGBB) en App\Support\Brand --}}
        <style>
            :root {
                @foreach ($brand->cssVariables() as $var => $value)
                    {{ $var }}: {{ $value }};
                @endforeach
            }
        </style>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body class="min-h-dvh bg-slate-50 font-sans text-slate-900 antialiased">
        @inertia
    </body>
</html>
