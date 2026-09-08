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
        // Ids de provincia del Ministerio. Lista vacía => descarga nacional completa,
        // que son ~11.500 estaciones: en el plan gratuito de Neon (500 MB) eso
        // llena la base de datos en semanas. Por defecto 29, Málaga.
        'provinces' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MITECO_PROVINCES', '29'))
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
        'min_refuels' => 3,
        'min_factor' => 0.750,
        'max_factor' => 1.350,
        'lookback_days' => 180,
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
];
