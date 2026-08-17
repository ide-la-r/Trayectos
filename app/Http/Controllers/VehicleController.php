<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FuelKind;
use App\Enums\Powertrain;
use App\Models\Vehicle;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        return view('vehicles.index', [
            'vehicles' => $request->user()->vehicles()->withCount('trips')->orderBy('label')->get(),
        ]);
    }

    public function create(): View
    {
        return view('vehicles.form', ['vehicle' => new Vehicle(['powertrain' => Powertrain::Combustion])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $request->user()->vehicles()->create($data);

        return redirect()->route('vehicles.index')->with('status', 'Coche guardado.');
    }

    public function edit(Request $request, Vehicle $vehicle): View
    {
        abort_unless($vehicle->owner_id === $request->user()->id, 403);

        return view('vehicles.form', [
            'vehicle' => $vehicle,
            'refuels' => $vehicle->refuels()->latest('refuelled_on')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->owner_id === $request->user()->id, 403);

        $vehicle->update($this->validated($request));

        return redirect()->route('vehicles.index')
            ->with('status', 'Coche actualizado. Los viajes ya apuntados mantienen su coste original.');
    }

    private function validated(Request $request): array
    {
        // Los decimales se escriben con coma en España
        foreach (['consumption_l_100', 'consumption_kwh_100', 'battery_kwh_usable', 'regen_factor', 'thermal_efficiency'] as $field) {
            $request->merge([$field => Money::normalizeInput($request->input($field))]);
        }

        $validator = validator($request->all(), [
            'label' => ['required', 'string', 'max:80'],
            'plate' => ['nullable', 'string', 'max:15'],
            'powertrain' => ['required', Rule::enum(Powertrain::class)],
            'fuel_kind' => ['required', Rule::enum(FuelKind::class)],
            'seats' => ['required', 'integer', 'min:1', 'max:9'],
            'kerb_weight_kg' => ['required', 'integer', 'min:500', 'max:4000'],
            'consumption_l_100' => ['nullable', 'numeric', 'min:0.5', 'max:40'],
            'consumption_kwh_100' => ['nullable', 'numeric', 'min:5', 'max:60'],
            'battery_kwh_usable' => ['nullable', 'numeric', 'min:0.3', 'max:200'],
            'ev_range_km' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'regen_factor' => ['nullable', 'numeric', 'min:0', 'max:0.9'],
            'thermal_efficiency' => ['nullable', 'numeric', 'min:0.1', 'max:0.5'],
            'active' => ['nullable', 'boolean'],
        ], attributes: [
            'label' => 'nombre',
            'powertrain' => 'tecnología',
            'fuel_kind' => 'combustible',
            'seats' => 'plazas',
            'kerb_weight_kg' => 'peso en vacío',
            'consumption_l_100' => 'consumo en litros',
            'consumption_kwh_100' => 'consumo eléctrico',
            'battery_kwh_usable' => 'batería útil',
            'ev_range_km' => 'autonomía eléctrica',
        ]);

        $validator->after(function (Validator $validator) use ($request) {
            $powertrain = Powertrain::tryFrom((string) $request->input('powertrain'));

            if (! $powertrain) {
                return;
            }

            if ($powertrain->burnsFuel() && blank($request->input('consumption_l_100'))) {
                $validator->errors()->add('consumption_l_100', 'Indica el consumo medio en litros a los 100 km.');
            }

            if ($powertrain->drivesOnBattery()) {
                if (blank($request->input('consumption_kwh_100'))) {
                    $validator->errors()->add('consumption_kwh_100', 'Indica el consumo eléctrico a los 100 km.');
                }

                if (blank($request->input('ev_range_km'))) {
                    $validator->errors()->add('ev_range_km', 'Indica la autonomía eléctrica.');
                }
            }

            // Sin batería útil no hay recuperación que modelar en un híbrido
            if ($powertrain->hasBattery() && blank($request->input('battery_kwh_usable'))) {
                $validator->errors()->add('battery_kwh_usable', 'Indica la capacidad útil de la batería.');
            }
        });

        $data = $validator->validate();

        $data['active'] = (bool) ($data['active'] ?? true);

        // Los parámetros del modelo físico caen a los de su tecnología si se dejan vacíos
        $powertrain = Powertrain::from($data['powertrain']);
        $data['regen_factor'] = $data['regen_factor'] ?? $powertrain->defaultRegenFactor();
        $data['thermal_efficiency'] = $data['thermal_efficiency'] ?? $powertrain->defaultThermalEfficiency();

        return $data;
    }
}
