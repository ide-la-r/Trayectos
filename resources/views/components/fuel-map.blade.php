@props(['stations', 'area'])

{{--
    Mapa de gasolineras con la marca y el precio escritos encima, como en
    Gasall: se lee de un vistazo sin tocar ningún punto.

    Igual que el mapa del viaje, no se descarga nada al abrir la pantalla —son
    274 kB y teselas de un tercero— y el listado de arriba funciona sin él.
--}}
<section class="mt-4">
    <h2 class="mb-2 px-1 text-sm font-semibold text-neutral-900">El mapa de tu zona</h2>

    <div x-data="fuelMap({ stations: @js($stations->values()) })"
         @keydown.escape.window="close()">

        {{-- ─── Antes de cargarlo ──────────────────────────────────────────── --}}
        <div x-show="! visible" x-cloak class="card p-4">
            <button type="button" @click="show()" :disabled="loading"
                    class="btn inline-flex w-full items-center justify-center gap-2 bg-neutral-900 px-4 py-2.5 text-sm text-white transition hover:bg-neutral-800 disabled:opacity-60 sm:w-auto">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                </svg>
                <span x-text="loading ? 'Cargando el mapa…' : 'Ver las {{ $stations->count() }} en el mapa'">Ver las {{ $stations->count() }} en el mapa</span>
            </button>

            <p class="mt-1.5 text-xs text-neutral-500">
                Con su marca y su precio encima. Se descarga sólo si lo pides,
                para no gastarte datos sin avisar.
            </p>
        </div>

        <p x-show="failed" x-cloak class="card p-4 text-sm text-neutral-600">
            No se ha podido cargar el mapa. El listado de aquí arriba no depende de él.
        </p>

        {{-- ─── El mapa ────────────────────────────────────────────────────────
             Ampliado se convierte en una capa fija sobre todo lo demás. Por CSS
             y no con la API de pantalla completa del navegador, que en iPhone
             no existe. --}}
        <div x-show="visible && ! failed" x-cloak
             :class="expanded ? 'fixed inset-0 z-50 bg-white' : 'card p-4'">

            <div class="relative" :class="expanded ? 'h-dvh' : ''">
                <div x-ref="canvas"
                     :class="expanded ? 'h-full w-full' : 'h-[26rem] w-full rounded-xl border border-neutral-200'"
                     class="overflow-hidden bg-neutral-100"
                     role="img" aria-label="Mapa de las gasolineras de la zona con sus precios"></div>

                {{-- Ampliar y cerrar. Arriba a la izquierda porque el zoom de
                     MapLibre va a la derecha; con el móvil ampliado hay que
                     esquivar la muesca. --}}
                <button type="button" @click="toggleExpand()"
                        :style="{ top: expanded ? 'max(0.75rem, env(safe-area-inset-top))' : '0.75rem' }"
                        class="absolute left-3 z-10 grid size-10 place-items-center rounded-lg border border-neutral-200 bg-white text-neutral-700 shadow-sm transition hover:bg-neutral-50"
                        :aria-label="expanded ? 'Salir de pantalla completa' : 'Ver el mapa a pantalla completa'">
                    <svg x-show="! expanded" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
                    </svg>
                    <svg x-show="expanded" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>

                {{-- ─── Ficha de la gasolinera tocada ──────────────────────────
                     Todo con x-text: los rótulos y las direcciones vienen de la
                     API del Ministerio y no entran como HTML en ningún caso. --}}
                {{-- OJO con el :style: en forma de cadena reescribe el atributo
                     entero y se lleva por delante el «display: none» que pone
                     x-show, así que la ficha se veía vacía sobre el mapa. En
                     forma de objeto Alpine toca sólo esa propiedad. --}}
                <div x-show="chosen" x-cloak
                     :style="{ bottom: expanded ? 'max(0.75rem, env(safe-area-inset-bottom))' : '0.75rem' }"
                     class="absolute inset-x-3 z-10 rounded-xl border border-neutral-200 bg-white p-3 shadow-lg sm:max-w-sm">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg text-xs font-bold"
                              :style="`background:${chosen?.brand.bg};color:${chosen?.brand.ink}`"
                              x-text="chosen?.brand.short"></span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-neutral-900" x-text="chosen?.label"></p>
                            <p class="truncate text-xs text-neutral-500"
                               x-text="[chosen?.municipality, chosen?.address].filter(Boolean).join(' · ')"></p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="money text-sm font-semibold text-neutral-900 tabular-nums">
                                <span x-text="chosen?.price"></span> €
                            </p>
                            <p class="text-xs text-neutral-400" x-show="chosen?.distance" x-cloak>
                                a <span x-text="chosen?.distance"></span> km
                            </p>
                        </div>
                    </div>

                    <div class="mt-2.5 flex items-center gap-3">
                        <a :href="directionsFor(chosen)" target="_blank" rel="noopener"
                           class="btn inline-flex items-center gap-1.5 bg-neutral-900 px-3 py-1.5 text-xs text-white transition hover:bg-neutral-800">
                            <svg class="size-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20.94 3.06a1 1 0 0 0-1.06-.22L3.5 9.2a1 1 0 0 0 .06 1.87l6.9 2.47 2.47 6.9a1 1 0 0 0 1.87.06l6.36-16.38a1 1 0 0 0-.22-1.06Z"/>
                            </svg>
                            Cómo llegar
                        </a>

                        <button type="button" @click="chosen = null"
                                class="text-xs text-neutral-500 underline underline-offset-2 hover:text-neutral-800">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>

            {{-- La leyenda sobra cuando el mapa ocupa la pantalla entera --}}
            <div x-show="! expanded" class="mt-2 space-y-1">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500">
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
                <p class="text-xs text-neutral-500">
                    Toca una para ver sus datos. Acerca el zoom para que salgan más:
                    se esconden las que se pisan.
                </p>
            </div>
        </div>
    </div>
</section>
