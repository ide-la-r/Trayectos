<x-layouts.app :title="$group->name" :heading="$group->name" :group="$group"
               :subheading="$group->driver_pays_own_share ? 'El conductor paga su parte' : 'Conducir sale gratis'">
    <x-slot:actions>
        <a href="{{ route('trips.create', $group) }}" class="btn-primary py-1.5 text-xs">Apuntar viaje</a>
    </x-slot:actions>

    {{-- ─── Mi saldo ──────────────────────────────────────────────────────── --}}
    <div class="card mb-4 text-center">
        <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase">
            {{ $myBalance > 0 ? 'Te deben' : ($myBalance < 0 ? 'Debes al grupo' : 'Estás a cero') }}
        </p>
        <p class="mt-1">
            <x-money :cents="$myBalance" coloured class="text-3xl" />
        </p>
    </div>

    {{-- ─── Próximo conductor ─────────────────────────────────────────────── --}}
    @if (! empty($suggestions))
        <section class="mb-4">
            <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">A quién le toca conducir</h2>

            <div class="space-y-2">
                @foreach ($suggestions as $index => $suggestion)
                    <div class="card-tight flex items-center gap-3 {{ $index === 0 ? 'border-neutral-900' : '' }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full
                                    {{ $index === 0 ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-600' }}
                                    text-xs font-semibold">
                            {{ $suggestion['member']->user->initials() }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-neutral-900">
                                {{ $suggestion['member']->user->name }}
                                @if ($index === 0)
                                    <span class="badge ml-1 bg-neutral-900 text-white">le toca</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-neutral-500">{{ $suggestion['reason'] }}</p>
                        </div>

                        <x-money :cents="$suggestion['balance_cents']" coloured class="text-sm" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ─── Saldos del grupo ──────────────────────────────────────────────── --}}
    <section class="mb-4">
        <div class="mb-2 flex items-baseline justify-between px-1">
            <h2 class="text-sm font-semibold text-neutral-900">Saldos</h2>
            <a href="{{ route('settlements.index', $group) }}" class="text-xs font-medium text-neutral-500 underline">
                Liquidar
            </a>
        </div>

        <div class="card divide-y divide-neutral-100 p-0">
            @foreach ($balances as $row)
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-[11px] font-semibold text-neutral-600">
                            {{ $row->member->user->initials() }}
                        </div>
                        <p class="truncate text-sm text-neutral-800">
                            {{ $row->member->user->name }}
                            @if ($row->member->id === $member->id)
                                <span class="text-neutral-400">· tú</span>
                            @endif
                        </p>
                    </div>
                    <x-money :cents="$row->balance_cents" signed coloured class="text-sm" />
                </div>
            @endforeach
        </div>
    </section>

    {{-- ─── Últimos viajes ────────────────────────────────────────────────── --}}
    <section class="mb-4">
        <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">Últimos viajes</h2>

        @if ($trips->isEmpty())
            <div class="card text-center text-sm text-neutral-500">
                Todavía no hay viajes. Apunta el primero y el reparto se hace solo.
            </div>
        @else
            <div class="space-y-2">
                @foreach ($trips as $trip)
                    <a href="{{ route('trips.show', [$group, $trip]) }}"
                       class="card-tight flex items-center gap-3 hover:border-neutral-300">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-neutral-900">
                                {{ $trip->origin_label }} → {{ $trip->destination_label }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-neutral-500">
                                {{ $trip->travelled_on->format('d/m/Y') }} ·
                                {{ $trip->driver->user->name }} ·
                                {{ number_format($trip->distanceKm(), 1, ',', '.') }} km ·
                                {{ $trip->passengers->count() }} {{ $trip->passengers->count() === 1 ? 'ocupante' : 'ocupantes' }}
                                @if ($trip->ascent_m > 300)
                                    · ↑{{ number_format($trip->ascent_m, 0, ',', '.') }} m
                                @endif
                            </p>
                        </div>
                        <x-money :cents="$trip->total_cost_cents" class="text-sm" />
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ─── Invitación y reglas ───────────────────────────────────────────── --}}
    <section class="card">
        <h2 class="text-sm font-semibold text-neutral-900">Invitar al grupo</h2>
        <p class="mt-1 text-xs text-neutral-500">Comparte este código para que se unan.</p>
        <p class="mt-2 rounded-xl bg-neutral-100 px-4 py-3 text-center text-xl font-semibold tracking-widest text-neutral-900">
            {{ $group->invite_code }}
        </p>

        @if ($member->isAdmin())
            <form method="POST" action="{{ route('groups.update', $group) }}" class="mt-4 space-y-3 border-t border-neutral-100 pt-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="label" for="group-name">Nombre</label>
                    <input id="group-name" name="name" type="text" class="field" value="{{ $group->name }}" required>
                </div>

                <label class="flex items-start gap-3">
                    <input type="checkbox" name="driver_pays_own_share" value="1"
                           class="mt-0.5 size-5 rounded border-neutral-300"
                           {{ $group->driver_pays_own_share ? 'checked' : '' }}>
                    <span class="text-sm text-neutral-700">
                        El conductor paga su parte del viaje
                        <span class="block text-xs text-neutral-500">
                            Cambiarlo no toca los viajes ya apuntados: sólo afecta a los siguientes.
                        </span>
                    </span>
                </label>

                <button type="submit" class="btn-secondary w-full">Guardar reglas</button>
            </form>
        @endif
    </section>

    @unless ($canDrive)
        <div class="card mt-4 border-dashed">
            <p class="text-sm text-neutral-700">
                No tienes ningún coche apuntado, así que no puedes aparecer como conductor.
            </p>
            <a href="{{ route('vehicles.create') }}" class="btn-primary mt-3">Añadir mi coche</a>
        </div>
    @endunless
</x-layouts.app>
