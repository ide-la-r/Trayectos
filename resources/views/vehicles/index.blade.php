<x-layouts.app title="Mis coches" heading="Mis coches">
    <x-slot:actions>
        <a href="{{ route('vehicles.create') }}" class="btn-primary py-1.5 text-xs">Añadir</a>
    </x-slot:actions>

    @if ($vehicles->isEmpty())
        <div class="card text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-full bg-neutral-100 text-neutral-500"><svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg></span>
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
