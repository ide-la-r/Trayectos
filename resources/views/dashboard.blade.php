@php
    $tieneGrupo = $groups->isNotEmpty();
    $tieneCoche = $vehicles->isNotEmpty();
    $hechos = ($tieneGrupo ? 1 : 0) + ($tieneCoche ? 1 : 0);

    // El estado inicial eran dos tarjetas sueltas que no se explicaban entre
    // ellas. Como los tres pasos son dependientes —sin grupo y sin coche no hay
    // viaje que apuntar— se presentan como una secuencia con su progreso real.
    $pasos = [
        [
            'titulo' => 'Crea tu grupo o únete a uno',
            'texto' => 'Un grupo es un libro de cuentas compartido: los viajes que apuntéis dentro se reparten entre sus miembros.',
            'hecho' => $tieneGrupo,
            'acciones' => [
                ['texto' => 'Crear un grupo', 'url' => route('groups.create'), 'clase' => 'btn-primary'],
                ['texto' => 'Tengo un código', 'url' => route('groups.join'), 'clase' => 'btn-secondary'],
            ],
        ],
        [
            'titulo' => 'Apunta tu coche',
            'texto' => 'Con su consumo homologado y su tecnología. Es lo que permite calcular el coste real de cada trayecto y sugerirte como conductor.',
            'hecho' => $tieneCoche,
            'acciones' => [
                ['texto' => 'Añadir mi coche', 'url' => route('vehicles.create'), 'clase' => 'btn-primary'],
            ],
        ],
        [
            'titulo' => 'Apunta el primer viaje',
            'texto' => 'Origen, destino y quién iba. La distancia, el desnivel y el precio del carburante los pone la aplicación.',
            'hecho' => false,
            'acciones' => $tieneGrupo
                ? [['texto' => 'Apuntar un viaje', 'url' => route('trips.create', $groups->first()->group), 'clase' => 'btn-primary']]
                : [],
        ],
    ];
@endphp

<x-layouts.app title="Panel · Libro de Trayectos" heading="Tus grupos">

    @if ($tieneGrupo)
        {{-- Rejilla en escritorio: en una sola columna cada tarjeta se estiraba
             hasta 900 px para mostrar un nombre y un saldo. --}}
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($groups as $row)
                <a href="{{ route('groups.show', $row->group) }}"
                   class="card flex items-center justify-between gap-4 transition hover:border-neutral-300 hover:shadow-md">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-neutral-900">{{ $row->group->name }}</p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            {{ $row->balance_cents > 0 ? 'Te deben' : ($row->balance_cents < 0 ? 'Debes' : 'Estás a cero') }}
                        </p>
                    </div>
                    <x-money :cents="$row->balance_cents" signed coloured class="text-lg" />
                </a>
            @endforeach
        </div>

        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('groups.create') }}" class="btn-secondary flex-1">Crear otro grupo</a>
            <a href="{{ route('groups.join') }}" class="btn-secondary flex-1">Unirme con un código</a>
        </div>
    @endif

    @if (! $tieneGrupo || ! $tieneCoche)
        <section class="{{ $tieneGrupo ? 'mt-8' : '' }}">
            {{-- Cabecera del arranque, con el asfalto de la marca de fondo --}}
            <div class="relative isolate overflow-hidden rounded-2xl bg-neutral-950 px-6 py-8 text-neutral-50 sm:px-8">
                <svg class="pointer-events-none absolute -right-10 -bottom-24 -z-10 w-72 max-w-none opacity-[0.09]"
                     viewBox="0 0 48 48" fill="currentColor" aria-hidden="true">
                    <path d="M6 45 L42 45 L28.6 14.6 L19.4 14.6 Z"/>
                </svg>

                <p class="font-mono text-xs tracking-[0.14em] text-neutral-400 uppercase">
                    Paso {{ min($hechos + 1, 3) }} de 3
                </p>
                <h2 class="mt-3 max-w-lg text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                    @if (! $tieneGrupo)
                        Vamos a dejarlo listo en tres pasos
                    @else
                        Ya casi: te falta apuntar tu coche
                    @endif
                </h2>
                <p class="mt-3 max-w-md text-sm leading-relaxed text-neutral-300">
                    Después, apuntar un viaje son treinta segundos y las cuentas se llevan solas.
                </p>

                <div class="mt-6 flex max-w-xs gap-1.5" role="img"
                     aria-label="{{ $hechos }} de 3 pasos completados">
                    @foreach ($pasos as $paso)
                        <span @class([
                            'h-1.5 flex-1 rounded-full',
                            'bg-neutral-50' => $paso['hecho'],
                            'bg-neutral-700' => ! $paso['hecho'],
                        ])></span>
                    @endforeach
                </div>
            </div>

            <ol class="mt-4 grid gap-3 lg:grid-cols-3">
                @foreach ($pasos as $i => $paso)
                    <li @class([
                        'card flex flex-col p-5',
                        'border-credit-500/40 bg-credit-50/40' => $paso['hecho'],
                    ])>
                        <div class="flex items-center gap-2.5">
                            @if ($paso['hecho'])
                                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-credit-500 text-white">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                    </svg>
                                </span>
                                <span class="text-xs font-semibold tracking-wide text-credit-700 uppercase">Hecho</span>
                            @else
                                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-neutral-900 font-mono text-xs text-white">
                                    {{ $i + 1 }}
                                </span>
                                <span class="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Pendiente</span>
                            @endif
                        </div>

                        <h3 class="mt-3 font-semibold text-neutral-900">{{ $paso['titulo'] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-neutral-600">{{ $paso['texto'] }}</p>

                        @if (! $paso['hecho'] && $paso['acciones'])
                            <div class="mt-4 flex flex-wrap gap-2 pt-1">
                                @foreach ($paso['acciones'] as $accion)
                                    <a href="{{ $accion['url'] }}" class="{{ $accion['clase'] }}">{{ $accion['texto'] }}</a>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

</x-layouts.app>
