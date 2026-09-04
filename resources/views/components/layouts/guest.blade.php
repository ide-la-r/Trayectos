<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Libro de Trayectos' }}</title>

    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="theme-color" content="#171717">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Trayectos">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('/icons/apple-touch-icon.png') }}">
    <link rel="icon" href="{{ url('/icons/icon-192.png') }}" sizes="192x192">

    {{-- Fuentes autoalojadas: las descarga el plugin de Vite al construir --}}
    {{ Vite::fonts() }}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- El layout invitado no tiene barra inferior, así que no necesita hueco --}}
<body class="min-h-full bg-white" style="padding-bottom: 0">

{{-- Franja de asfalto: la identidad de la marca sin gastar ninguna imagen --}}
<div class="relative isolate overflow-hidden bg-neutral-950 text-neutral-50">
    {{-- Solo el asfalto: el sol quedaba cortado por el borde de la franja --}}
    <svg class="pointer-events-none absolute -bottom-20 left-1/2 -z-10 w-80 max-w-none -translate-x-1/2 opacity-[0.08]"
         viewBox="0 0 48 48" fill="currentColor" aria-hidden="true">
        <path d="M6 45 L42 45 L28.6 14.6 L19.4 14.6 Z"/>
    </svg>

    <div class="mx-auto max-w-md px-5 pt-10 pb-16 text-center">
        <a href="{{ route('home') }}" class="inline-flex" aria-label="Volver a la portada">
            <span class="grid size-14 place-items-center overflow-hidden rounded-[28%] bg-white/10 ring-1 ring-white/15">
                <svg viewBox="0 0 48 48" class="size-full" fill="currentColor" aria-hidden="true">
                    <circle cx="24" cy="9.2" r="5.4" opacity="0.5"/>
                    <path d="M6 45 L42 45 L28.6 14.6 L19.4 14.6 Z" opacity="0.34"/>
                    <path d="M22.4 45 L25.6 45 L25.25 38.4 L22.75 38.4 Z"/>
                    <path d="M22.85 35.4 L25.15 35.4 L24.92 29.9 L23.08 29.9 Z"/>
                    <path d="M23.15 27.4 L24.85 27.4 L24.7 23.1 L23.3 23.1 Z"/>
                    <path d="M23.38 20.9 L24.62 20.9 L24.52 17.7 L23.48 17.7 Z"/>
                    <path d="M23.55 16.1 L24.45 16.1 L24.4 14.6 L23.6 14.6 Z"/>
                </svg>
            </span>
        </a>

        <h1 class="mt-5 text-2xl font-semibold tracking-tight">{{ $heading ?? 'Libro de Trayectos' }}</h1>
        <p class="mt-2 text-sm text-neutral-400">{{ $tagline ?? 'Las cuentas del coche, claras y sin discusiones.' }}</p>
    </div>
</div>

{{-- La tarjeta monta sobre la franja oscura --}}
<div class="relative z-10 mx-auto -mt-10 flex max-w-md flex-col px-5 pb-12">
    <x-flash />

    {{ $slot }}

    <p class="mt-8 text-center text-xs text-neutral-400">
        <a href="{{ route('home') }}" class="transition hover:text-neutral-600">Qué es Libro de Trayectos</a>
    </p>
</div>

</body>
</html>
