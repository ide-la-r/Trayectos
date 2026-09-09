<x-layouts.app title="Apuntar viaje" heading="Apuntar viaje" :subheading="$group->name" :group="$group">
    <div x-data="tripEstimate({ groupId: {{ $group->id }} })">
        <form method="POST" action="{{ route('trips.store', $group) }}" x-ref="form"
              @change="schedule()" @input.debounce.700ms="schedule()" class="space-y-4">
            @csrf

            {{-- ─── Quién y cuándo ───────────────────────────────────────── --}}
            <section class="card space-y-4">
                {{-- Una columna en movil. En dos, el campo de fecha nativo no baja
                     de su ancho intrinseco —los items de un grid no encogen por
                     defecto— y se metia por encima del conductor. --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label class="label" for="travelled_on">Fecha</label>
                        <input id="travelled_on" name="travelled_on" type="date" class="field" required
                               max="{{ now()->toDateString() }}"
                               value="{{ old('travelled_on', now()->toDateString()) }}">
                    </div>

                    <div class="min-w-0">
                        <label class="label" for="driver_member_id">Conductor</label>
                        <select id="driver_member_id" name="driver_member_id" class="field" required>
                            @foreach ($members as $option)
                                <option value="{{ $option->id }}"
                                    @selected(old('driver_member_id', $member->id) == $option->id)>
                                    {{ $option->user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label" for="vehicle_id">Coche</label>
                    @if ($vehicles->isEmpty())
                        <p class="rounded-xl border border-dashed border-neutral-300 px-3 py-3 text-sm text-neutral-600">
                            Nadie del grupo tiene un coche apuntado todavía.
                            <a href="{{ route('vehicles.create') }}" class="font-semibold underline">Añade el tuyo</a>.
                        </p>
                    @else
                        <select id="vehicle_id" name="vehicle_id" class="field" required
                                x-ref="vehicle" @change="$refs.form.dataset.powertrain = $event.target.selectedOptions[0].dataset.powertrain">
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}"
                                        data-powertrain="{{ $vehicle->powertrain->value }}"
                                        @selected(old('vehicle_id') == $vehicle->id)>
                                    {{-- Sin el nombre del dueño cuando el coche es tuyo, y sólo el nombre
                                         de pila cuando no: el rótulo completo se cortaba en el móvil
                                         justo por la parte que importa, el consumo. --}}
                                    {{ $vehicle->label }} · {{ $vehicle->consumptionLabel() }}@if ($vehicle->owner_id !== auth()->id()) · {{ \Illuminate\Support\Str::before($vehicle->owner->name, ' ') }}@endif
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </section>

            {{-- ─── Ruta ─────────────────────────────────────────────────── --}}
            <section class="card space-y-4">
                <x-place-field name="origin" label="Desde"
                               :value="old('origin_label')"
                               :lat="old('origin_lat')" :lon="old('origin_lon')"
                               placeholder="Calle, pueblo, estación…" />

                <x-place-field name="destination" label="Hasta"
                               :value="old('destination_label')"
                               :lat="old('destination_lat')" :lon="old('destination_lon')"
                               placeholder="¿A dónde vais?" />

                <label class="flex items-center gap-3 rounded-xl bg-neutral-50 px-3 py-2.5">
                    <input type="checkbox" name="round_trip" value="1" class="size-5 rounded border-neutral-300"
                           @checked(old('round_trip'))>
                    <span class="text-sm text-neutral-700">
                        Ida y vuelta
                        <span class="block text-xs text-neutral-500">
                            Duplica los kilómetros; la subida de la ida es bajada a la vuelta.
                        </span>
                    </span>
                </label>

                <details class="group rounded-xl border border-neutral-200 px-3 py-2.5"
                         {{ old('distance_km') ? 'open' : '' }}>
                    <summary class="flex cursor-pointer items-center justify-between gap-2 text-sm font-medium text-neutral-700">
                        Poner los datos a mano
                        <svg class="size-4 shrink-0 text-neutral-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </summary>

                    <div class="mt-3 space-y-3">
                        <p class="text-xs text-neutral-500">
                            Si escribes los kilómetros, mandan sobre lo que diga el servicio de rutas.
                        </p>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="label" for="distance_km">Km</label>
                                <input id="distance_km" name="distance_km" type="number" step="0.1" min="0"
                                       class="field" value="{{ old('distance_km') }}">
                            </div>
                            <div>
                                <label class="label" for="ascent_m">Subida (m)</label>
                                <input id="ascent_m" name="ascent_m" type="number" min="0" class="field"
                                       value="{{ old('ascent_m') }}">
                            </div>
                            <div>
                                <label class="label" for="descent_m">Bajada (m)</label>
                                <input id="descent_m" name="descent_m" type="number" min="0" class="field"
                                       value="{{ old('descent_m') }}">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="label" for="luggage_kg">Equipaje (kg)</label>
                                <input id="luggage_kg" name="luggage_kg" type="number" min="0" max="500"
                                       class="field" value="{{ old('luggage_kg', 0) }}">
                            </div>
                            <div>
                                <label class="label" for="battery_start_pct">Batería al salir (%)</label>
                                <input id="battery_start_pct" name="battery_start_pct" type="number" min="0" max="100"
                                       class="field" value="{{ old('battery_start_pct', 0) }}">
                                <p class="mt-1 text-xs text-neutral-500">Sólo para eléctricos y enchufables.</p>
                            </div>
                        </div>
                    </div>
                </details>
            </section>

            {{-- ─── Ocupantes ────────────────────────────────────────────── --}}
            <section class="card">
                <h2 class="text-sm font-semibold text-neutral-900">Quién va en el coche</h2>
                <p class="mt-0.5 text-xs text-neutral-500">
                    Marca a todos los ocupantes, el conductor incluido: su peso cuenta en el consumo.
                </p>

                <div class="mt-3 space-y-2">
                    @foreach ($members as $option)
                        <label class="flex items-center gap-3 rounded-xl border border-neutral-200 px-3 py-2.5">
                            <input type="checkbox" name="passengers[]" value="{{ $option->id }}"
                                   class="size-5 rounded border-neutral-300"
                                   @checked(in_array($option->id, old('passengers', [$member->id])))>
                            <span class="flex-1 text-sm text-neutral-800">{{ $option->user->name }}</span>
                            <select name="weights[{{ $option->id }}]"
                                    class="shrink-0 rounded-lg border-neutral-300 py-2 text-xs"
                                    aria-label="Cuánto viaje hace {{ $option->user->name }}">
                                <option value="1" @selected(old("weights.{$option->id}", '1') == '1')>Todo el viaje</option>
                                <option value="0.5" @selected(old("weights.{$option->id}") == '0.5')>Medio viaje</option>
                            </select>
                        </label>
                    @endforeach
                </div>
            </section>

            {{-- ─── Notas ────────────────────────────────────────────────── --}}
            <section class="card">
                <label class="label" for="notes">Notas <span class="font-normal text-neutral-400">(opcional)</span></label>
                <textarea id="notes" name="notes" rows="2" class="field"
                          placeholder="Peajes aparte, paramos a comer…">{{ old('notes') }}</textarea>
            </section>

            {{-- ─── Previsualización del coste ───────────────────────────── --}}
            <section class="card border-neutral-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-neutral-900">Lo que va a costar</h2>
                    <span x-show="loading" class="text-xs text-neutral-400">calculando…</span>
                </div>

                <template x-if="! estimate && ! loading">
                    <p class="mt-2 text-sm text-neutral-500" x-text="error || 'Elige coche, origen y destino para verlo.'"></p>
                </template>

                <template x-if="estimate">
                    <div class="mt-3 space-y-3">
                        <div class="flex items-end justify-between">
                            <div>
                                <p class="text-3xl font-semibold tabular-nums text-neutral-900"
                                   x-text="euros(estimate.total_cents)"></p>
                                <p class="text-xs text-neutral-500">
                                    <span x-text="estimate.distance_km.toLocaleString('es-ES')"></span> km ·
                                    ↑<span x-text="estimate.ascent_m"></span> m ·
                                    ↓<span x-text="estimate.descent_m"></span> m
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold tabular-nums text-neutral-900"
                                   x-text="euros(estimate.per_person_cents)"></p>
                                <p class="text-xs text-neutral-500">por persona</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-lg bg-neutral-50 px-3 py-2">
                                <p class="text-neutral-500">Por el desnivel</p>
                                <p class="font-semibold text-neutral-900">
                                    +<span x-text="estimate.hill_percent.toLocaleString('es-ES')"></span> %
                                </p>
                            </div>
                            <div class="rounded-lg bg-neutral-50 px-3 py-2">
                                <p class="text-neutral-500">Consumo estimado</p>
                                <p class="font-semibold text-neutral-900">
                                    <span x-show="estimate.litres > 0">
                                        <span x-text="estimate.litres.toLocaleString('es-ES')"></span> L
                                    </span>
                                    <span x-show="estimate.kwh > 0">
                                        <span x-show="estimate.litres > 0"> + </span>
                                        <span x-text="estimate.kwh.toLocaleString('es-ES')"></span> kWh
                                    </span>
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-neutral-500">
                            Precio: <span x-text="estimate.fuel_price.description"></span>
                            (<span x-text="estimate.fuel_price.euros.toLocaleString('es-ES')"></span> €/L)
                        </p>

                        <p x-show="estimate.route_warning" class="rounded-lg bg-neutral-100 px-3 py-2 text-xs text-neutral-600"
                           x-text="estimate.route_warning"></p>

                        {{--
                            Cuánto costaría el mismo trayecto con cada coche del
                            grupo. Solo llega cuando hay más de uno con plazas
                            suficientes: una tabla de una fila no compara nada.
                        --}}
                        <template x-if="estimate.comparison && estimate.comparison.length">
                            <div class="border-t border-neutral-100 pt-3">
                                <p class="text-xs font-semibold text-neutral-900">Con qué coche sale más barato</p>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    Este mismo trayecto, con cada coche del grupo. Toca uno para elegirlo.
                                </p>

                                <ul class="mt-2 space-y-1.5">
                                    <template x-for="fila in estimate.comparison" :key="fila.vehicle_id">
                                        <li>
                                            <button type="button" @click="elegirCoche(fila.vehicle_id)"
                                                    class="flex w-full items-center gap-3 rounded-xl border px-3 py-2 text-left transition"
                                                    :class="fila.selected
                                                        ? 'border-neutral-900 bg-neutral-50'
                                                        : 'border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50'">
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-medium text-neutral-900">
                                                        <span x-text="fila.label"></span>
                                                        <span x-show="fila.cheapest"
                                                              class="badge ml-1 bg-credit-50 text-credit-700">más barato</span>
                                                        <span x-show="fila.selected && ! fila.cheapest"
                                                              class="badge ml-1 bg-neutral-900 text-white">elegido</span>
                                                    </p>
                                                    <p class="truncate text-xs text-neutral-500">
                                                        <span x-text="fila.owner"></span>
                                                        <template x-if="fila.extra > 0">
                                                            <span>· <span x-text="moneda(fila.extra)"></span> más que el más barato</span>
                                                        </template>
                                                    </p>
                                                </div>

                                                <div class="shrink-0 text-right">
                                                    <p class="money text-sm text-neutral-900" x-text="moneda(fila.cost)"></p>
                                                    <p class="text-xs text-neutral-500">
                                                        <span x-text="moneda(fila.per_payer)"></span> cada uno
                                                    </p>
                                                </div>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </template>
            </section>

            <button type="submit" class="btn-primary w-full py-3" @if ($vehicles->isEmpty()) disabled @endif>
                Apuntar y repartir
            </button>
        </form>
    </div>
</x-layouts.app>
