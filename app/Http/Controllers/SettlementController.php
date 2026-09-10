<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\Ledger\BalanceService;
use App\Services\Ledger\LedgerService;
use App\Services\Push\Announcer;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function index(Request $request, Group $group, BalanceService $balances): View
    {
        return view('settlements.index', [
            'group' => $group,
            'member' => $request->attributes->get('group_member'),
            'plan' => $balances->settlementPlan($group),
            'balances' => $balances->forGroup($group),
            'settlements' => $group->settlements()
                ->with(['from.user', 'to.user'])
                ->latest('settled_on')
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        Group $group,
        LedgerService $ledger,
        Announcer $announcer,
    ): RedirectResponse {
        $groupId = $group->id;

        $request->merge(['amount' => Money::normalizeInput($request->input('amount'))]);

        $data = $request->validate([
            'from_member_id' => ['required', "exists:group_members,id,group_id,{$groupId}"],
            'to_member_id' => ['required', 'different:from_member_id', "exists:group_members,id,group_id,{$groupId}"],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'method' => ['nullable', 'string', 'max:40'],
            'settled_on' => ['required', 'date', 'before_or_equal:today'],
        ], attributes: [
            'from_member_id' => 'quién paga',
            'to_member_id' => 'quién cobra',
            'amount' => 'importe',
            'settled_on' => 'fecha',
        ]);

        $from = $group->members()->findOrFail($data['from_member_id']);
        $to = $group->members()->findOrFail($data['to_member_id']);
        $amountCents = Money::fromEuros($data['amount']);

        $ledger->postSettlement(
            from: $from,
            to: $to,
            amountCents: $amountCents,
            settledOn: Carbon::parse($data['settled_on']),
            method: $data['method'] ?? null,
            createdBy: $request->user()->id,
        );

        // A quien cobra le interesa enterarse sin tener que entrar a mirar
        $announcer->settlementRecorded($from, $to, $amountCents, $request->user()->id);

        return back()->with('status', 'Pago registrado.');
    }
}
