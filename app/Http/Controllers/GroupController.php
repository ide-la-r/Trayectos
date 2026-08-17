<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\Drivers\DriverSuggestionService;
use App\Services\Ledger\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function create(): View
    {
        return view('groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver_pays_own_share' => ['nullable', 'boolean'],
        ], attributes: ['name' => 'nombre']);

        $group = Group::create([
            'name' => $data['name'],
            'driver_pays_own_share' => (bool) ($data['driver_pays_own_share'] ?? true),
            'created_by' => $request->user()->id,
        ]);

        $group->members()->create([
            'user_id' => $request->user()->id,
            'role' => 'admin',
        ]);

        return redirect()->route('groups.show', $group)
            ->with('status', "Grupo creado. Comparte el código {$group->invite_code} con los demás.");
    }

    public function joinForm(): View
    {
        return view('groups.join');
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invite_code' => ['required', 'string', 'max:12'],
        ], attributes: ['invite_code' => 'código de invitación']);

        $group = Group::where('invite_code', strtoupper(trim($data['invite_code'])))->first();

        if (! $group) {
            return back()->withErrors(['invite_code' => 'Ese código no corresponde a ningún grupo.']);
        }

        $member = $request->user()->memberIn($group);

        if ($member) {
            $member->update(['active' => true]);
        } else {
            $group->members()->create(['user_id' => $request->user()->id]);
        }

        return redirect()->route('groups.show', $group)
            ->with('status', "Bienvenido a {$group->name}.");
    }

    public function show(
        Request $request,
        Group $group,
        BalanceService $balances,
        DriverSuggestionService $suggestions,
    ): View {
        $member = $request->attributes->get('group_member');

        return view('groups.show', [
            'group' => $group,
            'member' => $member,
            'balances' => $balances->forGroup($group),
            'myBalance' => $balances->forMember($member),
            'suggestions' => $suggestions->suggest($group),
            'trips' => $group->trips()
                ->with(['driver.user', 'vehicle', 'passengers'])
                ->latest('travelled_on')
                ->latest('id')
                ->limit(10)
                ->get(),
            'canDrive' => $request->user()->vehicles()->where('active', true)->exists(),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $member = $request->attributes->get('group_member');

        if (! $member->isAdmin()) {
            abort(403, 'Sólo un administrador del grupo puede cambiar sus reglas.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver_pays_own_share' => ['nullable', 'boolean'],
        ], attributes: ['name' => 'nombre']);

        $group->update([
            'name' => $data['name'],
            'driver_pays_own_share' => (bool) ($data['driver_pays_own_share'] ?? false),
        ]);

        return back()->with('status', 'Reglas del grupo actualizadas. Los viajes ya apuntados no cambian.');
    }
}
