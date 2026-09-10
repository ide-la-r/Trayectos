@props([
    'wordmark' => false,
    'size' => 'size-10',
    // La placa de detrás. Se puede cambiar porque sobre un fondo ya oscuro
    // —la franja del login— un cuadrado negro no se ve.
    'plate' => 'bg-neutral-900 text-neutral-50',
])

{{--
    La marca son dos curvas de nivel —un puerto visto en el mapa, no de frente—
    con la cima marcada. Es el mismo dibujo que generan los iconos de la PWA
    (app/Console/Commands/GenerateIcons.php): si se toca uno, hay que tocar el
    otro.

    Se eligió por encima de dibujar una carretera porque una ilustración
    pequeña acaba pareciendo un garabato, y esto se lee igual de bien a 24 px
    que a 512.

    Las curvas salen del MISMO currentColor con distinta opacidad, así que la
    marca funciona sobre cualquier fondo: la placa la pone el contenedor y el
    trazo hereda el color del texto. La cima es el único color fijo.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="{{ $size }} {{ $plate }} grid shrink-0 place-items-center overflow-hidden rounded-[28%]">
        <svg viewBox="0 0 48 48" class="size-full" fill="none" stroke="currentColor"
             stroke-width="3.4" stroke-linecap="round" aria-hidden="true">
            {{-- La de abajo, más tenue --}}
            <path d="M14 34.4 Q 24 18.4 34 34.4" opacity="0.45"/>
            <path d="M9 29.4 Q 24 8.4 39 29.4"/>
            <circle cx="24" cy="15" r="3" fill="#EE7203" stroke="none"/>
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
