<x-layouts.guest title="Entrar · Libro de Trayectos"
                heading="Entra en tu grupo"
                tagline="Tus viajes, tus coches y las cuentas al día.">

    <form method="POST" action="{{ route('login') }}" class="card space-y-5 p-6 shadow-lg shadow-neutral-950/5">
        @csrf

        <div>
            <label class="label" for="email">Correo</label>
            <input id="email" name="email" type="email" class="field" required autofocus
                   autocomplete="email" placeholder="tu@correo.com" value="{{ old('email') }}">
        </div>

        <x-password-field autocomplete="current-password" />

        <button type="submit" class="btn-primary w-full py-3">Entrar</button>

        {{-- El controlador entra con remember: true siempre, así que esto describe
             lo que de verdad pasa en lugar de ofrecer una casilla que no existe. --}}
        <p class="text-center text-xs text-neutral-400">
            La sesión queda abierta en este dispositivo.
        </p>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        ¿Primera vez por aquí?
        <a href="{{ route('register') }}"
           class="font-semibold text-neutral-900 underline decoration-neutral-300 underline-offset-2 transition hover:decoration-neutral-900">
            Crea tu cuenta
        </a>
    </p>

</x-layouts.guest>
