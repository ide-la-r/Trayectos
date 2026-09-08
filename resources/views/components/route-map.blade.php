@props(['geometry'])

{{--
    El mapa no se descarga al abrir la pantalla: MapLibre son unos 230 kB y las
    teselas las sirve un tercero. El perfil de altitud, que es lo que explica el
    coste, ya está dibujado sin nada de eso. Esto es para quien además quiera
    ver por dónde se fue.
--}}
<div x-data="tripMap({ geometry: @js($geometry) })" class="mt-4 border-t border-neutral-100 pt-4">
    <div x-show="! visible" x-cloak>
        <button type="button" @click="show()" :disabled="loading"
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-50 disabled:opacity-60">
            <svg class="size-4 text-neutral-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z"/>
            </svg>
            <span x-text="loading ? 'Cargando el mapa…' : 'Ver el recorrido en el mapa'">Ver el recorrido en el mapa</span>
        </button>

        <p class="mt-1.5 text-xs text-neutral-500">
            Se descarga sólo si lo pides, para no gastarte datos sin avisar.
        </p>
    </div>

    <p x-show="failed" x-cloak class="text-sm text-neutral-600">
        No se ha podido cargar el mapa. El perfil de aquí arriba no depende de él.
    </p>

    <div x-ref="canvas" x-show="visible && ! failed" x-cloak
         class="h-64 w-full overflow-hidden rounded-xl border border-neutral-200 bg-neutral-100"
         role="img" aria-label="Mapa del recorrido"></div>
</div>
