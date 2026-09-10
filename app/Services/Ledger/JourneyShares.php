<?php

declare(strict_types=1);

namespace App\Services\Ledger;

/**
 * Qué parte del viaje le corresponde a cada uno, tramo a tramo.
 *
 * El reparto anterior era proporcional al trozo que hacía cada uno, y eso está
 * mal. Málaga → Granada con Ana entera y Bea recogida a mitad de camino daba
 * 66,7 % y 33,3 %; pero la primera mitad la hizo Ana SOLA y esa mitad es suya
 * entera. Lo correcto es 75 % y 25 %.
 *
 * La cuenta es la del taxi compartido: el viaje se parte en tramos por los
 * puntos donde cambia la gente que va dentro, y cada tramo lo pagan a partes
 * iguales los que iban en él.
 *
 *   Ana 1,0 · Bea 0,5
 *   ├─ del 0 al 0,5 → van dos     → 0,25 para cada una
 *   └─ del 0,5 al 1 → va Ana sola → 0,50 para Ana
 *                                   Ana 0,75 · Bea 0,25
 *
 * Se asume que quien hace menos viaje va en la PRIMERA parte —le recogen
 * antes de llegar, o se baja por el camino—. Da igual cuál de las dos cosas
 * sea: el resultado es el mismo, porque lo único que importa es cuánta gente
 * iba dentro en cada momento y no en qué orden se subieron.
 *
 * Un tramo por el que no pasa ningún pagador no se cobra a nadie: es el del
 * conductor cuando el grupo ha decidido que no paga su parte, y se lo come él.
 * Por eso las partes NO tienen por qué sumar uno.
 */
final class JourneyShares
{
    /**
     * @param  array<int|string, float>  $presence  [memberId => parte del viaje que hizo, de 0 a 1]
     * @return array<int|string, float> [memberId => parte del coste que le toca]
     */
    public function fractions(array $presence): array
    {
        $presence = array_filter($presence, fn (float $part) => $part > 0);

        if ($presence === []) {
            return [];
        }

        // Ordenados de menos a más viaje: los cortes entre tramos son
        // justamente los puntos donde alguien se baja.
        asort($presence);

        $shares = array_fill_keys(array_keys($presence), 0.0);
        $ids = array_keys($presence);
        $start = 0.0;

        foreach ($ids as $position => $id) {
            $end = min(1.0, $presence[$id]);
            $length = $end - $start;

            if ($length <= 0) {
                continue;   // Otro que hace exactamente lo mismo: mismo corte
            }

            // En este tramo van los que quedan, o sea éste y los de detrás
            $aboard = count($ids) - $position;
            $each = $length / $aboard;

            for ($i = $position; $i < count($ids); $i++) {
                $shares[$ids[$i]] += $each;
            }

            $start = $end;
        }

        return $shares;
    }
}
