<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\Ledger\BalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function index(Request $request, Group $group, BalanceService $balances): View
    {
        return view('ledger.index', [
            'group' => $group,
            'member' => $request->attributes->get('group_member'),
            'entries' => $group->journalEntries()
                ->with(['lines.member.user', 'reversal'])
                ->latest('occurred_on')
                ->latest('id')
                ->paginate(25),
            'balances' => $balances->forGroup($group),
            'consistent' => $balances->isConsistent($group),
        ]);
    }
}
