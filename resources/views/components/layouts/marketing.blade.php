<!DOCTYPE html>
<html lang="es" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Libro de Trayectos — las cuentas del coche, claras' }}</title>
    <meta name="description" content="Reparte los gastos de coche calculando lo que cuesta de verdad cada trayecto, desnivel incluido, con contabilidad por partida doble.">

    {{-- Esta es la página desde la que la gente va a instalar la aplicación, así
         que necesita las mismas metas de Apple que el resto: sin ellas, iOS puede
         abrir el icono de la pantalla de inicio con la interfaz del navegador. --}}
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="theme-color" content="#171717">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Trayectos">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('/icons/apple-touch-icon.png') }}">
    <link rel="icon" href="{{ url('/icons/icon-192.png') }}" sizes="192x192">

    {{-- Instrument Sans estaba declarada en app.css y el plugin de Vite ya la
         descargaba al build, pero nadie la enlazaba: la aplicación llevaba desde
         el principio cayendo a la fuente del sistema. Vite::fonts() la sirve
         desde nuestro propio dominio, sin pedir nada a un CDN ajeno. --}}
    {{ Vite::fonts() }}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Sin la reserva para la barra inferior: aquí no hay navegación de aplicación --}}
<body class="min-h-full bg-neutral-50" style="padding-bottom: 0">

<header class="vt-chrome sticky top-0 z-30 border-b border-neutral-200/80 bg-white/85 backdrop-blur-md">
    <nav class="mx-auto flex max-w-5xl items-center gap-4 px-5 py-3" aria-label="Principal">
        <a href="{{ route('home') }}" class="text-neutral-900" aria-label="Libro de Trayectos — inicio">
            <x-logo :wordmark="true" size="size-9" />
        </a>

        <div class="ml-auto hidden items-center gap-7 text-sm text-neutral-600 md:flex">
            <a href="#como-funciona" class="link-grow transition-colors hover:text-neutral-900">Cómo funciona</a>
            <a href="#coste" class="link-grow transition-colors hover:text-neutral-900">El coste real</a>
            <a href="#cuentas" class="link-grow transition-colors hover:text-neutral-900">Las cuentas</a>
            <a href="#datos" class="link-grow transition-colors hover:text-neutral-900">De dónde salen los datos</a>
        </div>

        {{-- group para que la flecha avance al pasar el ratón por el botón --}}
        <a href="{{ route('login') }}" class="btn-primary group ml-auto shrink-0 md:ml-0">
            Área cliente
            <svg class="size-4 transition-transform duration-200 ease-out group-hover:translate-x-0.5"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12l-7.5 7.5M21 12H3"/>
            </svg>
        </a>
    </nav>
</header>

{{ $slot }}

<footer class="border-t border-neutral-200 bg-white">
    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-5 py-10 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3 text-neutral-900">
            <x-logo size="size-9" />
            <div class="text-sm">
                <p class="font-semibold">Libro de Trayectos</p>
                <p class="text-neutral-500">Proyecto propio de Ismael de la Rosa Guerrero</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-neutral-600">
            <a href="https://github.com/ide-la-r/Trayectos" class="link-grow transition-colors hover:text-neutral-900">Código en GitHub</a>
            <a href="{{ route('login') }}" class="link-grow transition-colors hover:text-neutral-900">Entrar</a>
            <a href="{{ route('register') }}" class="link-grow transition-colors hover:text-neutral-900">Crear cuenta</a>
        </div>
    </div>
</footer>

</body>
</html>
