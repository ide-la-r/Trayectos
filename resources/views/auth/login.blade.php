<x-layouts.guest title="Entrar · Libro de Trayectos">
    <form method="POST" action="{{ route('login') }}" class="card space-y-4">
        @csrf

        <div>
            <label class="label" for="email">Correo</label>
            <input id="email" name="email" type="email" class="field" required autofocus
                   autocomplete="email" value="{{ old('email') }}">
        </div>

        <div>
            <label class="label" for="password">Contraseña</label>
            <input id="password" name="password" type="password" class="field" required
                   autocomplete="current-password">
        </div>

        <button type="submit" class="btn-primary w-full">Entrar</button>

        <p class="text-center text-sm text-neutral-500">
            ¿Primera vez?
            <a href="{{ route('register') }}" class="font-semibold text-neutral-900 underline">Crea tu cuenta</a>
        </p>
    </form>
</x-layouts.guest>
