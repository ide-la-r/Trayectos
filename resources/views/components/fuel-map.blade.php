@props(['stations', 'area'])

{{--
    Mapa de gasolineras con el precio escrito encima, como en Gasall: se lee de
    un vistazo sin tocar ningún punto.

    Igual que el mapa del viaje, no se descarga nada al abrir la pantalla —son
    274 kB y teselas de un tercero— y el listado de debajo funciona sin él.
--}}
<section class="mt-4">
    <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">El mapa de tu zona</h2>

    <div x-data="fuelMap({ stations: @js($stations->values()) })" class="card p-4">
        <div x-show="! visible" x-cloak>
            <button type="button" @click="show()" :disabled="loading"
                    class="btn inline-flex w-full items-center justify-center gap-2 bg-neutral-900 px-4 py-2.5 text-sm text-white transition hover:bg-neutral-800 disabled:opacity-60 sm:w-auto">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                </svg>
                <span x-text="loading ? 'Cargando el mapa…' : 'Ver las {{ $stations->count() }} en el mapa'">Ver las {{ $stations->count() }} en el mapa</span>
            </button>

            <p class="mt-1.5 text-xs text-neutral-500">
                Con su precio encima de cada una. Se descarga sólo si lo pides,
                para no gastarte datos sin avisar.
            </p>
        </div>

        <p x-show="failed" x-cloak class="text-sm text-neutral-600">
            No se ha podido cargar el mapa. El listado de aquí arriba no depende de él.
        </p>

        <div x-show="visible && ! failed" x-cloak>
            <div x-ref="canvas" class="h-80 w-full overflow-hidden rounded-xl border border-neutral-200 bg-neutral-100"
                 role="img" aria-label="Mapa de las gasolineras de la zona con sus precios"></div>

            {{-- El color no es la información —el precio va escrito al lado— pero
                 conviene decir qué significa. --}}
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500">
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-credit-700"></span> De las más baratas
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-neutral-700"></span> En la media
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-debt-700"></span> De las más caras
                </span>
            </div>

            <p class="mt-1.5 text-xs text-neutral-500">
                Acerca el zoom para que salgan más: se esconden las etiquetas que se pisan.
            </p>
        </div>
    </div>
</section>
