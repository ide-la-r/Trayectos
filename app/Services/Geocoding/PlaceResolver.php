<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/**
 * Las coordenadas de un sitio escrito a mano.
 *
 * Elegir del desplegable rellena las coordenadas y todo va bien; el problema
 * es quien escribe «Málaga» y le da a guardar sin tocar la lista, que es lo
 * que hace cualquiera la primera vez. Antes el formulario contestaba «elige
 * origen y destino del buscador o escribe los kilómetros a mano» y ahí se
 * quedaba: la aplicación sabía perfectamente dónde está Málaga y aun así
 * pedía que se lo dijeran otra vez.
 *
 * Así que si faltan las coordenadas se buscan aquí, con el mismo buscador y la
 * misma caché que usa el desplegable. Sólo se llama al guardar y sólo cuando
 * faltan, así que no añade tráfico al servicio de mapas.
 */
final class PlaceResolver
{
    public function __construct(private readonly GeocodingClient $geocoder) {}

    /**
     * Las coordenadas de un sitio, vengan de donde vengan.
     *
     * Si ya llegan del desplegable se devuelven tal cual: lo que la persona ha
     * elegido manda siempre sobre lo que adivine el buscador.
     *
     * @param  float|null  $nearLat  Desde dónde se busca, para desempatar entre
     *                               sitios que se llaman igual
     * @return array{0: float, 1: float}|null
     */
    public function resolve(?string $label, mixed $lat, mixed $lon, ?float $nearLat = null, ?float $nearLon = null): ?array
    {
        if (filled($lat) && filled($lon)) {
            return [(float) $lat, (float) $lon];
        }

        $label = trim((string) $label);

        if ($label === '') {
            return null;
        }

        // El primero: el buscador ya ordena por coincidencia exacta, tamaño del
        // sitio y cercanía, así que el primero es el que habría elegido
        // cualquiera de la lista.
        $place = $this->geocoder->search($label, limit: 1, nearLat: $nearLat, nearLon: $nearLon)[0] ?? null;

        return $place ? [$place->lat, $place->lon] : null;
    }
}
