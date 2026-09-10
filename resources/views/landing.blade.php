<x-layouts.marketing>

{{-- ─── Portada ──────────────────────────────────────────────────────────── --}}
<section class="relative isolate overflow-hidden bg-neutral-950 text-neutral-50">
    {{-- Las curvas de nivel de la marca, a tamaño grande y muy tenues: el
         mismo dibujo del icono sirve de fondo sin necesidad de ninguna imagen. --}}
    <svg class="pointer-events-none absolute -bottom-32 left-1/2 -z-10 w-[52rem] max-w-none -translate-x-1/2 road-ambient"
         viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2"
         stroke-linecap="round" aria-hidden="true">
        <path d="M14 34.4 Q 24 18.4 34 34.4" opacity="0.55"/>
        <path d="M9 29.4 Q 24 8.4 39 29.4"/>
        <path d="M4 24.4 Q 24 -1.6 44 24.4" opacity="0.3"/>
    </svg>

    <div class="mx-auto max-w-5xl px-5 py-20 sm:py-28">
        <p class="font-medium tracking-[0.18em] text-neutral-400 uppercase text-[0.7rem]">
            Gastos de coche compartidos
        </p>

        <h1 class="mt-5 max-w-3xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl">
            «Poned 15 euros cada uno.» Y nadie sabe si sobró o faltó.
        </h1>

        <p class="mt-6 max-w-xl text-lg leading-relaxed text-neutral-300">
            Libro de Trayectos calcula lo que costó <em>de verdad</em> el viaje —los kilómetros
            que hay por carretera, las cuestas que subisteis y lo que valía el combustible
            ese día— y lleva la cuenta de quién le debe a quién. Tú apuntas de dónde a
            dónde y quién iba; el resto lo pone la aplicación.
        </p>

        <p class="mt-4 max-w-xl leading-relaxed text-neutral-400">
            Además te dice dónde repostar más barato, avisa al móvil de quien iba contigo, y
            se instala en la pantalla de inicio como una aplicación más.
        </p>

        <div class="mt-9 flex flex-wrap items-center gap-3">
            <a href="{{ route('register') }}"
               class="btn inline-flex bg-white px-5 py-3 text-neutral-900 hover:bg-neutral-200">
                Crear una cuenta
            </a>
            <a href="{{ route('login') }}"
               class="btn inline-flex border border-neutral-700 px-5 py-3 text-neutral-100 hover:bg-neutral-900">
                Ya tengo cuenta
            </a>
        </div>

        <dl class="mt-16 grid max-w-3xl grid-cols-2 gap-x-8 gap-y-8 border-t border-neutral-800 pt-9 sm:grid-cols-4">
            <div>
                <dt class="text-2xl font-semibold tabular-nums sm:text-3xl">+50 %</dt>
                <dd class="mt-1 text-sm text-neutral-400">de gasto de más tiene subir un puerto, y a ojo eso no lo cuenta nadie</dd>
            </div>
            <div>
                <dt class="text-2xl font-semibold tabular-nums sm:text-3xl">8,33 €</dt>
                <dd class="mt-1 text-sm text-neutral-400">el reparto llega al céntimo: de 25 € entre tres, el que sobra va rotando</dd>
            </div>
            <div>
                <dt class="text-2xl font-semibold tabular-nums sm:text-3xl">5 fuentes</dt>
                <dd class="mt-1 text-sm text-neutral-400">oficiales o abiertas para los precios y las distancias, ninguna inventada</dd>
            </div>
            <div>
                <dt class="text-2xl font-semibold tabular-nums sm:text-3xl">0 €/mes</dt>
                <dd class="mt-1 text-sm text-neutral-400">no hay suscripción, ni anuncios, ni letra pequeña</dd>
            </div>
        </dl>
    </div>
</section>

{{-- ─── El problema ──────────────────────────────────────────────────────── --}}
<section class="mx-auto max-w-5xl px-5 py-20">
    <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-3xl">
        Por qué el reparto a ojo casi nunca sale
    </h2>

    <div class="mt-10 grid gap-8 md:grid-cols-2">
        <div class="card p-6 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <p class="badge bg-neutral-100 text-neutral-600">Lo que gasta el coche</p>
            <p class="mt-4 leading-relaxed text-neutral-700">
                El consumo que pone el fabricante es en llano. Subir una cuesta gasta de más, y
                bajarla no lo devuelve entero: un diésel se lo deja en los frenos y un híbrido
                recupera sólo un poco en la batería. Por eso repartir un viaje de montaña a
                tanto el kilómetro <strong class="text-neutral-900">se queda corto hasta en un
                50 %</strong>.
            </p>
        </div>

        <div class="card p-6 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <p class="badge bg-neutral-100 text-neutral-600">Lo que apunta el grupo</p>
            <p class="mt-4 leading-relaxed text-neutral-700">
                Las notas del móvil y las hojas de cálculo acaban descuadradas: alguien apunta dos
                veces, alguien borra una línea, alguien rehace el total. Y
                <strong class="text-neutral-900">en cuanto el número falla una vez ya nadie se
                fía</strong> — que es justo cuando vuelven las discusiones que se querían
                evitar.
            </p>
        </div>
    </div>
</section>

{{-- ─── Cómo funciona ────────────────────────────────────────────────────── --}}
<section id="como-funciona" class="border-y border-neutral-200 bg-white">
    <div class="mx-auto max-w-5xl px-5 py-20">
        <h2 class="text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">Cómo funciona</h2>
        <p class="mt-3 max-w-xl text-neutral-600">Cuatro pasos, y solo el primero lo haces tú.</p>

        <ol class="mt-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <li>
                <p class="font-mono text-sm text-neutral-400">01</p>
                <h3 class="mt-2 font-semibold text-neutral-900">Apuntas el viaje</h3>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    Origen, destino y quién iba. El buscador pone primero lo que te pilla
                    cerca, y los viajes que repetís salen ya escritos: elegir y confirmar.
                </p>
            </li>
            <li>
                <p class="font-mono text-sm text-neutral-400">02</p>
                <h3 class="mt-2 font-semibold text-neutral-900">Se calcula el coste real</h3>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    Distancia y desnivel reales por carretera, tu coche con su tecnología y
                    su peso, y el precio de la gasolinera más barata de la zona.
                </p>
            </li>
            <li>
                <p class="font-mono text-sm text-neutral-400">03</p>
                <h3 class="mt-2 font-semibold text-neutral-900">Queda apuntado en el libro</h3>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    Quien conduce pone el dinero y recupera la parte de los demás, y a cada
                    uno le llega un aviso al móvil con lo que le toca.
                </p>
            </li>
            <li>
                <p class="font-mono text-sm text-neutral-400">04</p>
                <h3 class="mt-2 font-semibold text-neutral-900">Liquidáis cuando queráis</h3>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    La aplicación dice quién le paga a quién para dejarlo todo a cero, con
                    las menos transferencias posibles.
                </p>
            </li>
        </ol>
    </div>
</section>

{{-- ─── Lo demás ─────────────────────────────────────────────────────────── --}}
<section class="mx-auto max-w-5xl px-5 py-20">
    <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-3xl">
        Y lo que pasa entre viaje y viaje
    </h2>
    <p class="mt-3 max-w-2xl text-neutral-600">
        Repartir el gasto es la mitad. La otra mitad es todo lo que hay alrededor.
    </p>

    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Dónde repostar más barato</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Un mapa con las gasolineras de tu zona, cada una con su logo y su precio, y el
                histórico de cómo se viene moviendo. Precios oficiales del Ministerio, cuatro
                veces al día.
            </p>
        </div>

        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">A quién le toca conducir</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                La aplicación lo propone mirando quién debe más, quién tiene coche con plazas
                suficientes y a quién le tocó las últimas veces. Se acabó el «hoy llevo yo otra
                vez».
            </p>
        </div>

        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Quien va medio viaje paga medio</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Si a alguien lo recogéis por el camino, el trozo que hicisteis sin él no es
                suyo. El coste se corta por tramos y cada uno paga los que iba dentro.
            </p>
        </div>

        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Lo que gasta tu coche de verdad</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Apunta el cuentakilómetros al llenar y de un lleno al siguiente sale tu consumo
                real. Sin fiarse de la ficha del fabricante: son tus litros y tus kilómetros.
            </p>
        </div>

        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Avisos, pero sólo de dinero</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Cuando alguien apunta un viaje en el que ibas, lo anula, o te paga lo que te
                debía — con el importe en el propio aviso. De nada más.
            </p>
        </div>

        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Se instala y funciona sin cobertura</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Se añade a la pantalla de inicio como cualquier aplicación, sin pasar por
                ninguna tienda. Y si te quedas sin datos en mitad de la sierra, sigue abriendo.
            </p>
        </div>
    </div>
</section>

{{-- ─── El coste real ────────────────────────────────────────────────────── --}}
<section id="coste" class="mx-auto max-w-5xl px-5 py-20">
    <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-3xl">
        Un puerto de montaña no se paga a tanto el kilómetro
    </h2>
    <p class="mt-3 max-w-2xl text-neutral-600">
        El cálculo cuenta el peso que va de verdad dentro, lo que cuesta subir cada metro y
        cuánto de la bajada recupera ese coche en concreto. El mismo viaje sale distinto en
        un gasolina, en un híbrido y en un eléctrico.
    </p>

    <div class="mt-10 grid gap-8 lg:grid-cols-5">
        {{-- min-w-0 es imprescindible: sin él la columna del grid se estira para
             caber la tabla con min-w, el overflow-x-auto no sirve de nada y la
             página entera acaba desplazándose en horizontal. --}}
        <div class="min-w-0 lg:col-span-3">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[26rem] text-sm">
                    <caption class="mb-3 text-left text-sm text-neutral-500">
                        Madrid → Puerto de Navacerrada · 60 km · 900 m de ascenso · cuatro ocupantes
                    </caption>
                    <thead>
                        <tr class="border-b border-neutral-900 text-left text-xs tracking-wider text-neutral-500 uppercase">
                            <th class="py-2 pr-3 font-medium">Coche</th>
                            <th class="py-2 pr-3 font-medium">En llano</th>
                            <th class="py-2 pr-3 font-medium">Por el desnivel</th>
                            <th class="py-2 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-neutral-700">
                        <tr class="border-b border-neutral-200">
                            <td class="py-3 pr-3">Gasolina · 6,5 L/100</td>
                            <td class="py-3 pr-3 tabular-nums">3,90 L</td>
                            <td class="py-3 pr-3 tabular-nums">+1,97 L</td>
                            <td class="py-3 font-semibold text-neutral-900 tabular-nums">5,87 L</td>
                        </tr>
                        <tr class="border-b border-neutral-200">
                            <td class="py-3 pr-3">Híbrido · 4,5 L/100</td>
                            <td class="py-3 pr-3 tabular-nums">2,70 L</td>
                            <td class="py-3 pr-3 tabular-nums">+1,33 L</td>
                            <td class="py-3 font-semibold text-neutral-900 tabular-nums">4,03 L</td>
                        </tr>
                        <tr>
                            <td class="py-3 pr-3">Eléctrico · 17 kWh/100</td>
                            <td class="py-3 pr-3 tabular-nums">10,20 kWh</td>
                            <td class="py-3 pr-3 tabular-nums">+4,90 kWh</td>
                            <td class="py-3 font-semibold text-neutral-900 tabular-nums">15,10 kWh</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-6 max-w-xl text-sm leading-relaxed text-neutral-600">
                Y va afinando solo. Si apuntas el cuentakilómetros al llenar, de un llenado al
                siguiente sale <strong class="text-neutral-900">lo que gasta tu coche de
                verdad</strong> —sin fiarse de la ficha del fabricante ni de ningún modelo— y
                el cálculo se corrige con ese número. Cuanto más lo usáis, más se acerca.
            </p>
        </div>

        <aside class="lg:col-span-2">
            <div class="rounded-2xl border border-neutral-900 bg-neutral-900 p-6 text-neutral-100">
                <p class="text-xs font-medium tracking-[0.14em] text-neutral-400 uppercase">
                    El detalle que casi nadie tiene en cuenta
                </p>
                <p class="mt-4 text-3xl font-semibold tabular-nums">226 metros</p>
                <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                    Un híbrido que baja un puerto de 1.200 m no recupera los 1.200: llena la
                    batería en los primeros cientos de metros y el resto lo tira por los frenos,
                    igual que un diésel. En un híbrido normal ese tope está sobre los 226 m, y
                    el cálculo lo tiene en cuenta.
                </p>
            </div>
        </aside>
    </div>
</section>

{{-- ─── Las cuentas ──────────────────────────────────────────────────────── --}}
<section id="cuentas" class="border-y border-neutral-200 bg-white">
    <div class="mx-auto max-w-5xl px-5 py-20">
        <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-3xl">
            Las cuentas no se pueden descuadrar
        </h2>

        <div class="mt-10 grid gap-10 lg:grid-cols-2">
            <div>
                <p class="leading-relaxed text-neutral-700">
                    Cada viaje se apunta por partida doble, que es como llevan las cuentas los
                    contables: lo que uno pone y lo que los demás le deben se escriben a la vez, y
                    <strong class="text-neutral-900">las líneas suman exactamente cero</strong>. Un
                    viaje de 24 € que conduce Ana, con Bea, Carlos y Diego dentro:
                </p>

                <div class="card mt-6 p-0">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr class="border-b border-neutral-100">
                                <td class="px-4 py-3 font-medium text-neutral-900">Ana</td>
                                <td class="px-4 py-3 text-right money text-credit-700 tabular-nums">+18,00 €</td>
                                <td class="px-4 py-3 text-xs text-neutral-500">pone el dinero y recupera las tres partes ajenas</td>
                            </tr>
                            <tr class="border-b border-neutral-100">
                                <td class="px-4 py-3 text-neutral-700">Bea</td>
                                <td class="px-4 py-3 text-right money text-debt-700 tabular-nums">−6,00 €</td>
                                <td class="px-4 py-3 text-xs text-neutral-500">su parte</td>
                            </tr>
                            <tr class="border-b border-neutral-100">
                                <td class="px-4 py-3 text-neutral-700">Carlos</td>
                                <td class="px-4 py-3 text-right money text-debt-700 tabular-nums">−6,00 €</td>
                                <td class="px-4 py-3 text-xs text-neutral-500">su parte</td>
                            </tr>
                            <tr class="border-b border-neutral-200">
                                <td class="px-4 py-3 text-neutral-700">Diego</td>
                                <td class="px-4 py-3 text-right money text-debt-700 tabular-nums">−6,00 €</td>
                                <td class="px-4 py-3 text-xs text-neutral-500">su parte</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-semibold text-neutral-900">Suma</td>
                                <td class="px-4 py-3 text-right money text-neutral-900 tabular-nums">0,00 €</td>
                                <td class="px-4 py-3 text-xs text-neutral-500">o el viaje no se apunta</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <h3 class="font-semibold text-neutral-900">El saldo no se guarda en ningún sitio</h3>
                    <p class="mt-2 leading-relaxed text-neutral-700">
                        Guardar el saldo de cada uno en una casilla es justo lo que acaba fallando:
                        se actualiza mal una vez y ya está mintiendo. Aquí el saldo se suma del
                        libro cada vez que lo miras. No puede descuadrarse porque no está guardado.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold text-neutral-900">Nada se borra nunca</h3>
                    <p class="mt-2 leading-relaxed text-neutral-700">
                        Anular un viaje no lo borra: escribe el apunte contrario y los dos quedan
                        a la vista. Siempre se puede ver qué pasó, y los saldos vuelven solos a
                        su sitio.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold text-neutral-900">El céntimo que sobra va rotando</h3>
                    <p class="mt-2 leading-relaxed text-neutral-700">
                        25 € entre tres son 8,33 + 8,33 + 8,34. Ese céntimo de más no le toca
                        siempre al mismo: va cambiando de persona viaje a viaje. Y el dinero se
                        guarda en céntimos enteros, nunca en decimales que redondeen por su cuenta.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── Los datos ────────────────────────────────────────────────────────── --}}
<section id="datos" class="mx-auto max-w-5xl px-5 py-20">
    <h2 class="text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">De dónde salen los datos</h2>
    <p class="mt-3 max-w-2xl text-neutral-600">
        Ni un precio inventado ni un kilómetro a ojo: cinco fuentes oficiales o abiertas,
        que la aplicación consulta sola.
    </p>

    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Ministerio para la Transición Ecológica</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                El precio oficial de cada gasolinera, actualizado varias veces al día.
            </p>
        </div>
        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Red Eléctrica de España</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                El PVPC del día para los coches eléctricos, o tu tarifa real si la declaras.
            </p>
        </div>
        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">OpenRouteService</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Distancia y desnivel reales por carretera, no en línea recta.
            </p>
        </div>
        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Open Topo Data</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Altitudes del terreno a 25 m de resolución, como respaldo.
            </p>
        </div>
        <div class="card-tight p-5 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <h3 class="font-semibold text-neutral-900">Photon</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Autocompletado de direcciones sobre datos de OpenStreetMap.
            </p>
        </div>
        <div class="card-tight border-dashed border-neutral-300 bg-transparent p-5">
            <h3 class="font-semibold text-neutral-900">Y si alguna falla</h3>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                La aplicación tira de la siguiente y te lo dice en pantalla. Que falle una fuente
                nunca te impide apuntar un viaje.
            </p>
        </div>
    </div>
</section>

{{-- ─── Cierre ───────────────────────────────────────────────────────────── --}}
<section class="bg-neutral-950 text-neutral-50">
    <div class="mx-auto max-w-5xl px-5 py-20">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                    Se instala en el móvil como una aplicación más
                </h2>
                <p class="mt-4 leading-relaxed text-neutral-300">
                    Incluido en iPhone, sin pasar por la App Store: se abre desde la pantalla
                    de inicio, a pantalla completa. Y funciona sobre infraestructura gratuita
                    de principio a fin, así que no hay suscripción que pagar ni que cancelar.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register') }}"
                       class="btn inline-flex bg-white px-5 py-3 text-neutral-900 hover:bg-neutral-200">
                        Crear una cuenta
                    </a>
                    <a href="https://github.com/ide-la-r/Trayectos"
                       class="btn inline-flex border border-neutral-700 px-5 py-3 text-neutral-100 hover:bg-neutral-900">
                        Ver el código
                    </a>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-8 gap-y-7 border-t border-neutral-800 pt-9 lg:border-t-0 lg:pt-0 lg:pl-12">
                <div>
                    <dt class="text-sm text-neutral-400">Grupos</dt>
                    <dd class="mt-1 text-neutral-100">Uno por cuadrilla, con código o enlace de invitación</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-400">Coches</dt>
                    <dd class="mt-1 text-neutral-100">Gasolina, diésel, híbrido, enchufable y eléctrico</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-400">Repostajes</dt>
                    <dd class="mt-1 text-neutral-100">Cada llenado afina el consumo de tu coche</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-400">Liquidaciones</dt>
                    <dd class="mt-1 text-neutral-100">Quién paga a quién, en las menos transferencias</dd>
                </div>
            </dl>
        </div>
    </div>
</section>

</x-layouts.marketing>
