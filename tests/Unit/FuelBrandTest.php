<?php

namespace Tests\Unit;

use App\Support\FuelBrand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La marca sale del rótulo porque el Ministerio no publica ese campo, y el
 * rótulo viene como lo declara cada estación.
 */
class FuelBrandTest extends TestCase
{
    public static function rotulosReales(): array
    {
        return [
            ['REPSOL', 'Repsol', 'RE'],
            ['CEPSA', 'Cepsa', 'CE'],
            ['E.S. GALP ENERGIA ESPAÑA SA', 'Galp', 'GA'],
            ['CARREFOUR', 'Carrefour', 'CF'],
            ['ESTACION DE SERVICIO SHELL LA ROSALEDA', 'Shell', 'SH'],
            ['PETROPRIX', 'Petroprix', 'PX'],
            ['BALLENOIL MALAGA', 'Ballenoil', 'BA'],
            ['BP', 'BP', 'BP'],
            ['E.S. BP CARRETERA DE CADIZ', 'BP', 'BP'],
            ['Q8 TEATINOS', 'Q8', 'Q8'],
        ];
    }

    #[DataProvider('rotulosReales')]
    public function test_reconoce_las_marcas_de_los_rotulos(string $rotulo, string $nombre, string $iniciales): void
    {
        $marca = FuelBrand::for($rotulo);

        $this->assertSame($nombre, $marca->name);
        $this->assertSame($iniciales, $marca->short);
    }

    public function test_da_igual_como_venga_escrito(): void
    {
        // El rótulo llega en mayúsculas, en minúsculas y con o sin tildes
        foreach (['repsol', 'Repsol', 'REPSÓL'] as $rotulo) {
            $this->assertSame('Repsol', FuelBrand::for($rotulo)->name, $rotulo);
        }
    }

    public function test_las_siglas_cortas_no_casan_dentro_de_otra_palabra(): void
    {
        /*
         * Éste es el motivo del límite de palabra en los patrones de dos
         * letras: sin él, «SIGMA» contiene «GM» y esta estación pasaría por
         * ser una GM Oil.
         */
        $this->assertSame('Sin marca reconocida', FuelBrand::for('SIGMA OIL')->name);
        $this->assertSame('GM Oil', FuelBrand::for('E.S. GM OIL')->name);
    }

    public function test_una_marca_desconocida_se_queda_con_su_inicial(): void
    {
        $marca = FuelBrand::for('Estacion de servicio La Parra');

        $this->assertSame('Sin marca reconocida', $marca->name);
        $this->assertSame('E', $marca->short);
        // La clave identifica la imagen dentro del mapa: tiene que ser estable
        $this->assertSame('otra-e', $marca->key);
    }

    public function test_un_rotulo_vacio_no_revienta(): void
    {
        foreach ([null, '', '   '] as $rotulo) {
            $marca = FuelBrand::for($rotulo);

            $this->assertSame('?', $marca->short);
            $this->assertNotSame('', $marca->key);
        }
    }

    public function test_cada_marca_trae_color_de_fondo_y_de_tinta(): void
    {
        // Sin las dos cosas la insignia sale ilegible: el amarillo de Shell
        // necesita tinta oscura y el naranja de Repsol la necesita blanca.
        foreach (['SHELL', 'REPSOL', 'CEPSA', 'cualquier cosa'] as $rotulo) {
            $marca = FuelBrand::for($rotulo);

            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $marca->bg, $rotulo);
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $marca->ink, $rotulo);
        }
    }
}
