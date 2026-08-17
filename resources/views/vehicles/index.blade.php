<x-layouts.app title="Mis coches" heading="Mis coches">
    <x-slot:actions>
        <a href="{{ route('vehicles.create') }}" class="btn-primary py-1.5 text-xs">Añadir</a>
    </x-slot:actions>

    @if ($vehicles->isEmpty())
        <div class="card text-center">
            <p class="text-4xl">🚙</p>
            <h2 class="mt-3 text-lg font-semibold text-neutral-900">Aún no tienes coches</h2>
            <p class="mx-auto mt-1 max-w-sm text-sm text-neutral-500">
                Apunta el tuyo con su consumo homologado. Con eso y el desnivel de cada ruta, el sistema calcula
                lo que cuesta de verdad cada viaje.
            </p>
            <a href="{{ route('vehicles.create') }}" class="btn-primary mt-4">Añadir mi coche</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($vehicles as $vehicle)
                <a href="{{ route('vehicles.edit', $vehicle) }}" class="card block hover:border-neutral-300">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-neutral-900">{{ $vehicle->label }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                {{ $vehicle->powertrain->label() }}
                                @if ($vehicle->powertrain->burnsFuel())
                                    · {{ $vehicle->fuel_kind->label() }}
                                @endif
                                · {{ $vehicle->seats }} plazas
                                · {{ number_format($vehicle->kerb_weight_kg, 0, ',', '.') }} kg
                            </p>
                        </div>

                        @unless ($vehicle->active)
                            <span class="badge bg-neutral-100 text-neutral-500">retirado</span>
                        @endunless
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded-lg bg-neutral-50 px-3 py-2">
                            <dt class="text-neutral-500">Consumo</dt>
                            <dd class="font-semibold text-neutral-900">{{ $vehicle->consumptionLabel() }}</dd>
                        </div>
                        <div class="rounded-lg bg-neutral-50 px-3 py-2">
                            <dt class="text-neutral-500">Recupera</dt>
                            <dd class="font-semibold text-neutral-900">
                                {{ number_format($vehicle->regen_factor * 100, 0, ',', '.') }} % al bajar
                            </dd>
                        </div>
                        <div class="rounded-lg bg-neutral-50 px-3 py-2">
                            <dt class="text-neutral-500">Calibración</dt>
                            <dd class="font-semibold text-neutral-900">
                                ×{{ number_format($vehicle->calibration_factor, 3, ',', '.') }}
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-2 text-xs text-neutral-500">
                        {{ $vehicle->trips_count }} {{ $vehicle->trips_count === 1 ? 'viaje apuntado' : 'viajes apuntados' }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
