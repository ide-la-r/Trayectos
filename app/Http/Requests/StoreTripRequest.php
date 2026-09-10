<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Geocoding\PlaceResolver;
use App\Support\FuelArea;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Aquí se escriben los decimales con coma; 'numeric' sólo entiende el punto
        $this->merge([
            'distance_km' => Money::normalizeInput($this->input('distance_km')),
        ]);

        $this->resolveMissingPlaces();
    }

    /**
     * Buscar las coordenadas de lo que se haya escrito sin elegir de la lista.
     *
     * Elegir del desplegable las rellena, pero cualquiera escribe «Málaga» y le
     * da a guardar sin tocar la lista. Antes eso se contestaba con «elige
     * origen y destino del buscador», que es pedirle a la persona un dato que
     * la aplicación ya sabe.
     *
     * Va antes de validar para que el resto de reglas —y el aviso de más
     * abajo— vean el viaje ya completo. Sólo se busca lo que falta.
     */
    private function resolveMissingPlaces(): void
    {
        // Con los kilómetros a mano no hace falta ruta ninguna
        if (filled($this->input('distance_km'))) {
            return;
        }

        $resolver = app(PlaceResolver::class);
        $area = FuelArea::fromArray($this->session()->get(FuelArea::SESSION_KEY));

        foreach (['origin', 'destination'] as $field) {
            if (filled($this->input("{$field}_lat")) && filled($this->input("{$field}_lon"))) {
                continue;
            }

            $found = $resolver->resolve(
                $this->input("{$field}_label"),
                $this->input("{$field}_lat"),
                $this->input("{$field}_lon"),
                nearLat: $area?->lat,
                nearLon: $area?->lon,
            );

            if ($found) {
                $this->merge(["{$field}_lat" => $found[0], "{$field}_lon" => $found[1]]);
            }
        }
    }

    public function rules(): array
    {
        $groupId = $this->route('group')->id;

        return [
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_member_id' => ['required', "exists:group_members,id,group_id,{$groupId}"],
            'travelled_on' => ['required', 'date', 'before_or_equal:today'],

            'origin_label' => ['required', 'string', 'max:160'],
            'destination_label' => ['required', 'string', 'max:160'],
            'origin_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'origin_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'destination_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_lon' => ['nullable', 'numeric', 'between:-180,180'],

            'round_trip' => ['nullable', 'boolean'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'ascent_m' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'descent_m' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'luggage_kg' => ['nullable', 'integer', 'min:0', 'max:500'],
            'battery_start_pct' => ['nullable', 'integer', 'min:0', 'max:100'],

            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*' => ["exists:group_members,id,group_id,{$groupId}"],
            'weights' => ['nullable', 'array'],
            'weights.*' => ['nullable', 'numeric', 'min:0.1', 'max:1'],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_id' => 'coche',
            'driver_member_id' => 'conductor',
            'travelled_on' => 'fecha',
            'origin_label' => 'origen',
            'destination_label' => 'destino',
            'distance_km' => 'distancia',
            'passengers' => 'ocupantes',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasCoordinates = filled($this->input('origin_lat')) && filled($this->input('destination_lat'));

            /*
             * Llegados aquí ya se ha intentado buscar los sitios escritos a
             * mano, así que si siguen sin coordenadas es que el buscador no los
             * conoce. El aviso dice eso y no «elige del buscador», que era
             * desconcertante para quien había escrito los dos sitios.
             */
            if (! $hasCoordinates && blank($this->input('distance_km'))) {
                $validator->errors()->add(
                    'distance_km',
                    'No hemos encontrado esos sitios en el mapa. Prueba a elegirlos de la lista '
                        .'que sale al escribir, o pon los kilómetros a mano aquí abajo.'
                );
            }

            // El conductor viaja siempre en su propio coche
            $passengers = array_map('intval', $this->input('passengers', []));

            if (! in_array((int) $this->input('driver_member_id'), $passengers, true)) {
                $validator->errors()->add('passengers', 'El conductor también va en el coche: márcalo.');
            }
        });
    }
}
