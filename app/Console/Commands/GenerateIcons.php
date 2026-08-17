<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Genera los iconos de la PWA con GD, sin depender de ninguna herramienta de
 * diseño ni de servicios externos. El dibujo es una carretera en perspectiva
 * con su línea discontinua: se reconoce a 40 px, que es el tamaño real al que
 * se ve en la pantalla de inicio de un móvil.
 */
class GenerateIcons extends Command
{
    protected $signature = 'trayectos:icons';

    protected $description = 'Genera los iconos PNG de la PWA en public/icons';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Hace falta la extensión GD de PHP.');

            return self::FAILURE;
        }

        $directory = public_path('icons');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $targets = [
            'icon-192.png' => [192, 0.0],
            'icon-512.png' => [512, 0.0],
            // Los iconos maskable se recortan: hay que dejar margen de seguridad
            'maskable-512.png' => [512, 0.18],
            'apple-touch-icon.png' => [180, 0.0],
        ];

        foreach ($targets as $filename => [$size, $padding]) {
            $image = $this->draw($size, $padding);
            imagepng($image, "{$directory}/{$filename}", 9);
            imagedestroy($image);

            $this->line("· icons/{$filename} ({$size}×{$size})");
        }

        $this->info('Iconos generados.');

        return self::SUCCESS;
    }

    private function draw(int $size, float $padding): \GdImage
    {
        $image = imagecreatetruecolor($size, $size);
        imageantialias($image, true);

        $background = imagecolorallocate($image, 23, 23, 23);      // neutral-900
        $asphalt = imagecolorallocate($image, 64, 64, 64);         // neutral-700
        $line = imagecolorallocate($image, 250, 250, 250);         // neutral-50
        $horizon = imagecolorallocate($image, 115, 115, 115);      // neutral-500

        imagefilledrectangle($image, 0, 0, $size, $size, $background);

        $inset = (int) round($size * $padding);
        $drawable = $size - 2 * $inset;
        $unit = fn (float $ratio) => (int) round($inset + $ratio * $drawable);

        // Carretera: trapecio ancho abajo, estrecho en el horizonte
        imagefilledpolygon($image, [
            $unit(0.12), $unit(0.94),
            $unit(0.88), $unit(0.94),
            $unit(0.60), $unit(0.30),
            $unit(0.40), $unit(0.30),
        ], $asphalt);

        // Línea discontinua central, en perspectiva: los tramos se acortan
        $segments = [
            [0.88, 0.98, 0.030],
            [0.70, 0.78, 0.022],
            [0.56, 0.62, 0.016],
            [0.45, 0.49, 0.011],
            [0.36, 0.39, 0.008],
        ];

        foreach ($segments as [$bottom, $top, $halfWidth]) {
            imagefilledpolygon($image, [
                $unit(0.5 - $halfWidth), $unit($bottom),
                $unit(0.5 + $halfWidth), $unit($bottom),
                $unit(0.5 + $halfWidth * 0.75), $unit($top),
                $unit(0.5 - $halfWidth * 0.75), $unit($top),
            ], $line);
        }

        // Sol / luna sobre el horizonte, para que no sea sólo un triángulo grís
        $radius = (int) round($drawable * 0.13);
        imagefilledellipse($image, $unit(0.5), $unit(0.20), $radius, $radius, $horizon);

        return $image;
    }
}
