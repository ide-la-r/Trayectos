{{--
    Instalar la aplicación en el móvil. En iOS no existe el evento de
    instalación, así que hay que enseñar el camino: Compartir → Añadir a
    pantalla de inicio. Sin este aviso, nadie la instala.
--}}
<div x-data="installPrompt" x-cloak>
    <div x-show="canPrompt" class="mb-4 flex items-center gap-3 rounded-xl border border-neutral-200 bg-white px-4 py-3">
        <svg class="size-5 shrink-0 text-neutral-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
        <p class="flex-1 text-sm text-neutral-700">Instálala como aplicación y ábrela sin navegador.</p>
        <button type="button" class="btn-primary py-1.5 text-xs" @click="install()">Instalar</button>
        <button type="button" class="text-neutral-400 hover:text-neutral-600" @click="dismiss()" aria-label="Cerrar">✕</button>
    </div>

    <div x-show="showIosHelp" class="mb-4 rounded-xl border border-neutral-200 bg-white px-4 py-3">
        <div class="flex items-start gap-3">
            <svg class="size-5 shrink-0 text-neutral-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
            <div class="flex-1 text-sm text-neutral-700">
                <p class="font-medium text-neutral-900">Añádela a la pantalla de inicio</p>
                <p class="mt-0.5">
                    Toca <span class="font-semibold">Compartir</span> en la barra de Safari y elige
                    <span class="font-semibold">Añadir a pantalla de inicio</span>. Se abrirá como una aplicación,
                    a pantalla completa y sin pasar por la App Store.
                </p>
            </div>
            <button type="button" class="text-neutral-400 hover:text-neutral-600" @click="dismiss()" aria-label="Cerrar">✕</button>
        </div>
    </div>
</div>
