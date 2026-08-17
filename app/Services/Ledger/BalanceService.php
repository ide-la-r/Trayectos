<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BalanceService
{
    /**
     * Saldo de cada miembro del grupo, derivado siempre de journal_lines.
     * (+) el grupo le debe · (−) debe al grupo.
     *
     * @return Collection<int, object{member: GroupMember, balance_cents: int}>
     */
    public function forGroup(Group $group): Collection
    {
        $totals = DB::table('journal_lines')
            ->join('group_members', 'group_members.id', '=', 'journal_lines.group_member_id')
            ->where('group_members.group_id', $group->id)
            ->groupBy('journal_lines.group_member_id')
            ->select('journal_lines.group_member_id', DB::raw('SUM(amount_cents) AS balance_cents'))
            ->pluck('balance_cents', 'group_member_id');

        return $group->members()
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map(fn (GroupMember $member) => (object) [
                'member' => $member,
                'balance_cents' => (int) ($totals[$member->id] ?? 0),
            ])
            ->sortByDesc('balance_cents')
            ->values();
    }

    public function forMember(GroupMember $member): int
    {
        return (int) DB::table('journal_lines')
            ->where('group_member_id', $member->id)
            ->sum('amount_cents');
    }

    /**
     * Plan de liquidación: quién paga a quién para dejar todos los saldos a
     * cero con el menor número de pagos posible.
     *
     * Greedy sobre acreedores y deudores ordenados por importe. No garantiza el
     * óptimo teórico (es un problema NP-duro), pero con los tamaños de grupo
     * reales (5-15 personas) da el mismo resultado que la solución exacta y se
     * calcula al instante.
     *
     * @return array<int, array{from: GroupMember, to: GroupMember, amount_cents: int}>
     */
    public function settlementPlan(Group $group): array
    {
        $balances = $this->forGroup($group);

        $creditors = $balances->filter(fn ($row) => $row->balance_cents > 0)
            ->sortByDesc('balance_cents')->values()->all();
        $debtors = $balances->filter(fn ($row) => $row->balance_cents < 0)
            ->sortBy('balance_cents')->values()->all();

        $plan = [];
        $i = $j = 0;
        $creditLeft = $creditors === [] ? 0 : $creditors[0]->balance_cents;
        $debtLeft = $debtors === [] ? 0 : -$debtors[0]->balance_cents;

        while ($i < count($creditors) && $j < count($debtors)) {
            $amount = min($creditLeft, $debtLeft);

            if ($amount > 0) {
                $plan[] = [
                    'from' => $debtors[$j]->member,
                    'to' => $creditors[$i]->member,
                    'amount_cents' => $amount,
                ];
            }

            $creditLeft -= $amount;
            $debtLeft -= $amount;

            if ($creditLeft === 0 && ++$i < count($creditors)) {
                $creditLeft = $creditors[$i]->balance_cents;
            }

            if ($debtLeft === 0 && ++$j < count($debtors)) {
                $debtLeft = -$debtors[$j]->balance_cents;
            }
        }

        return $plan;
    }

    /**
     * Comprobación de integridad: la suma de todos los saldos de un grupo debe
     * ser exactamente cero. Si no lo es, algo ha escrito sin pasar por el
     * LedgerService y hay que auditarlo.
     */
    public function isConsistent(Group $group): bool
    {
        return $this->forGroup($group)->sum('balance_cents') === 0;
    }
}
