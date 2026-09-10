<?php

declare(strict_types=1);

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Command;

/**
 * Genera los iconos de la PWA con GD, sin depender de ninguna herramienta de
 * diseño ni de servicios externos.
 *
 * La marca son dos curvas de nivel —un puerto visto en el mapa, no de frente—
 * con la cima marcada en naranja. Se eligió por encima de dibujar una
 * carretera porque una ilustración pequeña acaba pareciendo un garabato: una
 * forma geométrica sencilla aguanta mucho mejor a 24 px, que es el tamaño real
 * al que se ve en la barra de abajo.
 *
 * Dos cosas que hay que saber si se toca esto:
 *
 *  1. Se dibuja a 4x y se reduce al final. GD no suaviza los bordes de una
 *     forma rellena; reducir una imagen grande sí. Es lo que hace que las
 *     curvas salgan limpias sin ImageMagick.
 *
 *  2. La curva tenue NO se pinta con un color semitransparente. Las curvas se
 *     trazan estampando círculos solapados, y con transparencia la opacidad se
 *     acumula en cada solape hasta quedar casi opaca. Se pinta entera en una
 *     capa aparte y se funde al 45 %.
 */
class GenerateIcons extends Command
{
    protected $signature = 'trayectos:icons';

    protected $description = 'Genera los iconos PNG de la PWA en public/icons';

    /** Se dibuja a este múltiplo y se reduce: es lo que suaviza los bordes. */
    private const SCALE = 4;

    /** El lienzo de la marca, el mismo que el viewBox del SVG del componente. */
    private const CANVAS = 48.0;

    private const INK = [0xFA, 0xFA, 0xFA];      // neutral-50

    private const BACKGROUND = [0x17, 0x17, 0x17];  // neutral-900

    private const SUMMIT = [0xEE, 0x72, 0x03];   // el naranja de la marca

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
            'icon-192.png' => [192, 1.0],
            'icon-512.png' => [512, 1.0],
            // Al maskable el sistema le recorta las esquinas y puede comerse
            // hasta un 20 % de cada lado: el dibujo va encogido.
            'maskable-512.png' => [512, 0.68],
            'apple-touch-icon.png' => [180, 1.0],
        ];

        foreach ($targets as $filename => [$size, $inset]) {
            $image = $this->draw($size, $inset);
            imagepng($image, "{$directory}/{$filename}", 9);
            imagedestroy($image);

            $this->line("· icons/{$filename} ({$size}×{$size})");
        }

        $this->info('Iconos generados.');

        return self::SUCCESS;
    }

    private function draw(int $size, float $inset): GdImage
    {
        $large = $size * self::SCALE;
        $image = imagecreatetruecolor($large, $large);

        $background = imagecolorallocate($image, ...self::BACKGROUND);
        imagefilledrectangle($image, 0, 0, $large, $large, $background);

        // Del sistema de coordenadas de la marca al lienzo, ya encogido
        $unit = ($large / self::CANVAS) * $inset;
        $side = (int) round(self::CANVAS * $unit);
        $margin = (int) round(($large - $side) / 2);

        $mark = imagecreatetruecolor($side, $side);
        imagefilledrectangle($mark, 0, 0, $side, $side, imagecolorallocate($mark, ...self::BACKGROUND));

        /*
         * Las curvas van subidas 3,6 respecto al SVG: el dibujo ocupa de 15,6
         * a 39,7 y su centro caía en 27,6 en vez de en 24. Se notaba sobre todo
         * en el maskable, que va encogido.
         */
        $faint = imagecreatetruecolor($side, $side);
        imagefilledrectangle($faint, 0, 0, $side, $side, imagecolorallocate($faint, ...self::BACKGROUND));
        $this->curve($faint, [14, 34.4], [24, 18.4], [34, 34.4], 3.4, imagecolorallocate($faint, ...self::INK), $unit);
        imagecopymerge($mark, $faint, 0, 0, 0, 0, $side, $side, 45);
        imagedestroy($faint);

        $this->curve($mark, [9, 29.4], [24, 8.4], [39, 29.4], 3.4, imagecolorallocate($mark, ...self::INK), $unit);

        // La cima, justo encima de la curva sin llegar a montarse
        $radius = (int) round(3.0 * $unit);
        imagefilledellipse(
            $mark,
            (int) round(24 * $unit),
            (int) round(15.0 * $unit),
            $radius * 2,
            $radius * 2,
            imagecolorallocate($mark, ...self::SUMMIT),
        );

        imagecopy($image, $mark, $margin, $margin, 0, 0, $side, $side);
        imagedestroy($mark);

        $final = imagecreatetruecolor($size, $size);
        imagecopyresampled($final, $image, 0, 0, 0, 0, $size, $size, $large, $large);
        imagedestroy($image);

        return $final;
    }

    /**
     * Traza una bézier cuadrática estampando círculos rellenos.
     *
     * GD no sabe dibujar curvas, así que se muestrea la bézier y se pone un
     * círculo en cada punto. De paso salen gratis las puntas redondeadas, que
     * es justo el remate que lleva la marca.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $control
     * @param  array{0: float, 1: float}  $to
     */
    private function curve(GdImage $image, array $from, array $control, array $to, float $width, int $color, float $unit): void
    {
        $diameter = (int) round($width * $unit);
        $steps = 900;

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $u = 1 - $t;

            $x = $u * $u * $from[0] + 2 * $u * $t * $control[0] + $t * $t * $to[0];
            $y = $u * $u * $from[1] + 2 * $u * $t * $control[1] + $t * $t * $to[1];

            imagefilledellipse($image, (int) round($x * $unit), (int) round($y * $unit), $diameter, $diameter, $color);
        }
    }
}
