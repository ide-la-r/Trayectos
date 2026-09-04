<x-layouts.app title="Liquidar" heading="Dejar las cuentas a cero" :subheading="$group->name" :group="$group">

    {{-- ─── Plan de pagos ─────────────────────────────────────────────────── --}}
    <section class="mb-4">
        <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">Pagos pendientes</h2>

        @if (empty($plan))
            <div class="card text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-credit-50 text-credit-700"><svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></span>
                <p class="mt-2 text-sm text-neutral-600">Nadie debe nada a nadie. Las cuentas están a cero.</p>
            </div>
        @else
            <p class="mb-2 px-1 text-xs text-neutral-500">
                Con estos {{ count($plan) }} {{ count($plan) === 1 ? 'pago' : 'pagos' }} queda todo saldado:
                es el mínimo número de transferencias posible.
            </p>

            <div class="space-y-2">
                @foreach ($plan as $payment)
                    <div class="card-tight">
                        <div class="flex items-center justify-between gap-3">
                            <p class="min-w-0 flex-1 truncate text-sm text-neutral-800">
                                <span class="font-semibold">{{ $payment['from']->user->name }}</span>
                                paga a
                                <span class="font-semibold">{{ $payment['to']->user->name }}</span>
                            </p>
                            <span class="money text-sm text-neutral-900">
                                {{ \App\Support\Money::format($payment['amount_cents']) }}
                            </span>
                        </div>

                        <form method="POST" action="{{ route('settlements.store', $group) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="from_member_id" value="{{ $payment['from']->id }}">
                            <input type="hidden" name="to_member_id" value="{{ $payment['to']->id }}">
                            <input type="hidden" name="amount" value="{{ $payment['amount_cents'] / 100 }}">
                            <input type="hidden" name="settled_on" value="{{ now()->toDateString() }}">
                            <input type="hidden" name="method" value="bizum">
                            <button type="submit" class="btn-secondary w-full py-1.5 text-xs">
                                Marcar como pagado
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ─── Pago manual ───────────────────────────────────────────────────── --}}
    <section class="card mb-4">
        <h2 class="text-sm font-semibold text-neutral-900">Apuntar otro pago</h2>

        <form method="POST" action="{{ route('settlements.store', $group) }}" class="mt-3 space-y-3">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="from_member_id">Paga</label>
                    <select id="from_member_id" name="from_member_id" class="field" required>
                        @foreach ($balances as $row)
                            <option value="{{ $row->member->id }}" @selected($row->member->id === $member->id)>
                                {{ $row->member->user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="to_member_id">Cobra</label>
                    <select id="to_member_id" name="to_member_id" class="field" required>
                        @foreach ($balances as $row)
                            <option value="{{ $row->member->id }}">{{ $row->member->user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="amount">Importe (€)</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" class="field" required>
                </div>

                <div>
                    <label class="label" for="settled_on">Fecha</label>
                    <input id="settled_on" name="settled_on" type="date" class="field" required
                           max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                </div>
            </div>

            <div>
                <label class="label" for="method">Cómo</label>
                <input id="method" name="method" type="text" class="field" placeholder="Bizum, efectivo, transferencia…">
            </div>

            <button type="submit" class="btn-primary w-full">Apuntar pago</button>
        </form>
    </section>

    {{-- ─── Historial ─────────────────────────────────────────────────────── --}}
    @if ($settlements->isNotEmpty())
        <section>
            <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">Pagos apuntados</h2>

            <div class="card divide-y divide-neutral-100 p-0">
                @foreach ($settlements as $settlement)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-neutral-800">
                                {{ $settlement->from->user->name }} → {{ $settlement->to->user->name }}
                            </p>
                            <p class="text-xs text-neutral-500">
                                {{ $settlement->settled_on->format('d/m/Y') }}
                                @if ($settlement->method)
                                    · {{ $settlement->method }}
                                @endif
                            </p>
                        </div>
                        <span class="money text-sm text-neutral-900">
                            {{ \App\Support\Money::format($settlement->amount_cents) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
