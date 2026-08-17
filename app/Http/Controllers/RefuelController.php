<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\Costing\CalibrationService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RefuelController extends Controller
{
    public function store(
        Request $request,
        Vehicle $vehicle,
        CalibrationService $calibration,
    ): RedirectResponse {
        abort_unless($vehicle->owner_id === $request->user()->id, 403);

        $request->merge([
            'cost' => Money::normalizeInput($request->input('cost')),
            'litres' => Money::normalizeInput($request->input('litres')),
            'kwh' => Money::normalizeInput($request->input('kwh')),
        ]);

        $data = $request->validate([
            'refuelled_on' => ['required', 'date', 'before_or_equal:today'],
            'litres' => ['nullable', 'numeric', 'min:0.5', 'max:200'],
            'kwh' => ['nullable', 'numeric', 'min:0.5', 'max:300'],
            'cost' => ['required', 'numeric', 'min:0.5', 'max:1000'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'full_tank' => ['nullable', 'boolean'],
        ], attributes: [
            'refuelled_on' => 'fecha',
            'litres' => 'litros',
            'cost' => 'importe',
            'odometer_km' => 'kilómetros',
        ]);

        if (blank($data['litres'] ?? null) && blank($data['kwh'] ?? null)) {
            return back()->withInput()->withErrors([
                'litres' => 'Apunta los litros o los kWh que has repostado.',
            ]);
        }

        $vehicle->refuels()->create([
            'refuelled_on' => $data['refuelled_on'],
            'litres' => $data['litres'] ?? null,
            'kwh' => $data['kwh'] ?? null,
            'cost_cents' => Money::fromEuros($data['cost']),
            'odometer_km' => $data['odometer_km'] ?? null,
            'full_tank' => (bool) ($data['full_tank'] ?? true),
        ]);

        // Cada repostaje real acerca el modelo a este coche concreto
        $result = $calibration->recalculate($vehicle);

        $message = $result
            ? sprintf('Repostaje apuntado. El modelo se ha ajustado al %+.1f %% (factor %.3f).',
                ($result['factor'] - 1) * 100, $result['factor'])
            : 'Repostaje apuntado. Con un par más el sistema podrá ajustar el consumo real de este coche.';

        return back()->with('status', $message);
    }
}
