<x-layouts.marketing>

{{-- ─── Portada ──────────────────────────────────────────────────────────── --}}
<section class="relative isolate overflow-hidden bg-neutral-950 text-neutral-50">
    {{-- La carretera de la marca, a tamaño grande y muy tenue: el mismo dibujo
         del icono sirve de fondo sin necesidad de ninguna imagen. --}}
    <svg class="pointer-events-none absolute -bottom-24 left-1/2 -z-10 w-[46rem] max-w-none -translate-x-1/2 road-ambient"
         viewBox="0 0 48 48" fill="currentColor" aria-hidden="true">
        <path d="M6 45 L42 45 L28.6 14.6 L19.4 14.6 Z"/>
        <circle cx="24" cy="9.2" r="5.4"/>
    </svg>

    <div class="mx-auto max-w-5xl px-5 py-20 sm:py-28">
        <p class="font-medium tracking-[0.18em] text-neutral-400 uppercase text-[0.7rem]">
            Gastos de coche compartidos
        </p>

        <h1 class="mt-5 max-w-3xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl">
            «Pon quince euros cada uno» no es repartir el gasto.
        </h1>

        <p class="mt-6 max-w-xl text-lg leading-relaxed text-neutral-300">
            Libro de Trayectos calcula lo que cuesta <em>de verdad</em> cada viaje
            —incluido el desnivel, que no pesa igual en un híbrido que en un diésel— y
            lo reparte con contabilidad por partida doble. Sin discusiones y sin cuentas
            que se descuadran.
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
                <dd class="mt-1 text-sm text-neutral-400">de gasto extra en un puerto de montaña frente al llano</dd>
            </div>
            <div>
                <dt class="text-2xl font-semibold tabular-nums sm:text-3xl">226 m</dt>
                <dd class="mt-1 text-sm text-neutral-400">de bajada es todo lo que un híbrido llega a recuperar</dd>
            </div>
            <div>
                <dt class="text-2xl font-semibold tabular-nums sm:text-3xl">0,00 €</dt>
                <dd class="mt-1 text-sm text-neutral-400">es lo que suman las líneas de cada asiento del libro</dd>
            </div>
            <div>
                <dt class="text-2xl font-semibold tabular-nums sm:text-3xl">0 €/mes</dt>
                <dd class="mt-1 text-sm text-neutral-400">cuesta mantenerla funcionando</dd>
            </div>
        </dl>
    </div>
</section>

{{-- ─── El problema ──────────────────────────────────────────────────────── --}}
<section class="mx-auto max-w-5xl px-5 py-20">
    <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-3xl">
        Repartir la gasolina a ojo falla por dos motivos, y no tienen nada que ver entre sí
    </h2>

    <div class="mt-10 grid gap-8 md:grid-cols-2">
        <div class="card p-6 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <p class="badge bg-neutral-100 text-neutral-600">El problema físico</p>
            <p class="mt-4 leading-relaxed text-neutral-700">
                El consumo que anuncia un fabricante corresponde a terreno esencialmente
                llano. Subir un puerto exige una energía que el motor tiene que entregar, y
                de la bajada solo se recupera una parte: un diésel la disipa en los frenos,
                un híbrido la devuelve a la batería. Usar los kilómetros a secas para un
                viaje de montaña <strong class="text-neutral-900">se equivoca en un 50 %</strong>.
            </p>
        </div>

        <div class="card p-6 hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-md">
            <p class="badge bg-neutral-100 text-neutral-600">El problema contable</p>
            <p class="mt-4 leading-relaxed text-neutral-700">
                Las cuentas de un grupo en notas del móvil o en una hoja de cálculo acaban
                descuadradas: un apunte duplicado, uno borrado, un recálculo. Y
                <strong class="text-neutral-900">en cuanto el número falla una vez, nadie se
                vuelve a fiar de él</strong> — que es cuando vuelven las discusiones que se
                pretendía evitar.
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
                    Origen, destino y quién iba. El buscador de direcciones autocompleta
                    mientras escribes.
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
                <h3 class="mt-2 font-semibold text-neutral-900">Se asienta en el libro</h3>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    Quien conduce adelanta el dinero y recupera las partes ajenas. El asiento
                    cuadra a cero o no se escribe.
                </p>
            </li>
            <li>
                <p class="font-mono text-sm text-neutral-400">04</p>
                <h3 class="mt-2 font-semibold text-neutral-900">Liquidáis cuando queráis</h3>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    La aplicación dice quién paga a quién para dejar todos los saldos a cero
                    con el menor número de transferencias.
                </p>
            </li>
        </ol>
    </div>
</section>

{{-- ─── El coste real ────────────────────────────────────────────────────── --}}
<section id="coste" class="mx-auto max-w-5xl px-5 py-20">
    <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-3xl">
        El coste real: física, no una regla de tres
    </h2>
    <p class="mt-3 max-w-2xl text-neutral-600">
        Cada trayecto se calcula con la masa que va de verdad en el coche, la energía que
        exige el desnivel y la parte de la bajada que esa tecnología es capaz de recuperar.
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
                Y el modelo aprende: compara vuestros repostajes reales con lo que había
                predicho y ajusta un factor propio para cada coche. Las estimaciones se
                afinan con el uso en lugar de quedarse en la teoría.
            </p>
        </div>

        <aside class="lg:col-span-2">
            <div class="rounded-2xl border border-neutral-900 bg-neutral-900 p-6 text-neutral-100">
                <p class="text-xs font-medium tracking-[0.14em] text-neutral-400 uppercase">
                    El detalle que casi nadie modela
                </p>
                <p class="mt-4 text-3xl font-semibold tabular-nums">226 metros</p>
                <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                    Un híbrido que baja un puerto de 1.200 m no recupera 1.200 m de energía:
                    llena su batería en los primeros cientos de metros y el resto lo tira por
                    los frenos igual que un diésel. Para un híbrido típico ese techo está en
                    unos 226 m de desnivel, y el cálculo lo tiene en cuenta.
                </p>
            </div>
        </aside>
    </div>
</section>

{{-- ─── Las cuentas ──────────────────────────────────────────────────────── --}}
<section id="cuentas" class="border-y border-neutral-200 bg-white">
    <div class="mx-auto max-w-5xl px-5 py-20">
        <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-3xl">
            Contabilidad de verdad, no una lista de quién debe qué
        </h2>

        <div class="mt-10 grid gap-10 lg:grid-cols-2">
            <div>
                <p class="leading-relaxed text-neutral-700">
                    Cada viaje genera un asiento cuyas líneas
                    <strong class="text-neutral-900">suman exactamente cero</strong>. Un trayecto de
                    24 € que conduce Ana, con Bea, Carlos y Diego dentro:
                </p>

                <div class="card mt-6 p-0">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr class="border-b border-neutral-100">
                                <td class="px-4 py-3 font-medium text-neutral-900">Ana</td>
                                <td class="px-4 py-3 text-right money text-credit-700 tabular-nums">+18,00 €</td>
                                <td class="px-4 py-3 text-xs text-neutral-500">adelanta y recupera las tres partes ajenas</td>
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
                                <td class="px-4 py-3 text-xs text-neutral-500">o el asiento no se escribe</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <h3 class="font-semibold text-neutral-900">No existe ninguna columna «saldo»</h3>
                    <p class="mt-2 leading-relaxed text-neutral-700">
                        Un campo con el saldo de cada persona acaba desajustado siempre. Aquí el
                        saldo no se guarda: es la suma de las líneas del libro, calculada al
                        leerla. No puede desajustarse porque no existe.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold text-neutral-900">Nada se borra nunca</h3>
                    <p class="mt-2 leading-relaxed text-neutral-700">
                        Anular un viaje no lo elimina: crea el asiento contrario y los dos quedan
                        a la vista. El histórico es auditable y los saldos vuelven solos a su
                        sitio.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold text-neutral-900">Hasta el céntimo suelto está pensado</h3>
                    <p class="mt-2 leading-relaxed text-neutral-700">
                        25 € entre tres son 8,33 + 8,33 + 8,34. Ese céntimo se reparte rotando
                        por viaje, para que no le toque siempre al mismo. Todo el dinero se
                        maneja en enteros de céntimos: ni un decimal flotante en ninguna cuenta.
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
        Nada de precios inventados ni de kilómetros a estima: cinco fuentes oficiales o
        abiertas, consultadas de forma automática.
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
                Se degrada por escalones y te avisa en pantalla. Que se agote una cuota nunca
                impide apuntar un viaje.
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
                    <dd class="mt-1 text-neutral-100">Uno por cuadrilla, con código de invitación</dd>
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
