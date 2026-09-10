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
    {{--
        Con «black-translucent» la web ocupa la pantalla entera y se pinta por
        debajo del reloj; el hueco para no comérselo lo deja safe-top en la
        cabecera. Con «default» iOS se queda la barra de estado y le da a la
        web lo que sobra.

        OJO: iOS lee esto UNA vez, al añadir la aplicación a la pantalla de
        inicio, y no lo vuelve a mirar. Cambiarlo aquí no toca las
        instalaciones que ya existen: hay que borrarla y volver a añadirla.
        Eso costó una tarde de diagnósticos sobre una instalación que llevaba
        una versión distinta de la que estaba desplegada.
    --}}
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
{{--
    EL ARMAZÓN. En móvil la aplicación ocupa la ventana y no se desplaza; lo
    que rueda es el contenido de dentro (el <main>), y la barra inferior es el
    último hijo de la columna, no un elemento flotante.

    La altura es h-app y no h-dvh, y ése es el arreglo de verdad: en la app
    instalada iOS reporta mal la ventana cuando la página es corta (873 en una
    pantalla de 932) y 100dvh hereda ese error; 100vh no. El porqué, con los
    bugs de WebKit, está en app.css junto a la utilidad.

    El body es blanco y el gris lo pone el contenedor de dentro: lo que iOS
    pinte fuera de la página toma el color del body, y así casa con la barra.

    En escritorio se deshace entero: la página vuelve a desplazarse como
    siempre y la navegación es la columna lateral.
--}}
<body class="h-app overflow-hidden bg-white lg:h-auto lg:min-h-dvh lg:overflow-visible">
    <x-app-nav :group="$group ?? null" part="side" />

    {{-- El gris de la aplicación vive aquí y no en el body: mira el comentario
         de arriba. --}}
    <div class="flex h-full flex-col bg-neutral-50 lg:h-auto lg:min-h-dvh lg:block lg:pl-60">
        {{-- El margen de arriba NO es decorativo: la aplicación declara
             «black-translucent», así que en un iPhone instalado el contenido se
             pinta POR DEBAJO del reloj y la cabecera se comía la hora. El fondo
             de la barra sí sube hasta el borde; lo que baja es su contenido. --}}
        {{-- El desenfoque y el z-index sólo tienen sentido en escritorio, donde
             la cabecera es sticky y el contenido pasa por debajo. En móvil es un
             hijo estático de la columna: nada pasa por detrás. --}}
        <header class="vt-chrome safe-top shrink-0 border-b border-neutral-200 bg-white lg:sticky lg:top-0 lg:z-20 lg:bg-white/90 lg:backdrop-blur">
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

        {{--
            Aquí es donde rueda el contenido en móvil.

            El que rueda va a ANCHO COMPLETO y el ancho máximo lo pone el div de
            dentro. Si los dos fueran el mismo elemento —como estaban— en una
            tablet la zona que responde al dedo sería una columna estrecha en
            mitad de la pantalla, y arrastrar por los lados no haría nada.

            min-h-0 no es decorativo: un hijo de un contenedor flexible trae de
            fábrica min-height:auto, o sea que no encoge por debajo de su
            contenido. Sin esto crece y empuja en vez de rodar, y la pantalla no
            se puede bajar. Safari es estricto y Chrome lo perdona, así que no
            se ve probándolo en el escritorio.

            overscroll-contain: que el rebote se quede dentro del contenido y no
            se encadene al documento, que es lo que hacía saltar la ventana.
        --}}
        <main data-scroller class="min-h-0 w-full flex-1 overflow-y-auto overscroll-contain lg:flex-none lg:overflow-visible lg:overscroll-auto">
            <div class="mx-auto max-w-2xl px-4 py-4 lg:max-w-4xl lg:px-8 lg:py-8">
                <x-flash />
                <x-install-banner />

                {{ $slot }}
            </div>
        </main>

        <x-app-nav :group="$group ?? null" part="bottom" />
    </div>
</body>
</html>
