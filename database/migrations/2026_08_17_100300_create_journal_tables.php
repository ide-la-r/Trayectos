<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Libro mayor por partida doble. Append-only: un error no se edita,
        // se contraasienta con un asiento de tipo 'reversal'.
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                 // trip | settlement | adjustment | reversal
            $table->string('description', 255);
            $table->date('occurred_on');
            // Idempotencia: 'trip:123'. Un doble submit no puede duplicar el asiento.
            $table->string('external_ref', 80)->nullable();
            $table->foreignId('reverses_id')->nullable()->constrained('journal_entries');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['group_id', 'external_ref']);
            $table->index(['group_id', 'occurred_on']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_member_id')->constrained()->restrictOnDelete();
            // Signo: (+) el grupo le debe a este miembro · (−) este miembro debe al grupo.
            // La suma de todas las líneas de un asiento es exactamente 0.
            $table->integer('amount_cents');
            $table->string('memo', 160)->nullable();
            $table->timestamps();

            $table->index('group_member_id');
            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
