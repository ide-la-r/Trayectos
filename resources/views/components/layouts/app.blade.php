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
        «black-translucent», y esta vez con los deberes hechos.

        Medido en el iPhone de Ismael con «default»: pantalla 932, ventana 873.
        iOS se queda la barra de estado —59 puntos— y le da a la web lo que
        sobra. Dentro de la web todo estaba bien (página 873, barra acabando en
        873, hueco 0), pero esos 59 puntos quedan FUERA y ahí la página no
        pinta: de ahí la franja.

        Con «black-translucent» la web ocupa los 932 enteros. No queda nada
        fuera, así que no hay sitio donde pueda salir una franja.

        Esto ya estaba antes y se quitó porque daba dos problemas. Los dos
        están arreglados desde entonces, y por eso se puede volver:

          · El reloj se montaba sobre la cabecera → safe-top, comprobado.
          · La página se quedaba más corta que la pantalla → min-h-dvh; antes
            era min-h-full, que al no tener altura <html> no resolvía a nada.

        OJO: iOS lee esto UNA vez, al añadir la aplicación a la pantalla de
        inicio, y no lo vuelve a mirar. Cambiarlo aquí no toca las
        instalaciones que ya existen: hay que borrarla y volver a añadirla.
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
    Dos cosas en esta línea, y las dos por la misma franja de abajo:

    min-h-dvh y no min-h-full. «full» es el 100 % de la altura del padre, y
    como <html> no tiene altura fija no resolvía a nada: la página se quedaba
    más corta que la pantalla.

    bg-white y no el gris de siempre. La franja que queda por debajo de la
    barra inferior en un iPhone instalado —la del indicador de inicio— la
    pinta el sistema POR FUERA de la web, y usa el color de fondo del body.
    Con el body gris quedaba una banda gris pegada a una barra blanca. Ahora
    el body es blanco y el gris lo pone el contenedor de dentro, así que el
    sistema rellena en blanco y la barra se funde con el borde.
--}}
{{--
    EL ARMAZÓN. En móvil la aplicación ocupa la ventana y NO se desplaza; lo
    que rueda es el contenido de dentro (el <main>).

    No es un capricho: en un iPhone instalado, iOS arranca dando una ventana 59
    puntos más corta que la pantalla —reserva el sitio de la barra de Safari
    aunque ahí no haya ninguna— y la agranda con el primer gesto de arrastrar.
    Con la barra inferior pegada al fondo de esa ventana, aparecía por encima
    del borde y bajaba sola al tocar. Medido en el móvil: ventana 873 sobre una
    pantalla de 932.

    Si el documento no se desplaza, iOS no cambia el tamaño de la ventana, y la
    barra —que ahora es el último hijo de esta columna— no se mueve nunca.

    En escritorio se deshace entero: la página vuelve a desplazarse como
    siempre y la navegación es la columna lateral.
--}}
<body class="h-dvh overflow-hidden bg-white lg:h-auto lg:min-h-dvh lg:overflow-visible">
    <x-app-nav :group="$group ?? null" part="side" />

    {{-- El gris de la aplicación vive aquí y no en el body: mira el comentario
         de arriba. --}}
    <div class="flex h-full flex-col bg-neutral-50 lg:h-auto lg:min-h-dvh lg:block lg:pl-60">
        {{-- El margen de arriba NO es decorativo: la aplicación declara
             «black-translucent», así que en un iPhone instalado el contenido se
             pinta POR DEBAJO del reloj y la cabecera se comía la hora. El fondo
             de la barra sí sube hasta el borde; lo que baja es su contenido. --}}
        <header class="vt-chrome safe-top z-20 shrink-0 border-b border-neutral-200 bg-white/90 backdrop-blur lg:sticky lg:top-0">
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

        {{-- Aquí es donde rueda el contenido en móvil. El desplazamiento suave
             de iOS se pide a mano: dentro de un contenedor no viene solo. --}}
        <main class="mx-auto w-full max-w-2xl flex-1 overflow-y-auto px-4 py-4 lg:max-w-4xl lg:flex-none lg:overflow-visible lg:px-8 lg:py-8"
              style="-webkit-overflow-scrolling: touch">
            <x-flash />
            <x-install-banner />

            {{ $slot }}
        </main>

        <x-app-nav :group="$group ?? null" part="bottom" />
    </div>
</body>
</html>
