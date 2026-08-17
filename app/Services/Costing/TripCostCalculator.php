<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Enums\Powertrain;

/**
 * Motor de coste de un trayecto.
 *
 * El consumo homologado de un coche corresponde a terreno esencialmente llano.
 * Todo desnivel añade una energía potencial (m·g·Δh) que el motor debe entregar,
 * y de la que sólo se recupera una parte al bajar. Ese factor de recuperación
 * es lo que separa de verdad a un híbrido de un motor de combustión: el térmico
 * disipa la bajada en los frenos, el híbrido la devuelve a la batería — pero
 * sólo hasta que la batería se llena, que es antes de lo que la gente cree.
 */
final class TripCostCalculator
{
    public function calculate(TripCostRequest $request): TripCostBreakdown
    {
        $vehicle = $request->vehicle;
        $physics = config('trayectos.physics');

        $distanceKm = $request->distanceKm();
        $massKg = $request->massKg();

        // ── 1. Cuánto descenso puede realmente recuperar este vehículo ──────
        $regenCeilingM = $vehicle->regenCeilingMetres($massKg);
        $usableDescentM = $vehicle->powertrain->hasBattery()
            ? min((float) $request->descentM, $regenCeilingM)
            : (float) $request->descentM;

        // ── 2. Energía gravitatoria neta que hay que entregar en la rueda ───
        $effectiveRiseM = $request->ascentM - ($vehicle->regen_factor * $usableDescentM);
        $wheelEnergyJoules = $massKg * (float) $physics['gravity'] * $effectiveRiseM;

        // ── 3. Reparto del trayecto entre energía de red y combustible ──────
        $evShare = $this->electricShare($request, $distanceKm);
        $evKm = $distanceKm * $evShare;
        $iceKm = $distanceKm - $evKm;

        $floor = (float) $physics['consumption_floor_ratio'];

        // ── 4. Tramo eléctrico ──────────────────────────────────────────────
        $kwhBase = $vehicle->consumption_kwh_100
            ? ($vehicle->consumption_kwh_100 / 100) * $evKm
            : 0.0;
        $kwhHill = 0.0;

        if ($kwhBase > 0 || $evKm > 0) {
            $kwhHill = ($wheelEnergyJoules * $evShare) / 3.6e6 / (float) $physics['ev_drivetrain_efficiency'];
        }

        $kwhTotal = $kwhBase > 0 ? max($kwhBase * $floor, $kwhBase + $kwhHill) : 0.0;

        // ── 5. Tramo térmico ────────────────────────────────────────────────
        $litresBase = $vehicle->consumption_l_100
            ? ($vehicle->consumption_l_100 / 100) * $iceKm
            : 0.0;
        $litresHill = 0.0;

        $lhv = $vehicle->fuel_kind->energyPerUnitJoules();

        if ($litresBase > 0 && $lhv && $vehicle->thermal_efficiency > 0) {
            $litresHill = ($wheelEnergyJoules * (1 - $evShare)) / ($vehicle->thermal_efficiency * $lhv);
        }

        $litresTotal = $litresBase > 0 ? max($litresBase * $floor, $litresBase + $litresHill) : 0.0;

        // ── 6. A dinero, con el factor de calibración del vehículo ──────────
        $calibration = $vehicle->calibration_factor ?: 1.0;

        $totalCents = $this->toCents(
            $kwhTotal * ($request->energyPriceMilli / 1000)
                + $litresTotal * ($request->fuelPriceMilli / 1000),
            $calibration
        );

        // El mismo trayecto en llano, para poder enseñar cuánto pesa la orografía
        $flatCents = $this->toCents(
            $kwhBase * ($request->energyPriceMilli / 1000)
                + $litresBase * ($request->fuelPriceMilli / 1000),
            $calibration
        );

        return new TripCostBreakdown(
            totalCents: $totalCents,
            flatCostCents: $flatCents,
            litres: $litresTotal,
            kwh: $kwhTotal,
            massKg: $massKg,
            effectiveRiseM: $effectiveRiseM,
            regenCeilingM: $regenCeilingM,
            usableDescentM: $usableDescentM,
            evShare: $evShare,
            inputs: [
                'vehicle_id' => $vehicle->id,
                'vehicle_label' => $vehicle->label,
                'powertrain' => $vehicle->powertrain->value,
                'fuel_kind' => $vehicle->fuel_kind->value,
                'distance_m' => $request->distanceM,
                'ascent_m' => $request->ascentM,
                'descent_m' => $request->descentM,
                'occupants' => $request->occupants,
                'luggage_kg' => $request->luggageKg,
                'battery_start_pct' => $request->batteryStartPct,
                'consumption_l_100' => $vehicle->consumption_l_100,
                'consumption_kwh_100' => $vehicle->consumption_kwh_100,
                'battery_kwh_usable' => $vehicle->battery_kwh_usable,
                'regen_factor' => $vehicle->regen_factor,
                'thermal_efficiency' => $vehicle->thermal_efficiency,
                'calibration_factor' => $calibration,
                'fuel_price_milli' => $request->fuelPriceMilli,
                'energy_price_milli' => $request->energyPriceMilli,
                'fuel_station_id' => $request->fuelStationId,
            ],
        );
    }

    /**
     * Fracción del trayecto que se recorre con energía de la batería.
     *
     * Un híbrido no enchufable devuelve 0: su batería es un amortiguador, no
     * una fuente. Toda su energía sigue viniendo del depósito, y su ventaja ya
     * está recogida en el consumo homologado y en el factor de regeneración.
     */
    private function electricShare(TripCostRequest $request, float $distanceKm): float
    {
        $vehicle = $request->vehicle;

        if ($distanceKm <= 0) {
            return $vehicle->powertrain === Powertrain::Electric ? 1.0 : 0.0;
        }

        if ($vehicle->powertrain === Powertrain::Electric) {
            return 1.0;
        }

        if ($vehicle->powertrain !== Powertrain::PluginHybrid) {
            return 0.0;
        }

        $availableEvKm = ($vehicle->ev_range_km ?? 0) * ($request->batteryStartPct / 100);

        return min(1.0, $availableEvKm / $distanceKm);
    }

    private function toCents(float $euros, float $calibration): int
    {
        return (int) max(0, round($euros * $calibration * 100));
    }
}
