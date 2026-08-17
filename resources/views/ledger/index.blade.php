<x-layouts.app title="Libro mayor" heading="Libro mayor" :subheading="$group->name" :group="$group">

    @unless ($consistent)
        <div class="mb-4 rounded-xl border border-debt-500/30 bg-debt-50 px-4 py-3 text-sm text-debt-700">
            <p class="font-semibold">Atención: los saldos del grupo no suman cero.</p>
            <p class="mt-1">
                Eso sólo puede pasar si algo ha escrito en el libro sin pasar por la aplicación. Revisa los
                últimos asientos.
            </p>
        </div>
    @endunless

    <section class="card mb-4">
        <h2 class="text-sm font-semibold text-neutral-900">Saldos actuales</h2>
        <div class="mt-2 divide-y divide-neutral-100">
            @foreach ($balances as $row)
                <div class="flex items-center justify-between gap-3 py-2">
                    <p class="truncate text-sm text-neutral-800">{{ $row->member->user->name }}</p>
                    <x-money :cents="$row->balance_cents" signed coloured class="text-sm" />
                </div>
            @endforeach
        </div>
        <p class="mt-2 border-t border-neutral-100 pt-2 text-right text-xs text-neutral-500">
            Suma total: {{ \App\Support\Money::format($balances->sum('balance_cents')) }}
            @if ($consistent)
                · cuadra
            @endif
        </p>
    </section>

    <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">Asientos</h2>

    @if ($entries->isEmpty())
        <div class="card text-center text-sm text-neutral-500">
            El libro está vacío. Apunta un viaje y aparecerá aquí.
        </div>
    @else
        <div class="space-y-2">
            @foreach ($entries as $entry)
                @php $isReversed = $entry->reversal->isNotEmpty(); @endphp

                <article class="card-tight {{ $isReversed ? 'opacity-60' : '' }}">
                    <header class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $entry->description }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                {{ $entry->occurred_on->format('d/m/Y') }} ·
                                {{ $entry->kind->label() }}
                                @if ($isReversed)
                                    · anulado
                                @endif
                            </p>
                        </div>
                        <span class="money text-sm text-neutral-900">
                            {{ \App\Support\Money::format($entry->lines->where('amount_cents', '>', 0)->sum('amount_cents')) }}
                        </span>
                    </header>

                    <div class="mt-2 space-y-1 border-t border-neutral-100 pt-2">
                        @foreach ($entry->lines as $line)
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="truncate text-neutral-600">{{ $line->member->user->name }}</span>
                                <x-money :cents="$line->amount_cents" signed coloured class="text-xs" />
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $entries->links() }}
        </div>
    @endif

    <p class="mt-4 px-1 text-xs text-neutral-500">
        Cada movimiento es un asiento cuyas líneas suman cero, y nada se borra: un error se corrige con un
        asiento contrario. Por eso los saldos no pueden descuadrar.
    </p>
</x-layouts.app>
