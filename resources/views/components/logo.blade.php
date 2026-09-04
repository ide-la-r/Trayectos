@props([
    'wordmark' => false,
    'size' => 'size-10',
])

{{--
    La marca es la misma carretera en perspectiva que dibujan los iconos de la
    PWA (app/Console/Commands/GenerateIcons.php): un trapecio que se estrecha
    hacia el horizonte, la línea discontinua acortándose y el sol al fondo.

    El asfalto, la línea y el sol salen del MISMO currentColor con distinta
    opacidad, así que la marca funciona sobre cualquier fondo: la placa la pone
    el contenedor y el trazo hereda el color del texto.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="{{ $size }} grid shrink-0 place-items-center overflow-hidden rounded-[28%] bg-neutral-900 text-neutral-50">
        <svg viewBox="0 0 48 48" class="size-full" fill="currentColor" aria-hidden="true">
            {{-- Sol sobre el horizonte --}}
            <circle cx="24" cy="9.2" r="5.4" opacity="0.5"/>

            {{-- Asfalto --}}
            <path d="M6 45 L42 45 L28.6 14.6 L19.4 14.6 Z" opacity="0.34"/>

            {{-- Línea discontinua: ancha en primer plano, estrechándose al fondo --}}
            <path d="M22.4 45 L25.6 45 L25.25 38.4 L22.75 38.4 Z"/>
            <path d="M22.85 35.4 L25.15 35.4 L24.92 29.9 L23.08 29.9 Z"/>
            <path d="M23.15 27.4 L24.85 27.4 L24.7 23.1 L23.3 23.1 Z"/>
            <path d="M23.38 20.9 L24.62 20.9 L24.52 17.7 L23.48 17.7 Z"/>
            <path d="M23.55 16.1 L24.45 16.1 L24.4 14.6 L23.6 14.6 Z"/>
        </svg>
    </span>

    @if ($wordmark)
        {{-- En pantallas muy estrechas se queda solo la marca: con el nombre al
             lado, el botón de la barra superior se salía del ancho. --}}
        <span class="hidden text-[0.95rem] leading-tight font-semibold tracking-tight whitespace-nowrap sm:block">
            Libro de <span class="block">Trayectos</span>
        </span>
    @endif
</span>
