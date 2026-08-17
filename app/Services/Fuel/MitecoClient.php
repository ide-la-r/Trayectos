<?php

declare(strict_types=1);

namespace App\Services\Fuel;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente del Geoportal de Gasolineras (Ministerio para la Transición
 * Ecológica). API pública, sin clave y sin tarjeta.
 *
 * Contrato verificado el 2026-08-17 contra el endpoint real:
 *   - la raíz trae 'Fecha' (d/m/Y H:i:s) y 'ListaEESSPrecio'
 *   - los precios son cadenas con coma decimal, y '' cuando no se vende
 *   - los nombres de campo llevan espacios, tildes y paréntesis
 *     ('Longitud (WGS84)', 'Rótulo', 'C.P.')
 *
 * Nunca se llama desde una petición de usuario: es lenta y se cae. La sincroniza
 * un comando y la aplicación lee siempre de la copia local.
 */
final class MitecoClient
{
    /**
     * @return array{observed_at: Carbon, stations: array<int, array<string, mixed>>}
     */
    public function stationsByProvince(string $provinceId): array
    {
        return $this->fetch("/EstacionesTerrestres/FiltroProvincia/{$provinceId}");
    }

    /** Descarga nacional completa: ~11.500 estaciones y unos 20 MB. */
    public function allStations(): array
    {
        return $this->fetch('/EstacionesTerrestres/');
    }

    /** @return array<int, array{id: string, name: string}> */
    public function provinces(): array
    {
        $response = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get($this->url('/Listados/Provincias/'));

        if ($response->failed()) {
            throw new RuntimeException("El listado de provincias ha respondido {$response->status()}.");
        }

        return collect($response->json())
            ->map(fn (array $row) => [
                'id' => (string) ($row['IDPovincia'] ?? $row['IDProvincia'] ?? ''),
                'name' => (string) ($row['Provincia'] ?? ''),
            ])
            ->filter(fn (array $row) => $row['id'] !== '')
            ->values()
            ->all();
    }

    private function fetch(string $path): array
    {
        $response = Http::timeout((int) config('trayectos.miteco.timeout'))
            ->retry(2, 2000, throw: false)
            ->get($this->url($path));

        if ($response->failed()) {
            throw new RuntimeException("El Ministerio ha respondido {$response->status()} en {$path}.");
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['ListaEESSPrecio'])) {
            throw new RuntimeException("Respuesta inesperada del Ministerio en {$path}.");
        }

        return [
            'observed_at' => $this->parseDate($payload['Fecha'] ?? null),
            'stations' => $payload['ListaEESSPrecio'],
        ];
    }

    private function parseDate(?string $raw): Carbon
    {
        if (blank($raw)) {
            return now();
        }

        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', trim($raw));
        } catch (\Throwable) {
            return now();
        }
    }

    private function url(string $path): string
    {
        return rtrim((string) config('trayectos.miteco.base_url'), '/').$path;
    }
}
