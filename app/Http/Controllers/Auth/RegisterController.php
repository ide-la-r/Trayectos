<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', [
            'inviteCode' => $request->query('invitacion'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'invite_code' => ['nullable', 'string', 'max:12'],
        ], attributes: [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
            'invite_code' => 'código de invitación',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Auth::login($user, remember: true);

        // Registrarse con un código de invitación entra directo al grupo
        if (filled($data['invite_code'] ?? null)) {
            $group = Group::where('invite_code', strtoupper($data['invite_code']))->first();

            if ($group) {
                $group->members()->create(['user_id' => $user->id, 'role' => 'member']);

                return redirect()->route('groups.show', $group)
                    ->with('status', "Ya estás dentro de {$group->name}.");
            }
        }

        return redirect()->route('dashboard');
    }
}
