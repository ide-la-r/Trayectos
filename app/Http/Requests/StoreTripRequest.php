<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    /** Aquí se escriben los decimales con coma; 'numeric' sólo entiende el punto. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'distance_km' => Money::normalizeInput($this->input('distance_km')),
        ]);
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

            // Sin coordenadas no hay forma de calcular la ruta: hace falta que
            // alguien ponga los kilómetros a mano.
            if (! $hasCoordinates && blank($this->input('distance_km'))) {
                $validator->errors()->add(
                    'distance_km',
                    'Elige origen y destino del buscador, o escribe los kilómetros a mano.'
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
