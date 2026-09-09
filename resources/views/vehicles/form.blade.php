@php
    $editing = $vehicle->exists;
    $current = old('powertrain', $vehicle->powertrain?->value ?? 'ICE');
@endphp

<x-layouts.app :title="$editing ? 'Editar coche' : 'Añadir coche'"
               :heading="$editing ? $vehicle->label : 'Añadir coche'">

    <form method="POST" action="{{ $editing ? route('vehicles.update', $vehicle) : route('vehicles.store') }}"
          x-data="{ powertrain: @js($current) }" class="space-y-4">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <section class="card space-y-4">
            <div>
                <label class="label" for="label">Nombre</label>
                <input id="label" name="label" type="text" class="field" required
                       value="{{ old('label', $vehicle->label) }}" placeholder="El Golf de Ana">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="plate">Matrícula <span class="font-normal text-neutral-400">(opcional)</span></label>
                    <input id="plate" name="plate" type="text" class="field uppercase"
                           value="{{ old('plate', $vehicle->plate) }}">
                </div>

                <div>
                    <label class="label" for="seats">Plazas</label>
                    <input id="seats" name="seats" type="number" min="1" max="9" class="field" required
                           value="{{ old('seats', $vehicle->seats ?? 5) }}">
                </div>
            </div>

            <div>
                <label class="label" for="powertrain">Tecnología</label>
                <select id="powertrain" name="powertrain" class="field" required x-model="powertrain">
                    @foreach (\App\Enums\Powertrain::options() as $value => $label)
                        <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="powertrain !== 'BEV'">
                <label class="label" for="fuel_kind">Combustible</label>
                <select id="fuel_kind" name="fuel_kind" class="field">
                    @foreach (\App\Enums\FuelKind::options() as $value => $label)
                        @continue($value === 'NONE')
                        <option value="{{ $value }}"
                            @selected(old('fuel_kind', $vehicle->fuel_kind?->value ?? 'G95E5') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Los eléctricos no tienen combustible, pero el campo debe viajar --}}
            <template x-if="powertrain === 'BEV'">
                <input type="hidden" name="fuel_kind" value="NONE">
            </template>

            <div>
                <label class="label" for="kerb_weight_kg">Peso en vacío (kg)</label>
                <input id="kerb_weight_kg" name="kerb_weight_kg" type="number" min="500" max="4000" class="field" required
                       value="{{ old('kerb_weight_kg', $vehicle->kerb_weight_kg ?? 1400) }}">
                <p class="mt-1 text-xs text-neutral-500">
                    Viene en la ficha técnica. Cuenta de verdad: subir un puerto con cinco personas cuesta más
                    que subirlo solo.
                </p>
            </div>
        </section>

        <section class="card space-y-4">
            <h2 class="text-sm font-semibold text-neutral-900">Consumo homologado</h2>

            <div x-show="powertrain !== 'BEV'">
                <label class="label" for="consumption_l_100">Litros a los 100 km</label>
                <input id="consumption_l_100" name="consumption_l_100" type="number" step="0.1" min="0.5" max="40"
                       class="field" value="{{ old('consumption_l_100', $vehicle->consumption_l_100) }}">
            </div>

            <div x-show="powertrain === 'BEV' || powertrain === 'PHEV'">
                <label class="label" for="consumption_kwh_100">kWh a los 100 km</label>
                <input id="consumption_kwh_100" name="consumption_kwh_100" type="number" step="0.1" min="5" max="60"
                       class="field" value="{{ old('consumption_kwh_100', $vehicle->consumption_kwh_100) }}">
            </div>

            <div x-show="powertrain !== 'ICE'">
                <label class="label" for="battery_kwh_usable">Batería útil (kWh)</label>
                <input id="battery_kwh_usable" name="battery_kwh_usable" type="number" step="0.1" min="0.3" max="200"
                       class="field" value="{{ old('battery_kwh_usable', $vehicle->battery_kwh_usable) }}">
                <p class="mt-1 text-xs text-neutral-500">
                    Un híbrido normal ronda 1,3 kWh; un enchufable, 10-20; un eléctrico, 40-100. De este dato
                    depende cuánta bajada puede recuperar antes de saturarse.
                </p>
            </div>

            <div x-show="powertrain === 'BEV' || powertrain === 'PHEV'">
                <label class="label" for="ev_range_km">Autonomía eléctrica (km)</label>
                <input id="ev_range_km" name="ev_range_km" type="number" min="1" max="1000" class="field"
                       value="{{ old('ev_range_km', $vehicle->ev_range_km) }}">
            </div>
        </section>

        <details class="card">
            <summary class="cursor-pointer text-sm font-semibold text-neutral-900">Ajuste fino del modelo</summary>

            <div class="mt-3 space-y-4">
                <p class="text-xs text-neutral-500">
                    Estos dos valores salen de la tecnología elegida y no hace falta tocarlos. Si los dejas vacíos
                    se usan los de referencia.
                </p>

                <div>
                    <label class="label" for="regen_factor">Recuperación en bajada (0 a 0,9)</label>
                    <input id="regen_factor" name="regen_factor" type="number" step="0.01" min="0" max="0.9"
                           class="field" value="{{ old('regen_factor', $vehicle->regen_factor) }}">
                    <p class="mt-1 text-xs text-neutral-500">
                        Combustión 0,05 · híbrido 0,60 · enchufable 0,65 · eléctrico 0,70.
                    </p>
                </div>

                <div x-show="powertrain !== 'BEV'">
                    <label class="label" for="thermal_efficiency">Rendimiento del motor (0,1 a 0,5)</label>
                    <input id="thermal_efficiency" name="thermal_efficiency" type="number" step="0.01" min="0.1" max="0.5"
                           class="field" value="{{ old('thermal_efficiency', $vehicle->thermal_efficiency) }}">
                    <p class="mt-1 text-xs text-neutral-500">Gasolina 0,25 · diésel 0,30 · híbrido 0,33.</p>
                </div>

                @if ($editing)
                    <div class="rounded-xl bg-neutral-50 px-3 py-2 text-xs text-neutral-600">
                        Factor de calibración actual:
                        <span class="font-semibold">×{{ number_format($vehicle->calibration_factor, 3, ',', '.') }}</span>.
                        Se ajusta solo comparando lo que gastas de verdad con lo que el modelo
                        predijo para tus viajes.
                    </div>

                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="active" value="1" class="size-5 rounded border-neutral-300"
                               @checked(old('active', $vehicle->active))>
                        <span class="text-sm text-neutral-700">Sigo usando este coche</span>
                    </label>
                @endif
            </div>
        </details>

        <button type="submit" class="btn-primary w-full py-3">
            {{ $editing ? 'Guardar cambios' : 'Añadir coche' }}
        </button>
    </form>

    {{-- ─── Repostajes: la calibración con datos reales ────────────────────── --}}
    @if ($editing)
        <section class="card mt-4">
            <h2 class="text-sm font-semibold text-neutral-900">Repostajes reales</h2>
            <p class="mt-1 text-xs text-neutral-500">
                Apunta lo que echas al depósito <strong>con el cuentakilómetros</strong>. De un llenado
                completo al siguiente sale lo que gasta este coche de verdad, sin fiarse de la ficha ni del
                modelo: se acaba la discusión de «mi coche no gasta eso».
            </p>

            @php
                // Se componen enteras y no a trozos: partirlas en varios @if deja
                // saltos de línea dentro del número y se lee «6,5 L /100 km».
                $porCien = fn (?float $litres, ?float $kwh) => collect([
                    $litres ? number_format($litres, 1, ',', '.').' L' : null,
                    $kwh ? number_format($kwh, 1, ',', '.').' kWh' : null,
                ])->filter()->implode(' + ');

                $real = $porCien($consumption['litres'], $consumption['kwh']);
            @endphp

            @if ($real !== '')
                <div class="mt-3 rounded-xl bg-neutral-900 px-4 py-3 text-white">
                    <p class="text-xs text-neutral-400">Consumo real de este coche</p>
                    <p class="mt-0.5 text-2xl font-semibold tracking-tight tabular-nums">{{ $real }}<span
                            class="text-sm font-normal text-neutral-400">/100 km</span></p>
                    <p class="mt-1 text-xs text-neutral-400">
                        {{ 'Medido en '.$consumption['tanks'].' '
                            .\Illuminate\Support\Str::plural('depósito', $consumption['tanks'])
                            .' y '.number_format($consumption['km'], 0, ',', '.').' km' }}
                        · la ficha dice {{ $vehicle->consumptionLabel() }}
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ route('refuels.store', $vehicle) }}" class="mt-3 space-y-3">
                @csrf

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="refuelled_on">Fecha</label>
                        <input id="refuelled_on" name="refuelled_on" type="date" class="field" required
                               max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                    </div>

                    <div>
                        <label class="label" for="cost">Importe (€)</label>
                        <input id="cost" name="cost" type="number" step="0.01" min="0.5" class="field" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @if ($vehicle->powertrain->burnsFuel())
                        <div>
                            <label class="label" for="litres">Litros</label>
                            <input id="litres" name="litres" type="number" step="0.01" min="0.5" class="field">
                        </div>
                    @endif

                    @if ($vehicle->powertrain->drivesOnBattery())
                        <div>
                            <label class="label" for="kwh">kWh</label>
                            <input id="kwh" name="kwh" type="number" step="0.01" min="0.5" class="field">
                        </div>
                    @endif

                    <div>
                        <label class="label" for="odometer_km">Cuentakilómetros</label>
                        <input id="odometer_km" name="odometer_km" type="number" min="0" class="field">
                    </div>
                </div>

                <label class="flex items-center gap-3">
                    <input type="checkbox" name="full_tank" value="1" class="size-5 rounded border-neutral-300" checked>
                    <span class="text-sm text-neutral-700">
                        Depósito lleno
                        <span class="block text-xs text-neutral-500">Sólo los llenados completos sirven para calibrar.</span>
                    </span>
                </label>

                <button type="submit" class="btn-secondary w-full">Apuntar repostaje</button>
            </form>

            @if (isset($refuels) && $refuels->isNotEmpty())
                <div class="mt-4 divide-y divide-neutral-100 border-t border-neutral-100 pt-2">
                    @foreach ($refuels as $refuel)
                        @php($tank = $tanks[$refuel->id] ?? null)

                        <div class="flex items-start justify-between gap-3 py-2 text-xs">
                            <div class="min-w-0">
                                <span class="text-neutral-600">{{ $refuel->refuelled_on->format('d/m/Y') }}</span>

                                {{-- El depósito que cierra este repostaje, si se ha podido medir --}}
                                @if ($tank)
                                    <span class="mt-0.5 block text-neutral-500">{{
                                        $porCien($tank->litresPer100(), $tank->kwhPer100())
                                            .'/100 km en '.number_format($tank->km, 0, ',', '.').' km'
                                    }}</span>
                                @elseif ($refuel->full_tank && ! $refuel->odometer_km)
                                    <span class="mt-0.5 block text-neutral-400">Sin cuentakilómetros</span>
                                @endif
                            </div>

                            <span class="shrink-0 text-right text-neutral-800">
                                @if ($refuel->litres)
                                    {{ number_format($refuel->litres, 2, ',', '.') }} L
                                @endif
                                @if ($refuel->kwh)
                                    {{ number_format($refuel->kwh, 2, ',', '.') }} kWh
                                @endif
                                · {{ \App\Support\Money::format($refuel->cost_cents) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</x-layouts.app>
