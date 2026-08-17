<?php

declare(strict_types=1);

namespace App\Services\Drivers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Vehicle;
use App\Services\Ledger\BalanceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sugerencia del próximo conductor.
 *
 * El criterio principal es el que pide el libro de cuentas: conduce quien más
 * debe. Pero se filtra por quién tiene coche con plazas suficientes — sugerir a
 * alguien que no puede conducir no sirve de nada — y se desempata por turnos
 * recientes, para que quien tenga el saldo casi a cero no acabe conduciendo
 * siempre por inercia.
 */
final class DriverSuggestionService
{
    public function __construct(private readonly BalanceService $balances) {}

    /**
     * @return array<int, array{
     *     member: GroupMember,
     *     balance_cents: int,
     *     recent_trips: int,
     *     vehicles: Collection,
     *     score: float,
     *     reason: string
     * }>
     */
    public function suggest(Group $group, int $neededSeats = 1, ?int $limit = null): array
    {
        $limit ??= (int) config('trayectos.driver_suggestion.suggestions');
        $lookbackDays = (int) config('trayectos.driver_suggestion.lookback_days');
        $fairnessWeight = (float) config('trayectos.driver_suggestion.fairness_weight_eur');

        $recentTrips = DB::table('trips')
            ->where('group_id', $group->id)
            ->where('travelled_on', '>=', now()->subDays($lookbackDays)->toDateString())
            ->groupBy('driver_member_id')
            ->select('driver_member_id', DB::raw('COUNT(*) AS total'))
            ->pluck('total', 'driver_member_id');

        $maxRecent = $recentTrips->max() ?? 0;

        $vehiclesByOwner = Vehicle::query()
            ->where('active', true)
            ->where('seats', '>=', max($neededSeats, 1))
            ->whereIn('owner_id', $group->activeMembers()->pluck('user_id'))
            ->get()
            ->groupBy('owner_id');

        $candidates = [];

        foreach ($this->balances->forGroup($group) as $row) {
            $member = $row->member;

            if (! $member->active) {
                continue;
            }

            $vehicles = $vehiclesByOwner->get($member->user_id);

            if ($vehicles === null || $vehicles->isEmpty()) {
                continue;   // sin coche con plazas suficientes, no es candidato
            }

            $recent = (int) ($recentTrips[$member->id] ?? 0);
            $debtEuros = -$row->balance_cents / 100;              // deuda en positivo
            $fairness = $fairnessWeight * ($maxRecent - $recent); // pocos turnos, más puntos

            $candidates[] = [
                'member' => $member,
                'balance_cents' => $row->balance_cents,
                'recent_trips' => $recent,
                'vehicles' => $vehicles,
                'score' => round($debtEuros + $fairness, 2),
                'reason' => $this->reason($row->balance_cents, $recent, $maxRecent, $lookbackDays),
            ];
        }

        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, $limit);
    }

    private function reason(int $balanceCents, int $recent, int $maxRecent, int $lookbackDays): string
    {
        if ($balanceCents < 0) {
            $debt = number_format(abs($balanceCents) / 100, 2, ',', '.');
            $reason = "Debe {$debt} €";
        } elseif ($balanceCents > 0) {
            $credit = number_format($balanceCents / 100, 2, ',', '.');
            $reason = "Tiene {$credit} € a favor";
        } else {
            $reason = 'Está a cero';
        }

        if ($maxRecent > 0 && $recent < $maxRecent) {
            $reason .= " y ha conducido {$recent} ".($recent === 1 ? 'vez' : 'veces')
                ." en los últimos {$lookbackDays} días";
        }

        return $reason;
    }
}
