<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Genera el par de claves con el que se firman los avisos.
 *
 * Va aquí y no en un fichero del repositorio a propósito: la clave privada es
 * un secreto y no puede acabar versionada ni pasando por una conversación. Se
 * genera una vez, se pega en el entorno, y no se vuelve a tocar.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'trayectos:vapid';

    protected $description = 'Genera el par de claves VAPID para los avisos en el móvil';

    /** Para no relanzarse en bucle si con la variable puesta tampoco va. */
    private const RETRY_FLAG = 'TRAYECTOS_VAPID_RETRY';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $exception) {
            // Con !== null y no a secas: SUCCESS es 0, que es falso
            if (($retry = $this->retryWithOpenSslConfig()) !== null) {
                return $retry;
            }

            $this->error('No se han podido generar: '.$exception->getMessage());
            $this->line('A este PHP le falta encontrar su openssl.cnf y no hemos dado con él.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Pega estas dos líneas en el .env, y las mismas en Render (Environment):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();

        $this->warn('La privada es un secreto: no la pegues en un chat ni la subas al repositorio.');
        $this->warn('Y no las cambies luego: quien haya dicho que sí a los avisos tendría que volver a decirlo.');

        return self::SUCCESS;
    }

    /**
     * Volver a intentarlo con OpenSSL apuntando a su configuración.
     *
     * En Windows con Herd, OpenSSL no encuentra su openssl.cnf y no puede
     * crear una clave de curva elíptica: falla con «configuration file
     * routines: no such file». El fichero está ahí, junto al php.ini.
     *
     * No vale con un putenv: PHP inicializa OpenSSL al arrancar y para cuando
     * corre esto ya se ha quedado con la configuración que encontró (o con la
     * que no encontró). Hay que arrancar OTRO php con la variable puesta, y
     * eso es justo lo que se hace aquí. Tampoco vale PHP_BINDIR para
     * localizarlo: con Herd dice «C:\php», que no es donde está.
     *
     * En Linux no se llega hasta aquí, porque allí no falla.
     *
     * @return int|null El resultado del intento, o null si no hay nada que probar.
     */
    private function retryWithOpenSslConfig(): ?int
    {
        // Comparando contra false y no con filled(): getenv() devuelve false
        // cuando la variable no existe, y filled(false) es TRUE — los
        // booleanos no cuentan como vacíos.
        if (getenv(self::RETRY_FLAG) !== false || getenv('OPENSSL_CONF') !== false) {
            return null;   // Ya se ha reintentado, o ya apuntaba a uno
        }

        $ini = (string) php_ini_loaded_file();

        $config = collect([
            $ini === '' ? null : dirname($ini).'/extras/ssl/openssl.cnf',
            PHP_BINDIR.'/extras/ssl/openssl.cnf',
        ])->filter(fn (?string $path) => $path !== null && is_file($path))->first();

        if ($config === null) {
            return null;
        }

        $process = new Process(
            [PHP_BINARY, base_path('artisan'), $this->getName()],
            base_path(),
            [self::RETRY_FLAG => '1', 'OPENSSL_CONF' => $config],
        );

        $process->run(fn (string $type, string $output) => $this->output->write($output));

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
