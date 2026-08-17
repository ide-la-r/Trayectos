<x-layouts.app title="Panel · Libro de Trayectos" heading="Tus grupos">
    @if ($groups->isEmpty())
        <div class="card text-center">
            <p class="text-4xl">🚗</p>
            <h2 class="mt-3 text-lg font-semibold text-neutral-900">Empieza por un grupo</h2>
            <p class="mx-auto mt-1 max-w-sm text-sm text-neutral-500">
                Un grupo es un libro de cuentas compartido: los viajes que apuntéis dentro se reparten entre
                sus miembros.
            </p>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <a href="{{ route('groups.create') }}" class="btn-primary">Crear un grupo</a>
                <a href="{{ route('groups.join') }}" class="btn-secondary">Tengo un código</a>
            </div>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($groups as $row)
                <a href="{{ route('groups.show', $row->group) }}"
                   class="card flex items-center justify-between gap-4 hover:border-neutral-300">
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

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('groups.create') }}" class="btn-secondary flex-1">Crear otro grupo</a>
            <a href="{{ route('groups.join') }}" class="btn-secondary flex-1">Unirme con un código</a>
        </div>
    @endif

    @if ($vehicles->isEmpty())
        <div class="card mt-4 border-dashed">
            <p class="text-sm text-neutral-700">
                <span class="font-semibold">Te falta apuntar tu coche.</span>
                Sin coche no puedes conducir ningún trayecto, y el sistema no podrá sugerirte como conductor.
            </p>
            <a href="{{ route('vehicles.create') }}" class="btn-primary mt-3">Añadir mi coche</a>
        </div>
    @endif
</x-layouts.app>
