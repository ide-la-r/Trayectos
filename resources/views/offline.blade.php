<x-layouts.guest title="Sin conexión">
    <div class="card text-center">
        <p class="text-4xl">📡</p>
        <h2 class="mt-3 text-lg font-semibold text-neutral-900">Sin conexión</h2>
        <p class="mx-auto mt-1 max-w-sm text-sm text-neutral-500">
            No hay red ahora mismo. Las pantallas que ya habías abierto siguen disponibles; para apuntar un
            viaje nuevo hace falta conexión, porque el coste se calcula en el servidor.
        </p>
        <button type="button" class="btn-primary mt-4" onclick="window.location.reload()">Volver a intentarlo</button>
    </div>
</x-layouts.guest>
