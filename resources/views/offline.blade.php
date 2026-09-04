<x-layouts.guest title="Sin conexión">
    <div class="card text-center">
        <span class="mx-auto grid size-12 place-items-center rounded-full bg-neutral-100 text-neutral-500"><svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg></span>
        <h2 class="mt-3 text-lg font-semibold text-neutral-900">Sin conexión</h2>
        <p class="mx-auto mt-1 max-w-sm text-sm text-neutral-500">
            No hay red ahora mismo. Las pantallas que ya habías abierto siguen disponibles; para apuntar un
            viaje nuevo hace falta conexión, porque el coste se calcula en el servidor.
        </p>
        <button type="button" class="btn-primary mt-4" onclick="window.location.reload()">Volver a intentarlo</button>
    </div>
</x-layouts.guest>
