<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('plate', 15)->nullable();

            $table->string('powertrain', 5);          // ICE | HEV | PHEV | BEV
            $table->string('fuel_kind', 8)->default('NONE');
            $table->unsignedTinyInteger('seats')->default(5);
            $table->unsignedInteger('kerb_weight_kg');

            // Consumos homologados: uno, otro o los dos según tecnología
            $table->decimal('consumption_l_100', 5, 2)->nullable();
            $table->decimal('consumption_kwh_100', 5, 2)->nullable();

            // Batería útil: HEV ~1,3 kWh · PHEV ~13 kWh · BEV ~60 kWh
            $table->decimal('battery_kwh_usable', 6, 2)->nullable();
            $table->unsignedSmallInteger('ev_range_km')->nullable();

            // Parámetros del modelo físico, editables por vehículo
            $table->decimal('regen_factor', 3, 2);
            $table->decimal('thermal_efficiency', 3, 2)->nullable();
            $table->decimal('calibration_factor', 4, 3)->default(1.000);

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['owner_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
