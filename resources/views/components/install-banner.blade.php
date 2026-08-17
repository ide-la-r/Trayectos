{{--
    Instalar la aplicación en el móvil. En iOS no existe el evento de
    instalación, así que hay que enseñar el camino: Compartir → Añadir a
    pantalla de inicio. Sin este aviso, nadie la instala.
--}}
<div x-data="installPrompt" x-cloak>
    <div x-show="canPrompt" class="mb-4 flex items-center gap-3 rounded-xl border border-neutral-200 bg-white px-4 py-3">
        <span class="text-xl">📲</span>
        <p class="flex-1 text-sm text-neutral-700">Instálala como aplicación y ábrela sin navegador.</p>
        <button type="button" class="btn-primary py-1.5 text-xs" @click="install()">Instalar</button>
        <button type="button" class="text-neutral-400 hover:text-neutral-600" @click="dismiss()" aria-label="Cerrar">✕</button>
    </div>

    <div x-show="showIosHelp" class="mb-4 rounded-xl border border-neutral-200 bg-white px-4 py-3">
        <div class="flex items-start gap-3">
            <span class="text-xl">📲</span>
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
