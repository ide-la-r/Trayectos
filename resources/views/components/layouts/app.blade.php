<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover para que la barra inferior respete el notch de iOS --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Libro de Trayectos' }}</title>

    {{-- Instalación como aplicación --}}
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="theme-color" content="#171717">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Trayectos">
    {{-- Safari ignora los iconos del manifest para la pantalla de inicio --}}
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('/icons/apple-touch-icon.png') }}">
    <link rel="icon" href="{{ url('/icons/icon-192.png') }}" sizes="192x192">

    {{-- Instrument Sans se declaraba en app.css y el plugin de Vite ya la
         descargaba al build, pero nadie la enlazaba: la aplicación llevaba desde
         el principio cayendo a la fuente del sistema. Vite::fonts() la sirve
         desde nuestro propio dominio, así que también funciona sin conexión. --}}
    {{ Vite::fonts() }}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full lg:pb-0">
    {{-- La navegación se pinta como columna lateral en escritorio y como barra
         inferior en móvil; el desplazamiento del contenido lo compensa lg:pl-60 --}}
    <x-app-nav :group="$group ?? null" />

    <div class="lg:pl-60">
        {{-- El margen de arriba NO es decorativo: la aplicación declara
             «black-translucent», así que en un iPhone instalado el contenido se
             pinta POR DEBAJO del reloj y la cabecera se comía la hora. El fondo
             de la barra sí sube hasta el borde; lo que baja es su contenido. --}}
        <header class="vt-chrome safe-top sticky top-0 z-20 border-b border-neutral-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-2xl items-center gap-3 px-4 py-3 lg:max-w-4xl lg:px-8 lg:py-4">
                {{-- En escritorio la marca ya está en la columna lateral --}}
                <a href="{{ route('dashboard') }}" class="shrink-0 text-neutral-900 lg:hidden" aria-label="Ir al panel">
                    <x-logo size="size-8" />
                </a>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-base font-semibold text-neutral-900 lg:text-xl">{{ $heading ?? 'Libro de Trayectos' }}</p>
                    @isset($subheading)
                        <p class="truncate text-xs text-neutral-500 lg:text-sm">{{ $subheading }}</p>
                    @endisset
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @isset($actions)
                        {{ $actions }}
                    @endisset

                    {{-- En escritorio, salir vive al final de la columna lateral --}}
                    <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                        @csrf
                        <button type="submit" class="rounded-lg p-2 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700"
                                title="Salir" aria-label="Salir">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18.75 15 21.75 12m0 0-3-3m3 3H9"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-4 lg:max-w-4xl lg:px-8 lg:py-8">
            <x-flash />
            <x-install-banner />

            {{ $slot }}
        </main>
    </div>
</body>
</html>
