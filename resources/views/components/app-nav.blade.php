@props(['group' => null])

{{--
    Una sola definición de la navegación, dos presentaciones: barra inferior con
    el pulgar en móvil y columna lateral en escritorio. Antes solo existía la
    barra inferior, así que en un monitor la aplicación se veía como un móvil
    estirado con la navegación pegada al borde de abajo.
--}}
@php
    $icons = [
        'home' => 'm2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
        'plus' => 'M12 4.5v15m7.5-7.5h-15',
        'book' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
        'card' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
        'car' => 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
        'chart' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
    ];

    $items = [[
        'label' => 'Grupo',
        'url' => $group ? route('groups.show', $group) : route('dashboard'),
        'active' => request()->routeIs('groups.show', 'dashboard'),
        'icon' => 'home',
    ]];

    if ($group) {
        $items[] = ['label' => 'Apuntar', 'url' => route('trips.create', $group), 'active' => request()->routeIs('trips.create'), 'icon' => 'plus'];
        $items[] = ['label' => 'Libro', 'url' => route('ledger.index', $group), 'active' => request()->routeIs('ledger.*'), 'icon' => 'book'];
        $items[] = ['label' => 'Pagar', 'url' => route('settlements.index', $group), 'active' => request()->routeIs('settlements.*'), 'icon' => 'card'];
    }

    $items[] = ['label' => 'Coches', 'url' => route('vehicles.index'), 'active' => request()->routeIs('vehicles.*'), 'icon' => 'car'];
    $items[] = ['label' => 'Precios', 'url' => route('prices'), 'active' => request()->routeIs('prices'), 'icon' => 'chart'];
@endphp

{{-- ── Columna lateral (escritorio) ───────────────────────────────────────── --}}
<aside class="vt-nav-side fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-neutral-200 bg-white px-4 py-5 lg:flex">
    <a href="{{ route('dashboard') }}" class="mb-8 px-2 text-neutral-900" aria-label="Ir al panel">
        <x-logo :wordmark="true" size="size-9" />
    </a>

    <nav class="flex flex-col gap-1" aria-label="Secciones">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}"
               @class([
                   'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                   'bg-neutral-900 text-white' => $item['active'],
                   'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' => ! $item['active'],
               ])>
                <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$item['icon']] }}"/>
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="mt-auto">
        @csrf
        <button type="submit"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900">
            <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18.75 15 21.75 12m0 0-3-3m3 3H9"/>
            </svg>
            Salir
        </button>
    </form>
</aside>

{{-- ── Barra inferior (móvil) ─────────────────────────────────────────────── --}}
<nav class="vt-nav-bottom fixed inset-x-0 bottom-0 z-20 border-t border-neutral-200 bg-white/95 backdrop-blur lg:hidden"
     style="padding-bottom: env(safe-area-inset-bottom)"
     aria-label="Secciones">
    <div class="mx-auto flex max-w-2xl items-stretch px-2">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}" class="nav-item {{ $item['active'] ? 'nav-item-active' : '' }}">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$item['icon']] }}"/>
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
