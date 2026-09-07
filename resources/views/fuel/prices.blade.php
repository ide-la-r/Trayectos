@php
    use Illuminate\Support\Number;

    $money = fn (?int $milli) => $milli === null
        ? '—'
        : number_format($milli / 1000, 3, ',', '.');

    // Geometría de la línea de tendencia. Se calcula aquí y no en JavaScript
    // porque el dato ya viene con la página: pintarlo en el cliente sólo
    // añadiría un parpadeo.
    $serie = $summary?->series ?? collect();
    $puntos = collect();

    if ($serie->count() >= 2) {
        $valores = $serie->pluck('avg_milli');
        $min = (int) $valores->min();
        $max = (int) $valores->max();
        $rango = max(1, $max - $min);

        // El viewBox es 700x100 y no 100x100 por una razón concreta: con
        // preserveAspectRatio="none" el dibujo se estira al ancho disponible, y
        // en una caja de ~760x112 px un viewBox cuadrado deforma los círculos
        // casi siete veces a lo ancho: el punto del extremo salía como una
        // raya. Con esta proporción las dos escalas quedan casi iguales.
        //
        // Y los márgenes de 8 y 4 dejan aire para que el punto del extremo no
        // se corte contra el borde.
        $puntos = $serie->values()->map(fn ($d, $i) => (object) [
            'x' => round(8 + ($i / max(1, $serie->count() - 1)) * 684, 2),
            'y' => round(96 - (($d->avg_milli - $min) / $rango) * 88, 2),
            'dato' => $d,
        ]);
    }
@endphp

<x-layouts.app title="Precios · Libro de Trayectos"
               heading="Precios del carburante"
               :subheading="$summary?->kind->label()">

    @if (! $kind)
        {{-- Sin datos no hay pantalla: decirlo y explicar de qué depende --}}
        <div class="card p-6 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-full bg-neutral-100 text-neutral-500">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                </svg>
            </span>
            <h2 class="mt-3 font-semibold text-neutral-900">Todavía no hay precios sincronizados</h2>
            <p class="mx-auto mt-1.5 max-w-md text-sm leading-relaxed text-neutral-600">
                Los precios oficiales los descarga una tarea programada cuatro veces al día. En cuanto
                corra la primera vez, aquí aparecerá el histórico de las provincias configuradas.
            </p>
        </div>
    @else
        {{-- ─── Selector de carburante ─────────────────────────────────────── --}}
        @if ($available->count() > 1)
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($available as $option)
                    <a href="{{ route('prices', ['carburante' => $option->value]) }}"
                       @class([
                           'rounded-xl border px-3 py-1.5 text-sm font-medium transition',
                           'border-neutral-900 bg-neutral-900 text-white' => $option === $kind,
                           'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300' => $option !== $kind,
                       ])>
                        {{ $option->label() }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- ─── Número protagonista y tendencia ────────────────────────────── --}}
        <div class="card p-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase">
                        Más barato de la zona
                    </p>
                    <p class="mt-1 flex items-baseline gap-1.5">
                        <span class="text-4xl font-semibold tracking-tight text-neutral-900 tabular-nums">
                            {{ $money($summary->min_milli) }}
                        </span>
                        <span class="text-sm text-neutral-500">€/{{ $kind->unit() }}</span>
                    </p>
                    <p class="mt-1 text-sm text-neutral-500">
                        Media de la zona {{ $money($summary->avg_milli) }} €
                        @if ($summary->latest)
                            · {{ $summary->latest->stations }}
                            {{ $summary->latest->stations === 1 ? 'estación' : 'estaciones' }}
                        @endif
                    </p>
                </div>

                {{-- La dirección no la carga el color: van icono y texto --}}
                <div @class([
                    'flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium',
                    'bg-debt-50 text-debt-700' => $summary->direction === 'up',
                    'bg-credit-50 text-credit-700' => $summary->direction === 'down',
                    'bg-neutral-100 text-neutral-600' => in_array($summary->direction, ['flat', 'unknown'], true),
                ])>
                    @if ($summary->direction === 'up')
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75 12 8.25l7.5 7.5"/>
                        </svg>
                        Sube
                    @elseif ($summary->direction === 'down')
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25 12 15.75l-7.5-7.5"/>
                        </svg>
                        Baja
                    @elseif ($summary->direction === 'flat')
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                        </svg>
                        Estable
                    @else
                        Midiendo
                    @endif

                    @if ($summary->change_pct !== null)
                        <span class="tabular-nums">{{ $summary->change_pct > 0 ? '+' : '' }}{{ number_format($summary->change_pct, 1, ',', '.') }} %</span>
                    @endif
                </div>
            </div>

            <p class="mt-3 text-sm text-neutral-700">{{ $summary->verdict }}</p>

            {{-- ─── Línea de tendencia ─────────────────────────────────────── --}}
            @if ($puntos->count() >= 2)
                @php
                    $primero = $puntos->first();
                    $ultimo = $puntos->last();
                    $masBarato = $puntos->sortBy(fn ($p) => $p->dato->avg_milli)->first();
                    $masCaro = $puntos->sortByDesc(fn ($p) => $p->dato->avg_milli)->first();
                @endphp

                <figure class="mt-5">
                    <figcaption class="mb-2 text-xs text-neutral-500">
                        Media diaria de la zona · últimos {{ $serie->count() }}
                        {{ $serie->count() === 1 ? 'día' : 'días' }} con datos
                    </figcaption>

                    <svg viewBox="0 0 700 100" class="h-28 w-full" preserveAspectRatio="none"
                         role="img" aria-label="Evolución de la media diaria del precio">
                        {{-- Rejilla discreta: tres líneas, sin números encima --}}
                        @foreach ([8, 52, 96] as $y)
                            <line x1="0" y1="{{ $y }}" x2="700" y2="{{ $y }}"
                                  stroke="currentColor" stroke-width="0.4" class="text-neutral-200"
                                  vector-effect="non-scaling-stroke"/>
                        @endforeach

                        <polyline fill="none" stroke="currentColor" stroke-width="2"
                                  stroke-linecap="round" stroke-linejoin="round"
                                  vector-effect="non-scaling-stroke"
                                  class="text-neutral-900"
                                  points="{{ $puntos->map(fn ($p) => "{$p->x},{$p->y}")->implode(' ') }}"/>

                        {{-- Un punto por día con su título nativo: da información
                             al pasar el ratón sin montar un tooltip propio --}}
                        @foreach ($puntos as $p)
                            <circle cx="{{ $p->x }}" cy="{{ $p->y }}" r="4"
                                    class="text-neutral-400" fill="currentColor">
                                <title>{{ \Carbon\Carbon::parse($p->dato->day)->format('d/m') }}: {{ $money($p->dato->avg_milli) }} €</title>
                            </circle>
                        @endforeach

                        {{-- El extremo, marcado: es el dato de hoy --}}
                        <circle cx="{{ $ultimo->x }}" cy="{{ $ultimo->y }}" r="6"
                                class="text-neutral-900" fill="currentColor"
                                stroke="white" stroke-width="1.5"/>
                    </svg>

                    {{-- Sólo tres etiquetas: mínimo, máximo y hoy. Un número en
                         cada punto convertiría la línea en una tabla ilegible. --}}
                    <div class="mt-2 flex flex-wrap justify-between gap-x-6 gap-y-1 text-xs text-neutral-500 tabular-nums">
                        <span>
                            Mínimo {{ $money($masBarato->dato->avg_milli) }} €
                            <span class="text-neutral-400">({{ \Carbon\Carbon::parse($masBarato->dato->day)->format('d/m') }})</span>
                        </span>
                        <span>
                            Máximo {{ $money($masCaro->dato->avg_milli) }} €
                            <span class="text-neutral-400">({{ \Carbon\Carbon::parse($masCaro->dato->day)->format('d/m') }})</span>
                        </span>
                        <span class="font-medium text-neutral-700">
                            Hoy {{ $money($ultimo->dato->avg_milli) }} €
                        </span>
                    </div>

                    {{-- Los números en tabla: la línea no es la única forma de
                         llegar al dato --}}
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs text-neutral-500 hover:text-neutral-700">
                            Ver los números
                        </summary>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full min-w-[18rem] text-xs">
                                <thead>
                                    <tr class="border-b border-neutral-200 text-left text-neutral-500">
                                        <th class="py-1.5 pr-3 font-medium">Día</th>
                                        <th class="py-1.5 pr-3 font-medium">Más barato</th>
                                        <th class="py-1.5 pr-3 font-medium">Media</th>
                                        <th class="py-1.5 font-medium">Estaciones</th>
                                    </tr>
                                </thead>
                                <tbody class="tabular-nums text-neutral-700">
                                    @foreach ($serie->reverse() as $d)
                                        <tr class="border-b border-neutral-100 last:border-0">
                                            <td class="py-1.5 pr-3">{{ \Carbon\Carbon::parse($d->day)->format('d/m/Y') }}</td>
                                            <td class="py-1.5 pr-3">{{ $money($d->min_milli) }} €</td>
                                            <td class="py-1.5 pr-3">{{ $money($d->avg_milli) }} €</td>
                                            <td class="py-1.5">{{ $d->stations }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </figure>
            @endif
        </div>

        {{-- ─── Dónde está más barato ──────────────────────────────────────── --}}
        @if ($cheapest->isNotEmpty())
            <section class="mt-4">
                <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">Dónde está más barato ahora</h2>

                <div class="card divide-y divide-neutral-100 p-0">
                    @foreach ($cheapest as $i => $station)
                        <div class="flex items-center gap-3 px-4 py-3">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-neutral-100 font-mono text-xs text-neutral-600">
                                {{ $i + 1 }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-neutral-900">
                                    {{ $station->label ?: 'Estación sin rótulo' }}
                                </p>
                                <p class="truncate text-xs text-neutral-500">
                                    {{ $station->municipality }}
                                    @if ($station->address)
                                        · {{ $station->address }}
                                    @endif
                                </p>
                            </div>

                            <p class="shrink-0 text-right">
                                <span class="money text-sm text-neutral-900 tabular-nums">{{ $money($station->price_milli) }} €</span>
                                <span class="block text-xs text-neutral-400">
                                    @php $visto = \Carbon\Carbon::parse($station->observed_at); @endphp
                                    {{-- La marca de tiempo es la del Ministerio, no la nuestra: si su
                                         reloj va por delante, diffForHumans diría «en 11 h», que no
                                         significa nada para quien lo lee. --}}
                                    {{ $visto->isFuture() ? 'ahora mismo' : $visto->diffForHumans(short: true) }}
                                </span>
                            </p>
                        </div>
                    @endforeach
                </div>

                <p class="mt-2 px-1 text-xs text-neutral-500">
                    Precios oficiales del Ministerio para la Transición Ecológica, de las provincias
                    sincronizadas ({{ implode(', ', $provinces) }}). El mapa llega en la próxima entrega.
                </p>
            </section>
        @endif
    @endif

</x-layouts.app>
