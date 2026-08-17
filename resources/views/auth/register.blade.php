<x-layouts.guest title="Crear cuenta · Libro de Trayectos">
    <form method="POST" action="{{ route('register') }}" class="card space-y-4">
        @csrf

        <div>
            <label class="label" for="name">Nombre</label>
            <input id="name" name="name" type="text" class="field" required autofocus
                   autocomplete="name" value="{{ old('name') }}">
        </div>

        <div>
            <label class="label" for="email">Correo</label>
            <input id="email" name="email" type="email" class="field" required
                   autocomplete="email" value="{{ old('email') }}">
        </div>

        <div>
            <label class="label" for="password">Contraseña</label>
            <input id="password" name="password" type="password" class="field" required
                   autocomplete="new-password">
            <p class="mt-1 text-xs text-neutral-500">Ocho caracteres o más.</p>
        </div>

        <div>
            <label class="label" for="password_confirmation">Repite la contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="field" required
                   autocomplete="new-password">
        </div>

        <div>
            <label class="label" for="invite_code">Código de invitación <span class="font-normal text-neutral-400">(opcional)</span></label>
            <input id="invite_code" name="invite_code" type="text" class="field uppercase"
                   value="{{ old('invite_code', $inviteCode) }}" placeholder="Si te han invitado a un grupo">
        </div>

        <button type="submit" class="btn-primary w-full">Crear cuenta</button>

        <p class="text-center text-sm text-neutral-500">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="font-semibold text-neutral-900 underline">Entra</a>
        </p>
    </form>
</x-layouts.guest>
