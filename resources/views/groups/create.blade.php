<x-layouts.app title="Crear grupo" heading="Crear un grupo">
    <form method="POST" action="{{ route('groups.store') }}" class="card space-y-4">
        @csrf

        <div>
            <label class="label" for="name">Nombre del grupo</label>
            <input id="name" name="name" type="text" class="field" required autofocus
                   value="{{ old('name') }}" placeholder="Los del jueves, Cuadrilla de la sierra…">
        </div>

        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-3">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="driver_pays_own_share" value="1"
                       class="mt-0.5 size-5 rounded border-neutral-300"
                       {{ old('driver_pays_own_share', true) ? 'checked' : '' }}>
                <span class="text-sm text-neutral-700">
                    <span class="font-semibold text-neutral-900">El conductor paga su parte</span><br>
                    Marcado, el coste se divide entre todos los ocupantes y el conductor recupera sólo lo de los
                    demás. Sin marcar, conducir sale gratis: los pasajeros cubren el viaje entero.
                </span>
            </label>
        </div>

        <button type="submit" class="btn-primary w-full">Crear grupo</button>
    </form>
</x-layouts.app>
