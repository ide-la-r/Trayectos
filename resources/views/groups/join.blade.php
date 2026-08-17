<x-layouts.app title="Unirme a un grupo" heading="Unirme a un grupo">
    <form method="POST" action="{{ route('groups.join') }}" class="card space-y-4">
        @csrf

        <div>
            <label class="label" for="invite_code">Código de invitación</label>
            <input id="invite_code" name="invite_code" type="text" class="field text-center text-lg tracking-widest uppercase"
                   required autofocus maxlength="12" value="{{ old('invite_code') }}" placeholder="ABCD1234">
            <p class="mt-1 text-xs text-neutral-500">Te lo pasa quien creó el grupo.</p>
        </div>

        <button type="submit" class="btn-primary w-full">Entrar en el grupo</button>
    </form>
</x-layouts.app>
