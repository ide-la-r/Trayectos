@props(['profile'])

@php
    $puntos = $profile->points;
    $rango = max(1, $profile->max_m - $profile->min_m);
    $total = max(0.001, $profile->distance_km);

    /*
     * El viewBox es 700x160 y se estira al ancho disponible con
     * preserveAspectRatio="none". Por eso aquí dentro NO va ni una letra ni un
     * círculo: se deformarían a lo ancho. Las etiquetas van en HTML debajo.
     */
    $x = fn (float $km) => round($km / $total * 700, 2);
    $y = fn (int $m) => round(150 - ($m - $profile->min_m) / $rango * 130, 2);

    $linea = $puntos->map(fn ($p) => $x($p->km).','.$y($p->m))->implode(' ');
    // El área cierra contra el suelo del dibujo para que se lea como terreno
    $area = '0,158 '.$linea.' 700,158';

    $metros = fn (int $m) => number_format($m, 0, ',', '.');
    $kms = fn (float $km) => number_format($km, 1, ',', '.');
@endphp

<figure {{ $attributes }}>
    {{-- En ida y vuelta se dibuja sólo la ida, y hay que decirlo: la tarjeta de
         arriba enseña el doble de kilómetros y si no parecen dos viajes. --}}
    <figcaption class="mb-2 text-xs text-neutral-500">
        @if ($profile->round_trip)
            Perfil de la ida · {{ $kms($profile->distance_km) }} km (la vuelta es la misma al revés)
        @else
            Perfil del recorrido
        @endif
    </figcaption>

    <svg viewBox="0 0 700 160" class="h-40 w-full" preserveAspectRatio="none" role="img"
         aria-label="Perfil de altitud: sale a {{ $metros($profile->start_m) }} metros, sube hasta {{ $metros($profile->max_m) }} en el kilómetro {{ $kms($profile->peak->km) }} y llega a {{ $metros($profile->end_m) }}.">
        <polygon points="{{ $area }}" fill="currentColor" class="text-neutral-200"/>

        <polyline points="{{ $linea }}" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linejoin="round" stroke-linecap="round"
                  vector-effect="non-scaling-stroke" class="text-neutral-900"/>

        {{-- El punto más alto, marcado con una vertical: un círculo saldría
             aplastado con este preserveAspectRatio --}}
        <line x1="{{ $x($profile->peak->km) }}" y1="{{ $y($profile->peak->m) }}"
              x2="{{ $x($profile->peak->km) }}" y2="158"
              stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 3"
              vector-effect="non-scaling-stroke" class="text-neutral-400"/>
    </svg>

    {{-- Tres columnas y no un flex con justify-between: con el ancho de un móvil
         el del medio es el más largo y la fila se partía dejando «Llegada»
         solo en la línea de abajo, como si sobrara. --}}
    <div class="mt-2 grid grid-cols-3 gap-x-2 text-xs text-neutral-500 tabular-nums">
        <span>Salida<br>{{ $metros($profile->start_m) }} m</span>
        <span class="text-center font-medium text-neutral-700">
            Más alto {{ $metros($profile->max_m) }} m<br>
            <span class="font-normal text-neutral-400">km {{ $kms($profile->peak->km) }}</span>
        </span>
        <span class="text-right">Llegada<br>{{ $metros($profile->end_m) }} m</span>
    </div>
</figure>
