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

        // Cada llenado completo con su cuentakilómetros acerca el modelo a este
        // coche concreto
        return back()->with('status', $this->tell($calibration->recalculate($vehicle)));
    }

    /**
     * Lo que se le cuenta a quien acaba de apuntar el repostaje.
     *
     * El consumo real se dice siempre que se sepa, aunque no se haya podido
     * calibrar: es el dato que uno quiere ver, y no depende de nada más que de
     * haber llenado dos veces con el cuentakilómetros apuntado.
     *
     * @param  array<string, mixed>  $result
     */
    private function tell(array $result): string
    {
        $real = $this->realConsumption($result);

        if ($real === null) {
            return 'Repostaje apuntado. Apunta el cuentakilómetros al llenar y con dos '
                .'llenados completos sabré lo que gasta de verdad.';
        }

        return match ($result['status']) {
            'calibrated' => sprintf(
                'Repostaje apuntado. %s, y el cálculo se ha ajustado un %+.1f %% (factor %.3f).',
                $real,
                ((float) $result['factor'] - 1) * 100,
                $result['factor'],
            ),
            'thin_sample' => "Repostaje apuntado. $real. Con más viajes apuntados podré ajustar también el cálculo.",
            'no_trips' => "Repostaje apuntado. $real. Cuando haya viajes apuntados ajustaré el cálculo con ellos.",
            default => "Repostaje apuntado. $real.",
        };
    }

    /**
     * «Tu coche hace 6,5 L/100 km de verdad», con la unidad que le toque.
     *
     * @param  array<string, mixed>  $result
     */
    private function realConsumption(array $result): ?string
    {
        $figures = collect(['litres_per_100' => 'L', 'kwh_per_100' => 'kWh'])
            ->filter(fn (string $unit, string $key) => ($result[$key] ?? null) !== null)
            ->map(fn (string $unit, string $key) => number_format((float) $result[$key], 1, ',', '.').' '.$unit)
            ->implode(' + ');

        return $figures === '' ? null : "Tu coche hace $figures/100 km de verdad";
    }
}
