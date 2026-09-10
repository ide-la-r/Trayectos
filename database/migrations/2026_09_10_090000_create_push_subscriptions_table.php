<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A dónde mandar los avisos de cada persona.
     *
     * Una fila por navegador, no por persona: el mismo usuario puede tener la
     * aplicación en el móvil y en el portátil y querer el aviso en los dos.
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * La URL que el navegador da para hablar con él a través de Apple
             * o Google. Es larga y es la identidad de la suscripción, así que
             * se guarda su huella aparte: en MySQL una columna de texto no
             * puede llevar índice único entera, y así el día que se cambie de
             * base de datos esto sigue valiendo.
             */
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();

            // Las dos claves con las que se cifra el aviso para ese navegador
            $table->string('public_key', 255);
            $table->string('auth_token', 255);

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
