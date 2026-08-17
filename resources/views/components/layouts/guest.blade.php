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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- El layout invitado no tiene barra inferior, así que no necesita hueco --}}
<body class="min-h-full" style="padding-bottom: 0">
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-neutral-900 text-2xl">
                🚗
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">Libro de Trayectos</h1>
            <p class="mt-1 text-sm text-neutral-500">Las cuentas del coche, claras y sin discusiones.</p>
        </div>

        <x-flash />

        {{ $slot }}
    </div>
</body>
</html>
