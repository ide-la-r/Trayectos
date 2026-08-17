<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_member_id')->constrained('group_members')->restrictOnDelete();
            $table->date('travelled_on');

            $table->string('origin_label', 160);
            $table->string('destination_label', 160);
            $table->decimal('origin_lat', 9, 6)->nullable();
            $table->decimal('origin_lon', 9, 6)->nullable();
            $table->decimal('destination_lat', 9, 6)->nullable();
            $table->decimal('destination_lon', 9, 6)->nullable();
            $table->boolean('round_trip')->default(false);

            $table->unsignedInteger('distance_m');
            $table->unsignedInteger('ascent_m')->default(0);
            $table->unsignedInteger('descent_m')->default(0);
            $table->unsignedSmallInteger('luggage_kg')->default(0);
            $table->unsignedTinyInteger('battery_start_pct')->default(0); // sólo PHEV/BEV

            // De dónde salieron distancia y desnivel: ors | haversine | manual
            $table->string('route_source', 20)->default('manual');
            $table->json('route_geometry')->nullable();

            $table->unsignedInteger('total_cost_cents');
            // Snapshot inmutable de TODO lo que entró en el cálculo. Sin esto,
            // recalibrar un vehículo reescribiría la historia contable.
            $table->json('cost_inputs');
            $table->unsignedTinyInteger('formula_version');

            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['group_id', 'travelled_on']);
            $table->index(['driver_member_id', 'travelled_on']);
        });

        Schema::create('trip_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_member_id')->constrained()->restrictOnDelete();
            // 1.00 = trayecto completo · 0.50 = sólo ida, o mitad del recorrido
            $table->decimal('weight', 4, 2)->default(1.00);
            $table->timestamps();

            $table->unique(['trip_id', 'group_member_id']);
        });

        // Repostajes reales: la única fuente de verdad para calibrar el modelo
        Schema::create('refuels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('refuelled_on');
            $table->decimal('litres', 6, 2)->nullable();
            $table->decimal('kwh', 7, 2)->nullable();
            $table->unsignedInteger('cost_cents');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->boolean('full_tank')->default(true);
            $table->timestamps();

            $table->index(['vehicle_id', 'refuelled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refuels');
        Schema::dropIfExists('trip_passengers');
        Schema::dropIfExists('trips');
    }
};
