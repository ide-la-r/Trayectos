<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La aplicación ya valida que cada asiento cuadre (LedgerService), pero la
     * garantía de verdad vive en la base de datos: en PostgreSQL un trigger
     * DEFERRABLE comprueba al COMMIT que la suma de líneas del asiento es 0.
     * Así ningún script, tinker o migración futura puede descuadrar el libro.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW member_balances AS
            SELECT gm.id         AS group_member_id,
                   gm.group_id   AS group_id,
                   gm.user_id    AS user_id,
                   COALESCE(SUM(jl.amount_cents), 0) AS balance_cents
            FROM group_members gm
            LEFT JOIN journal_lines jl ON jl.group_member_id = gm.id
            GROUP BY gm.id, gm.group_id, gm.user_id
        SQL);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE journal_lines
                ADD CONSTRAINT ck_journal_lines_nonzero CHECK (amount_cents <> 0)
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_journal_entry_balanced() RETURNS TRIGGER AS $$
            DECLARE
                target BIGINT := COALESCE(NEW.journal_entry_id, OLD.journal_entry_id);
                total  BIGINT;
            BEGIN
                SELECT COALESCE(SUM(amount_cents), 0) INTO total
                  FROM journal_lines WHERE journal_entry_id = target;

                IF total <> 0 THEN
                    RAISE EXCEPTION 'Asiento % descuadrado: suma % centimos (debe ser 0)', target, total;
                END IF;

                RETURN NULL;
            END $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER trg_journal_entry_balanced
                AFTER INSERT OR UPDATE OR DELETE ON journal_lines
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION assert_journal_entry_balanced()
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_journal_entry_balanced ON journal_lines');
            DB::statement('DROP FUNCTION IF EXISTS assert_journal_entry_balanced()');
            DB::statement('ALTER TABLE journal_lines DROP CONSTRAINT IF EXISTS ck_journal_lines_nonzero');
        }

        DB::statement('DROP VIEW IF EXISTS member_balances');
    }
};
