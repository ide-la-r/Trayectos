<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Ledger\BalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BalanceService $balances): View
    {
        $memberships = $request->user()
            ->memberships()
            ->where('active', true)
            ->with('group')
            ->get();

        // Con un solo grupo no tiene sentido enseñar un selector: se entra directo
        $groups = $memberships->map(fn ($member) => (object) [
            'group' => $member->group,
            'member' => $member,
            'balance_cents' => $balances->forMember($member),
        ]);

        return view('dashboard', [
            'groups' => $groups,
            'vehicles' => $request->user()->vehicles()->where('active', true)->get(),
            'onlyGroup' => $groups->count() === 1 ? $groups->first()->group : null,
        ]);
    }
}
