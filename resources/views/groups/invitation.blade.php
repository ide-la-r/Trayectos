{{--
    Destino de un enlace de invitación. Tres situaciones en una sola vista:
    código que no existe, visitante sin sesión y usuario con sesión.

    Usa el layout de invitado a propósito, incluso para quien ya tiene sesión:
    es una pantalla de decisión, no una pantalla de la aplicación, y el panel de
    marca a la izquierda es justo lo que necesita quien no la conoce.
--}}
@if (! $group)
    <x-layouts.guest title="Invitación no válida · Libro de Trayectos"
                     heading="Esta invitación no vale"
                     tagline="El enlace está mal copiado o el grupo ya no existe.">

        <div class="card p-6 text-center shadow-lg shadow-neutral-950/5">
            <p class="text-sm leading-relaxed text-neutral-600">
                Comprueba que el enlace esté completo: se corta con facilidad al pegarlo en un chat.
                También puedes entrar con el código del grupo, que son ocho caracteres.
            </p>

            <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
                @auth
                    <a href="{{ route('groups.join') }}" class="btn-primary">Entrar con un código</a>
                    <a href="{{ route('dashboard') }}" class="btn-secondary">Ir a mi panel</a>
                @else
                    <a href="{{ route('register') }}" class="btn-primary">Crear una cuenta</a>
                    <a href="{{ route('login') }}" class="btn-secondary">Ya tengo cuenta</a>
                @endauth
            </div>
        </div>

    </x-layouts.guest>
@else
    <x-layouts.guest :title="'Te invitan a '.$group->name.' · Libro de Trayectos'"
                     heading="Te invitan a un grupo"
                     tagline="Los viajes que apuntéis dentro se reparten entre sus miembros, con el coste real de cada trayecto.">

        <div class="card p-6 shadow-lg shadow-neutral-950/5">
            <p class="text-xs font-semibold tracking-[0.12em] text-neutral-400 uppercase">El grupo</p>
            <p class="mt-1.5 text-xl font-semibold tracking-tight text-neutral-900">{{ $group->name }}</p>
            <p class="mt-1 text-sm text-neutral-500">
                {{ trans_choice('{1} :count miembro|[2,*] :count miembros', $group->activeMembers()->count(), ['count' => $group->activeMembers()->count()]) }}
            </p>

            @auth
                <form method="POST" action="{{ route('groups.invitation.accept', $group->invite_code) }}" class="mt-6">
                    @csrf
                    <button type="submit" class="btn-primary w-full py-3">Unirme a {{ $group->name }}</button>
                </form>

                <p class="mt-3 text-center text-xs text-neutral-400">
                    Entras como {{ auth()->user()->name }}.
                    <a href="{{ route('dashboard') }}" class="underline decoration-neutral-300 underline-offset-2 transition hover:decoration-neutral-900">
                        No, gracias
                    </a>
                </p>
            @else
                <div class="mt-6 flex flex-col gap-2">
                    <a href="{{ route('register', ['invitacion' => $group->invite_code]) }}"
                       class="btn-primary w-full py-3">
                        Crear cuenta y unirme
                    </a>
                    <a href="{{ route('login') }}" class="btn-secondary w-full py-3">Ya tengo cuenta</a>
                </div>

                <p class="mt-3 text-center text-xs text-neutral-400">
                    Al entrar volverás aquí para confirmar.
                </p>
            @endauth
        </div>

    </x-layouts.guest>
@endif
