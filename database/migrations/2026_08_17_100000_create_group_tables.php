<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->char('currency', 3)->default('EUR');
            // Regla contable del grupo: ¿el conductor paga también su parte?
            $table->boolean('driver_pays_own_share')->default(true);
            $table->string('invite_code', 12)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // group_members es la "cuenta" del libro mayor: todo asiento apunta aquí
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 20)->default('member');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->index(['group_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
