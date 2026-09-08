@props([
    'name',
    'label',
    'value' => null,
    'lat' => null,
    'lon' => null,
    'placeholder' => '',
    // En un viaje el sitio es obligatorio; eligiendo zona de precios, no.
    'required' => true,
    'hint' => 'Elige una opción de la lista para calcular la ruta, o escribe los kilómetros a mano más abajo.',
])

{{--
    Buscador de lugares. Las coordenadas se guardan en campos ocultos: si el
    usuario reescribe el texto a mano se limpian, porque ya no corresponden al
    sitio elegido.
--}}
<div x-data="placeField({ name: '{{ $name }}', label: @js($value), lat: @js($lat), lon: @js($lon) })"
     class="relative">
    <label class="label" for="{{ $name }}_label">{{ $label }}</label>

    <input id="{{ $name }}_label" name="{{ $name }}_label" type="text" class="field"
           @required($required) autocomplete="off" placeholder="{{ $placeholder }}"
           x-model="query" @focus="open = results.length > 0" @keydown.escape="open = false">

    <input type="hidden" name="{{ $name }}_lat" :value="lat">
    <input type="hidden" name="{{ $name }}_lon" :value="lon">

    <div x-show="loading" class="absolute top-9 right-3 text-xs text-neutral-400">buscando…</div>

    <ul x-show="open" x-cloak @click.outside="open = false"
        class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-neutral-200 bg-white shadow-lg">
        <template x-for="place in results" :key="`${place.lat},${place.lon}`">
            <li>
                <button type="button" @click="choose(place)"
                        class="block w-full px-4 py-2.5 text-left hover:bg-neutral-50">
                    <span class="block text-sm font-medium text-neutral-900" x-text="place.label"></span>
                    <span class="block text-xs text-neutral-500" x-text="place.context"></span>
                </button>
            </li>
        </template>
    </ul>

    <p x-show="! lat && query.length > 2" x-cloak class="mt-1 text-xs text-neutral-500">
        {{ $hint }}
    </p>
</div>
