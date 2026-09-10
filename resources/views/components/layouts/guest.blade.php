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
{{--
    En móvil: franja de asfalto arriba y la tarjeta montando sobre ella.
    En pantalla grande: dos columnas de altura completa. El hueco enorme que
    quedaba en el escritorio no era falta de contenido, era un diseño de móvil
    estirado a 1920 px; repartir el ancho lo elimina en lugar de rellenarlo.
--}}
<body class="min-h-full bg-neutral-50 lg:grid lg:min-h-screen lg:grid-cols-2" style="padding-bottom: 0">

{{-- ── Panel de marca ─────────────────────────────────────────────────────── --}}
<div class="relative isolate flex flex-col overflow-hidden bg-neutral-950 text-neutral-50 lg:justify-between lg:p-12">
    {{-- Las curvas de nivel de la marca, muy tenues, como en la portada --}}
    <svg class="pointer-events-none absolute -bottom-20 left-1/2 -z-10 w-80 max-w-none -translate-x-1/2 opacity-[0.09] lg:-bottom-40 lg:left-auto lg:right-0 lg:w-[34rem] lg:translate-x-1/4"
         viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2"
         stroke-linecap="round" aria-hidden="true">
        <path d="M14 34.4 Q 24 18.4 34 34.4" opacity="0.55"/>
        <path d="M9 29.4 Q 24 8.4 39 29.4"/>
        <path d="M4 24.4 Q 24 -1.6 44 24.4" opacity="0.3"/>
    </svg>

    {{-- safe-top: instalada en el iPhone, esta franja se pinta por debajo del
         reloj y el nombre se le montaba encima --}}
    <div class="safe-top mx-auto max-w-md px-5 pt-10 pb-16 text-center lg:mx-0 lg:max-w-none lg:px-0 lg:pt-0 lg:pb-0 lg:text-left">
        {{-- Con el componente y NO con una copia del dibujo: aquí había una, y
             al cambiar la marca se quedó con la vieja mientras el resto de la
             aplicación ya llevaba la nueva. --}}
        <a href="{{ route('home') }}" class="inline-flex" aria-label="Volver a la portada">
            <x-logo size="size-14" plate="bg-white/10 ring-1 ring-white/15 text-neutral-50" />
        </a>

        <h1 class="mt-5 text-2xl font-semibold tracking-tight lg:mt-10 lg:max-w-md lg:text-4xl lg:leading-[1.1] lg:text-balance">
            {{ $heading ?? 'Libro de Trayectos' }}
        </h1>
        <p class="mt-2 text-sm text-neutral-400 lg:mt-4 lg:max-w-sm lg:text-base lg:leading-relaxed">
            {{ $tagline ?? 'Las cuentas del coche, claras y sin discusiones.' }}
        </p>
    </div>

    {{-- Las cifras solo en escritorio: en móvil competirían con el formulario --}}
    <dl class="hidden lg:grid lg:max-w-md lg:grid-cols-3 lg:gap-6 lg:border-t lg:border-neutral-800 lg:pt-8">
        <div>
            <dt class="text-2xl font-semibold tabular-nums">+50 %</dt>
            <dd class="mt-1 text-xs leading-snug text-neutral-400">de gasto extra en un puerto frente al llano</dd>
        </div>
        <div>
            <dt class="text-2xl font-semibold tabular-nums">226 m</dt>
            <dd class="mt-1 text-xs leading-snug text-neutral-400">es todo lo que un híbrido llega a recuperar</dd>
        </div>
        <div>
            <dt class="text-2xl font-semibold tabular-nums">0,00 €</dt>
            <dd class="mt-1 text-xs leading-snug text-neutral-400">suman las líneas de cada asiento</dd>
        </div>
    </dl>
</div>

{{-- ── Panel del formulario ───────────────────────────────────────────────── --}}
{{--
    El formulario se centra en la altura completa y el pie va anclado abajo. Con
    el pie como hermano en el flujo, su mt-auto se comía el espacio libre y el
    formulario quedaba pegado arriba; en rejilla, su fila desplazaba el centro.
--}}
<div class="relative z-10 flex flex-col lg:justify-center lg:px-10 xl:px-16">
    {{-- El -mt-10 es el solape de móvil; en escritorio no hay nada que solapar --}}
    <div class="mx-auto -mt-10 w-full max-w-md px-5 pb-12 lg:mt-0 lg:px-0 lg:pb-0">
        <x-flash />

        {{ $slot }}
    </div>

    <p class="hidden text-xs text-neutral-400 lg:absolute lg:bottom-8 lg:left-10 lg:block xl:left-16">
        <a href="{{ route('home') }}" class="transition hover:text-neutral-600">Qué es Libro de Trayectos</a>
        <span class="mx-2 text-neutral-300">·</span>
        <a href="https://github.com/ide-la-r/Trayectos" class="transition hover:text-neutral-600">Código en GitHub</a>
    </p>

    <p class="pb-10 text-center text-xs text-neutral-400 lg:hidden">
        <a href="{{ route('home') }}" class="transition hover:text-neutral-600">Qué es Libro de Trayectos</a>
    </p>
</div>

</body>
</html>
