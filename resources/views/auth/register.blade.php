<x-layouts.guest title="Crear cuenta · Libro de Trayectos"
                heading="Crea tu cuenta"
                tagline="En un minuto, y sin tarjeta de nada.">

    <form method="POST" action="{{ route('register') }}" class="card space-y-5 p-6 shadow-lg shadow-neutral-950/5">
        @csrf

        <div>
            <label class="label" for="name">Nombre</label>
            <input id="name" name="name" type="text" class="field" required autofocus
                   autocomplete="name" placeholder="Como te llame tu cuadrilla" value="{{ old('name') }}">
        </div>

        <div>
            <label class="label" for="email">Correo</label>
            <input id="email" name="email" type="email" class="field" required
                   autocomplete="email" placeholder="tu@correo.com" value="{{ old('email') }}">
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="label" for="password">Contraseña</label>
                <input id="password" name="password" type="password" class="field" required
                       autocomplete="new-password" placeholder="••••••••">
                <p class="mt-1.5 text-xs text-neutral-500">Ocho caracteres o más.</p>
            </div>

            <div>
                <label class="label" for="password_confirmation">Repítela</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="field" required
                       autocomplete="new-password" placeholder="••••••••">
            </div>
        </div>

        <div class="rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-4">
            <label class="label mb-1.5" for="invite_code">
                Código de invitación
                <span class="font-normal text-neutral-400">· opcional</span>
            </label>
            <input id="invite_code" name="invite_code" type="text" class="field uppercase"
                   value="{{ old('invite_code', $inviteCode) }}" placeholder="ABC123">
            <p class="mt-1.5 text-xs text-neutral-500">
                Si alguien te ha invitado a su grupo, pégalo aquí y entrarás directo. Si no, luego
                podrás crear el tuyo.
            </p>
        </div>

        <button type="submit" class="btn-primary w-full py-3">Crear cuenta</button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}"
           class="font-semibold text-neutral-900 underline decoration-neutral-300 underline-offset-2 transition hover:decoration-neutral-900">
            Entra
        </a>
    </p>

</x-layouts.guest>
