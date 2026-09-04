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
                @php
                    // Sin viajes conducidos no hay entrada en el recuento
                    $mine = $tally[$row->member->id] ?? null;
                @endphp

                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-[11px] font-semibold text-neutral-600">
                            {{ $row->member->user->initials() }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm text-neutral-800">
                                {{ $row->member->user->name }}
                                @if ($row->member->id === $member->id)
                                    <span class="text-neutral-400">· tú</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-neutral-500">
                                @if ($mine)
                                    {{ $mine->trips }} {{ $mine->trips === 1 ? 'vez' : 'veces' }} al volante
                                    · {{ number_format($mine->meters / 1000, 0, ',', '.') }} km
                                @else
                                    Todavía no ha conducido
                                @endif
                            </p>
                        </div>
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
        <p class="mt-1 text-xs text-neutral-500">
            Manda el enlace y quien lo abra entra directo, tenga cuenta o no.
        </p>

        <div x-data="inviteLink(@js(route('groups.invitation', $group->invite_code)), @js($group->name))"
             class="mt-3">
            <div class="flex gap-2">
                {{-- readonly y no disabled: hace falta poder seleccionarlo para
                     que funcione el recurso de copiar a mano --}}
                <input x-ref="field" type="text" readonly
                       value="{{ route('groups.invitation', $group->invite_code) }}"
                       class="field flex-1 text-neutral-600 sm:text-xs"
                       onclick="this.select()"
                       aria-label="Enlace de invitación">

                <button type="button" x-show="canShare" @click="share()"
                        class="btn-primary shrink-0 px-3" x-cloak aria-label="Compartir enlace">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"/>
                    </svg>
                </button>

                <button type="button" @click="copy()"
                        class="btn-secondary shrink-0 px-3"
                        :aria-label="copied ? 'Enlace copiado' : 'Copiar enlace'">
                    <svg x-show="! copied" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
                    </svg>
                    <svg x-show="copied" class="size-4 text-credit-700" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                </button>
            </div>

            <p x-show="copied" x-cloak class="mt-2 text-xs font-medium text-credit-700">
                Enlace copiado.
            </p>

            {{-- El navegador puede negar el portapapeles: hay que decirlo en vez
                 de dejar un botón que aparenta no hacer nada. --}}
            <p x-show="failed" x-cloak class="mt-2 text-xs font-medium text-neutral-600">
                Tu navegador no deja copiar solo. Ya está seleccionado: pulsa Ctrl+C (o Cmd+C).
            </p>
        </div>

        <p class="mt-4 text-xs text-neutral-500">
            O que metan este código a mano en «Unirme con un código»:
        </p>
        <p class="mt-1.5 rounded-xl bg-neutral-100 px-4 py-2.5 text-center text-lg font-semibold tracking-widest text-neutral-900">
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
