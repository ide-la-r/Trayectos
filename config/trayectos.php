<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Servicios externos
    |--------------------------------------------------------------------------
    | Todos gratuitos y sin tarjeta. Ver docs/apis.md para cuotas y contratos.
    */

    'ors' => [
        'key' => env('ORS_API_KEY'),
        'base_url' => env('ORS_BASE_URL', 'https://api.openrouteservice.org'),
        'profile' => env('ORS_PROFILE', 'driving-car'),
        // Margen de seguridad sobre la cuota real del plan libre (~2.000/día)
        'daily_quota' => (int) env('ORS_DAILY_QUOTA', 1900),
        'timeout' => 20,
    ],

    'opentopodata' => [
        'base_url' => env('OPENTOPODATA_BASE_URL', 'https://api.opentopodata.org'),
        'dataset' => env('OPENTOPODATA_DATASET', 'eudem25m'),
        'max_points_per_request' => 100,
        'daily_quota' => 900,
        'timeout' => 20,
    ],

    'miteco' => [
        'base_url' => env('MITECO_BASE_URL', 'https://sedeaplicaciones.minetur.gob.es/ServiciosRESTCarburantes/PreciosCarburantes'),
        /*
         * Ids de provincia del Ministerio. Por defecto Granada, Málaga, Sevilla
         * y Madrid: los sitios donde de verdad hay alguien repostando.
         *
         * Lista vacía => descarga nacional. Medido contra la API real el
         * 09-09-2026: España son 11.493 estaciones y 28.858 filas de precio
         * por sincronización, o sea 22,6 MB al día y 677 MB en treinta días.
         * El plan gratuito de Neon son 500 MB en total, así que no cabe.
         * Estas cuatro son 1.930 estaciones y 3,8 MB al día: 172 MB con los 45
         * días que se guardan, un tercio del plan. Andalucía entera más Madrid
         * serían 3.016 estaciones y 265 MB, que también cabrían.
         */
        'provinces' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MITECO_PROVINCES', '18,28,29,41'))
        ))),
        'timeout' => 120,
    ],

    'ree' => [
        'base_url' => env('REE_BASE_URL', 'https://apidatos.ree.es'),
        'timeout' => 15,
        // Se usa la serie PVPC (id 1001), que ya es precio minorista regulado,
        // pero se publica sin IVA. Este factor lo añade. Aun así es una
        // aproximación: quien carga en casa con tarifa fija debería declararla.
        'retail_multiplier' => (float) env('REE_RETAIL_MULTIPLIER', 1.21),
        'series_title' => 'PVPC',
    ],

    /*
    |--------------------------------------------------------------------------
    | Precios de respaldo
    |--------------------------------------------------------------------------
    | Se usan cuando no hay dato fresco de la fuente oficial. En milésimas de
    | euro por litro / por kWh para no arrastrar decimales flotantes.
    */

    'fallback_prices' => [
        'kwh_milli' => (int) round(((float) env('DEFAULT_KWH_PRICE', 0.15)) * 1000),
        'fuel_milli' => [
            'G95E5' => 1550,
            'G98E5' => 1720,
            'GOA' => 1480,
            'GLP' => 990,
            'GNC' => 1180,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Física del cálculo de consumo
    |--------------------------------------------------------------------------
    | Constantes del modelo gravitatorio. Cambiar cualquiera de estas obliga a
    | subir 'formula_version': los viajes ya asentados conservan su versión.
    */

    'physics' => [
        'formula_version' => 3,
        'gravity' => 9.81,
        'occupant_weight_kg' => 75,

        // Poder calorífico inferior por unidad de venta (litro, o kg en GLP/GNC), en julios
        'lhv_joules_per_unit' => [
            'G95E5' => 32.0e6,
            'G98E5' => 32.2e6,
            'GOA' => 35.8e6,
            'GLP' => 25.0e6,
            'GNC' => 38.0e6, // por kg equivalente; el GNC se factura por kg
        ],

        // Eficiencia batería -> rueda en el tramo eléctrico
        'ev_drivetrain_efficiency' => 0.85,

        // Eficiencia de la regeneración al convertir energía potencial en carga
        'regen_charge_efficiency' => 0.90,

        // Un vehículo cuesta abajo nunca consume cero: suelo sobre el consumo base
        'consumption_floor_ratio' => 0.40,

        // Valores por defecto por tecnología (editables por vehículo)
        'defaults' => [
            'ICE' => ['regen' => 0.05, 'thermal' => 0.25],
            'HEV' => ['regen' => 0.60, 'thermal' => 0.33],
            'PHEV' => ['regen' => 0.65, 'thermal' => 0.32],
            'BEV' => ['regen' => 0.70, 'thermal' => null],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Calibración con repostajes reales
    |--------------------------------------------------------------------------
    */

    'calibration' => [
        // Dos depositos completos son tres llenados: el que abre y los dos que
        // cierran cada tramo.
        'min_tanks' => 2,
        'min_factor' => 0.750,
        'max_factor' => 1.350,
        'lookback_days' => 180,
        /*
         * Que parte de los kilometros conducidos tiene que estar apuntada como
         * viajes para que el ritmo del modelo signifique algo. No hace falta
         * apuntarlo todo —comparar litros por cada cien kilometros a los dos
         * lados se encarga de eso—, pero con un viaje suelto no hay muestra.
         */
        'min_trip_coverage' => 0.25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumo real, medido de lleno a lleno
    |--------------------------------------------------------------------------
    |
    | Limites para descartar un deposito que no puede ser. Casi siempre es un
    | cuentakilometros mal tecleado: un cero de mas convierte un deposito
    | normal en cuarenta mil kilometros con cincuenta litros, y ese dato solo
    | entra para destrozar la media.
    |
    */

    'real_consumption' => [
        'min_tank_km' => 50,
        'max_tank_km' => 2000,
        'min_litres_per_100' => 1.0,
        'max_litres_per_100' => 40.0,
        'min_kwh_per_100' => 5.0,
        'max_kwh_per_100' => 100.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Motor de sugerencia de conductor
    |--------------------------------------------------------------------------
    */

    'driver_suggestion' => [
        // Peso en "euros equivalentes" de cada turno de diferencia respecto a
        // quien más ha conducido en la ventana reciente.
        'fairness_weight_eur' => 10.0,
        'lookback_days' => 30,
        'suggestions' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tareas internas invocadas por el cron externo (GitHub Actions)
    |--------------------------------------------------------------------------
    */

    'internal_task_token' => env('INTERNAL_TASK_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Avisos en el móvil (Web Push)
    |--------------------------------------------------------------------------
    |
    | Sin claves configuradas la función se apaga sola: no se ofrece el
    | interruptor y no se manda nada. Se generan UNA vez con
    | `php artisan trayectos:vapid` y no se tocan: cambiarlas invalida todas
    | las suscripciones que haya dado la gente y hay que volver a pedirlas una
    | por una.
    |
    | Apple y Google no cobran por esto. El «subject» es a quién avisar desde
    | su lado si algo va mal; tiene que ser un mailto: o una URL.
    |
    */

    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'https://libro-de-trayectos.onrender.com'),
        /*
         * El envío va dentro de la petición de quien apunta el viaje, porque
         * en el plan gratuito de Render no hay trabajador de colas. Con un
         * grupo de cuatro son cuatro peticiones en paralelo; el tope está para
         * que un servidor de avisos atascado no deje colgado a quien está
         * apuntando.
         */
        'timeout' => 8,
        // Cuánto lo guarda Apple o Google si el móvil está apagado. Un día:
        // pasado eso, el viaje ya se ha visto al abrir la aplicación.
        'ttl' => 86400,
    ],
];
