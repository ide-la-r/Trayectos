<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Caché local del catálogo del Ministerio: nunca se consulta su API
        // dentro de una petición de usuario.
        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ideess')->unique();  // id oficial de la estación
            $table->string('label', 120)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('municipality', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('province_id', 4)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('schedule', 120)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->timestamps();

            $table->index(['lat', 'lon']);
            $table->index('province_id');
        });

        Schema::create('fuel_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_station_id')->constrained()->cascadeOnDelete();
            $table->string('fuel_kind', 8);
            // Milésimas de euro por unidad: 1,459 €/L => 1459. Nunca float.
            $table->unsignedInteger('price_milli');
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->unique(['fuel_station_id', 'fuel_kind', 'observed_at'], 'fuel_prices_unique_observation');
            $table->index(['fuel_kind', 'observed_at']);
        });

        // Precio de la energía eléctrica (PVPC de REE o tarifa declarada)
        Schema::create('energy_prices', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30);            // 'ree' | 'manual'
            $table->unsignedInteger('price_milli');  // milésimas de euro por kWh
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->unique(['source', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_prices');
        Schema::dropIfExists('fuel_prices');
        Schema::dropIfExists('fuel_stations');
    }
};
