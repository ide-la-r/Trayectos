@php
    $breakdown = data_get($trip->cost_inputs, 'breakdown', []);
    $inputs = data_get($trip->cost_inputs, 'inputs', []);
    $fuelPrice = data_get($trip->cost_inputs, 'fuel_price', []);
    $reversed = $trip->journalEntry?->isReversed() ?? false;
@endphp

<x-layouts.app :title="$trip->origin_label.' → '.$trip->destination_label"
               :heading="$trip->origin_label.' → '.$trip->destination_label"
               :subheading="$trip->travelled_on->format('d/m/Y').' · '.$trip->driver->user->name"
               :group="$group">

    @if ($reversed)
        <div class="mb-4 rounded-xl border border-debt-500/30 bg-debt-50 px-4 py-3 text-sm text-debt-700">
            Este viaje está anulado. Su asiento sigue en el libro junto al contrario que lo cancela.
        </div>
    @endif

    {{-- ─── Coste ─────────────────────────────────────────────────────────── --}}
    <section class="card mb-4">
        <div class="flex items-end justify-between">
            <div>
                <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase">Coste del viaje</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums text-neutral-900">
                    {{ \App\Support\Money::format($trip->total_cost_cents) }}
                </p>
            </div>
            <div class="text-right text-xs text-neutral-500">
                <p>{{ number_format($trip->distanceKm(), 1, ',', '.') }} km</p>
                <p>{{ number_format($trip->costPerKm(), 3, ',', '.') }} €/km</p>
            </div>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
            <div class="rounded-lg bg-neutral-50 px-3 py-2">
                <dt class="text-neutral-500">Subida</dt>
                <dd class="font-semibold text-neutral-900">↑ {{ number_format($trip->ascent_m, 0, ',', '.') }} m</dd>
            </div>
            <div class="rounded-lg bg-neutral-50 px-3 py-2">
                <dt class="text-neutral-500">Bajada</dt>
                <dd class="font-semibold text-neutral-900">↓ {{ number_format($trip->descent_m, 0, ',', '.') }} m</dd>
            </div>
            <div class="rounded-lg bg-neutral-50 px-3 py-2">
                <dt class="text-neutral-500">Por el desnivel</dt>
                <dd class="font-semibold text-neutral-900">
                    {{ ($breakdown['hill_surcharge_percent'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($breakdown['hill_surcharge_percent'] ?? 0, 1, ',', '.') }} %
                </dd>
            </div>
            <div class="rounded-lg bg-neutral-50 px-3 py-2">
                <dt class="text-neutral-500">Peso en carretera</dt>
                <dd class="font-semibold text-neutral-900">{{ number_format($breakdown['mass_kg'] ?? 0, 0, ',', '.') }} kg</dd>
            </div>
        </dl>
    </section>

        {{-- ─── Perfil del recorrido ──────────────────────────────────────────
         Va justo debajo del coste porque es lo que explica el «por el
         desnivel» de ahí arriba: se ve de dónde sale ese porcentaje. --}}
    @if ($profile)
        <section class="card mb-4">
            <x-route-profile :profile="$profile" />
            <x-route-map :geometry="$trip->route_geometry" />
        </section>
    @endif

    {{-- ─── Reparto ───────────────────────────────────────────────────────── --}}
    <section class="mb-4">
        <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">Cómo se ha repartido</h2>

        @if ($trip->journalEntry)
            <div class="card divide-y divide-neutral-100 p-0">
                @foreach ($trip->journalEntry->lines as $line)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-neutral-800">{{ $line->member->user->name }}</p>
                            <p class="text-xs text-neutral-500">{{ $line->memo }}</p>
                        </div>
                        <x-money :cents="$line->amount_cents" signed coloured class="text-sm" />
                    </div>
                @endforeach
            </div>
            <p class="mt-2 px-1 text-xs text-neutral-500">
                Las líneas de un asiento suman siempre cero: lo que uno adelanta es exactamente lo que deben los demás.
            </p>
        @else
            <div class="card text-sm text-neutral-500">
                Este viaje no ha generado reparto: el conductor iba solo.
            </div>
        @endif
    </section>

    {{-- ─── Ocupantes ─────────────────────────────────────────────────────── --}}
    <section class="card mb-4">
        <h2 class="text-sm font-semibold text-neutral-900">Ocupantes</h2>
        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach ($trip->passengers as $passenger)
                <li class="badge bg-neutral-100 text-neutral-700">
                    {{ $passenger->member->user->name }}
                    {{-- El porcentaje y no «medio viaje»: ahora puede ser un
                         cuarto o tres cuartos, y decir «medio» seria mentir --}}
                    @if ($passenger->weight < 1)
                        · {{ (int) round($passenger->weight * 100) }} % del viaje
                    @endif
                    @if ($passenger->group_member_id === $trip->driver_member_id)
                        · conduce
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ─── De dónde salen los números ────────────────────────────────────── --}}
    <section class="card mb-4">
        <h2 class="text-sm font-semibold text-neutral-900">De dónde salen los números</h2>

        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
                <dt class="text-neutral-500">Coche</dt>
                <dd class="text-right text-neutral-900">
                    {{ $trip->vehicle->label }} · {{ $trip->vehicle->powertrain->label() }}
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-neutral-500">Consumo estimado</dt>
                <dd class="text-right text-neutral-900">
                    @if (($breakdown['litres'] ?? 0) > 0)
                        {{ number_format($breakdown['litres'], 2, ',', '.') }} L
                    @endif
                    @if (($breakdown['kwh'] ?? 0) > 0)
                        {{ ($breakdown['litres'] ?? 0) > 0 ? ' + ' : '' }}{{ number_format($breakdown['kwh'], 2, ',', '.') }} kWh
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-neutral-500">Precio aplicado</dt>
                <dd class="text-right text-neutral-900">
                    {{ number_format(($inputs['fuel_price_milli'] ?? 0) / 1000, 3, ',', '.') }} €/L
                    <span class="block text-xs text-neutral-500">{{ $fuelPrice['station_label'] ?? $fuelPrice['source'] ?? '—' }}</span>
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-neutral-500">Recuperación en bajada</dt>
                <dd class="text-right text-neutral-900">
                    {{ number_format(($inputs['regen_factor'] ?? 0) * 100, 0, ',', '.') }} %
                    @if (($breakdown['regen_ceiling_m'] ?? 0) > 0)
                        <span class="block text-xs text-neutral-500">
                            batería llena a los {{ number_format($breakdown['regen_ceiling_m'], 0, ',', '.') }} m de bajada
                        </span>
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-neutral-500">Origen de la ruta</dt>
                <dd class="text-right text-neutral-900">
                    {{ match ($trip->route_source) {
                        'ors' => 'OpenRouteService (distancia y desnivel reales)',
                        'haversine' => 'Estimación en línea recta',
                        default => 'Introducida a mano',
                    } }}
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-neutral-500">Calibración del coche</dt>
                <dd class="text-right text-neutral-900">×{{ number_format($inputs['calibration_factor'] ?? 1, 3, ',', '.') }}</dd>
            </div>
        </dl>

        <p class="mt-3 border-t border-neutral-100 pt-3 text-xs text-neutral-500">
            Estos valores quedaron congelados al apuntar el viaje (fórmula v{{ $trip->formula_version }}).
            Aunque el coche se recalibre o suba el combustible, este viaje seguirá costando lo mismo.
        </p>
    </section>

    @if ($trip->notes)
        <section class="card mb-4">
            <h2 class="text-sm font-semibold text-neutral-900">Notas</h2>
            <p class="mt-1 text-sm whitespace-pre-line text-neutral-700">{{ $trip->notes }}</p>
        </section>
    @endif

    {{-- Lleva al formulario relleno, no apunta nada --}}
    <a href="{{ route('trips.repeat', [$group, $trip]) }}" class="btn-secondary mt-4 w-full">
        Volver a hacer este viaje
    </a>

    @if ($trip->journalEntry && ! $reversed)
        <form method="POST" action="{{ route('trips.cancel', [$group, $trip]) }}" class="mt-3"
              onsubmit="return confirm('¿Anular este viaje? El libro conservará el asiento original y su contrario.')">
            @csrf
            <button type="submit" class="btn-danger w-full">Anular este viaje</button>
        </form>
    @endif
</x-layouts.app>
