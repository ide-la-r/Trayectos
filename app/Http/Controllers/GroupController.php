<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Services\Drivers\DriverSuggestionService;
use App\Services\Drivers\DriverTallyService;
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
            ->with('status', 'Grupo creado. Comparte el enlace de invitación con los demás, abajo de esta pantalla.');
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

        return $this->addMember($group, $request->user());
    }

    /**
     * Pantalla a la que lleva un enlace de invitación. Es pública a propósito:
     * si quien lo abre no tiene sesión hay que poder enseñarle a qué grupo le
     * invitan antes de pedirle que se registre, o el enlace no sirve de nada.
     */
    public function invitation(Request $request, string $code): View|RedirectResponse
    {
        $group = Group::where('invite_code', strtoupper(trim($code)))->first();

        if (! $group) {
            return view('groups.invitation', ['group' => null]);
        }

        $user = $request->user();

        // Ya dentro y activo: el enlace no tiene nada que hacer aquí
        if ($user && $user->memberIn($group)?->active) {
            return redirect()->route('groups.show', $group);
        }

        // Sin sesión, se guarda el destino para volver aquí después de entrar
        if (! $user) {
            $request->session()->put('url.intended', route('groups.invitation', $group->invite_code));
        }

        return view('groups.invitation', ['group' => $group]);
    }

    /**
     * Confirmación explícita del enlace. Abrir un enlace no debe cambiar nada
     * por sí solo: se entra al grupo al pulsar el botón, no al tocar el mensaje
     * de WhatsApp.
     */
    public function acceptInvitation(Request $request, string $code): RedirectResponse
    {
        $group = Group::where('invite_code', strtoupper(trim($code)))->firstOrFail();

        return $this->addMember($group, $request->user());
    }

    /**
     * Alta en el grupo, compartida por el código y por el enlace. Reactivar en
     * lugar de crear evita duplicar la ficha de quien ya estuvo y se salió: sus
     * líneas del libro siguen apuntando a ese group_member.
     */
    private function addMember(Group $group, User $user): RedirectResponse
    {
        $member = $user->memberIn($group);

        if ($member) {
            $member->update(['active' => true]);
        } else {
            $group->members()->create(['user_id' => $user->id]);
        }

        return redirect()->route('groups.show', $group)
            ->with('status', "Bienvenido a {$group->name}.");
    }

    public function show(
        Request $request,
        Group $group,
        BalanceService $balances,
        DriverSuggestionService $suggestions,
        DriverTallyService $tally,
    ): View {
        $member = $request->attributes->get('group_member');

        return view('groups.show', [
            'group' => $group,
            'member' => $member,
            'balances' => $balances->forGroup($group),
            'myBalance' => $balances->forMember($member),
            'suggestions' => $suggestions->suggest($group),
            'tally' => $tally->forGroup($group),
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
