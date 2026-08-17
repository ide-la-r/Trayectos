<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pagos reales entre personas. También pasan por el libro mayor: una
        // liquidación es un asiento cuadrado como cualquier otro.
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_member_id')->constrained('group_members')->restrictOnDelete();
            $table->foreignId('to_member_id')->constrained('group_members')->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('method', 40)->nullable();   // bizum, efectivo, transferencia…
            $table->date('settled_on');
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['group_id', 'settled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
