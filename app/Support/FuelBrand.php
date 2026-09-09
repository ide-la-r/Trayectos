<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * La marca de una gasolinera, deducida de su rótulo.
 *
 * El Ministerio no publica un campo de marca: sólo el rótulo, y viene como lo
 * declara cada estación («REPSOL», «E.S. GALP ENERGIA», «CARREFOUR»...). Así
 * que se busca la marca dentro del texto.
 *
 * Cada una lleva sus iniciales y un color aproximado al corporativo. NO son
 * los logotipos: son marcas propias hechas para distinguirlas de un vistazo,
 * porque meter los logotipos de las petroleras en el repositorio sería
 * redistribuir material del que no se puede acreditar la licencia. El sitio
 * donde enchufarlos está preparado, por si algún día se tienen los ficheros.
 */
final class FuelBrand
{
    /**
     * Patrón => [iniciales, fondo, tinta].
     *
     * El orden importa: se devuelve la primera que casa, así que las marcas
     * cuyo nombre contiene otra van antes. Y las siglas cortas («BP», «Q8»)
     * llevan límite de palabra: sin él, «BP» casaría dentro de cualquier
     * palabra que la contenga.
     */
    private const BRANDS = [
        'MOEVE' => ['Moeve', 'MV', '#0033A0', '#FFFFFF'],
        'CEPSA' => ['Cepsa', 'CE', '#0033A0', '#FFFFFF'],
        'REPSOL' => ['Repsol', 'RE', '#EE7203', '#FFFFFF'],
        'CAMPSA' => ['Campsa', 'CA', '#005AA9', '#FFFFFF'],
        'PETRONOR' => ['Petronor', 'PN', '#E4002B', '#FFFFFF'],
        'GALP' => ['Galp', 'GA', '#FF7900', '#FFFFFF'],
        'SHELL' => ['Shell', 'SH', '#FBCE07', '#3D2B00'],
        'PETROPRIX' => ['Petroprix', 'PX', '#0B2C5A', '#FFFFFF'],
        'BALLENOIL' => ['Ballenoil', 'BA', '#0069B4', '#FFFFFF'],
        'PLENOIL' => ['Plenoil', 'PL', '#00A19A', '#FFFFFF'],
        'PETROMAX' => ['Petromax', 'PM', '#C8102E', '#FFFFFF'],
        'MEROIL' => ['Meroil', 'ME', '#003C71', '#FFFFFF'],
        'CARREFOUR' => ['Carrefour', 'CF', '#004E9F', '#FFFFFF'],
        'ALCAMPO' => ['Alcampo', 'AL', '#E30613', '#FFFFFF'],
        'EROSKI' => ['Eroski', 'ER', '#E4032E', '#FFFFFF'],
        'BONAREA' => ['BonÀrea', 'BO', '#00843D', '#FFFFFF'],
        'DISA' => ['Disa', 'DI', '#005CA9', '#FFFFFF'],
        'AVIA' => ['Avia', 'AV', '#E2001A', '#FFFFFF'],
        'TAMOIL' => ['Tamoil', 'TA', '#E1261C', '#FFFFFF'],
        'ESCLATOIL' => ['Esclatoil', 'ES', '#F39200', '#FFFFFF'],
        '\bBP\b' => ['BP', 'BP', '#009E49', '#FFFFFF'],
        '\bQ8\b' => ['Q8', 'Q8', '#D0021B', '#FFFFFF'],
        '\bGM\b' => ['GM Oil', 'GM', '#1F4E79', '#FFFFFF'],
    ];

    /** Para las que no se reconocen: gris y la primera letra del rótulo. */
    private const UNKNOWN = ['#525252', '#FFFFFF'];

    /**
     * @return object{key: string, name: string, short: string, bg: string, ink: string}
     */
    public static function for(?string $label): object
    {
        // Sin tildes y en mayúsculas: el rótulo llega como lo declara cada
        // estación y no se puede contar con una forma concreta.
        $haystack = Str::upper(Str::ascii((string) $label));

        foreach (self::BRANDS as $pattern => [$name, $short, $bg, $ink]) {
            if (preg_match('/'.$pattern.'/u', $haystack) === 1) {
                return (object) [
                    // La clave identifica la imagen dentro del mapa, así que
                    // tiene que ser estable y sin caracteres raros.
                    'key' => Str::slug($name),
                    'name' => $name,
                    'short' => $short,
                    'bg' => $bg,
                    'ink' => $ink,
                ];
            }
        }

        $initial = mb_substr(trim((string) $label), 0, 1);
        $initial = $initial === '' ? '?' : Str::upper(Str::ascii($initial));

        return (object) [
            'key' => 'otra-'.mb_strtolower($initial === '?' ? 'x' : $initial),
            'name' => 'Sin marca reconocida',
            'short' => $initial,
            'bg' => self::UNKNOWN[0],
            'ink' => self::UNKNOWN[1],
        ];
    }
}
