<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| audit_entries é append-only (I11): o trigger rejeita UPDATE, DELETE e TRUNCATE,
| inclusive vindos de fora da aplicação.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->timestampTz('occurred_at');
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 50);
            $table->string('subject_id', 64);
            $table->jsonb('context')->default('{}');
            $table->text('reason')->nullable();
            $table->ipAddress()->nullable();

            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index('occurred_at');
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION bitman_reject_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% é append-only: % não é permitido', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_entries_append_only
                BEFORE UPDATE OR DELETE ON audit_entries
                FOR EACH ROW EXECUTE FUNCTION bitman_reject_mutation();

            CREATE TRIGGER audit_entries_no_truncate
                BEFORE TRUNCATE ON audit_entries
                FOR EACH STATEMENT EXECUTE FUNCTION bitman_reject_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
        DB::unprepared('DROP FUNCTION IF EXISTS bitman_reject_mutation()');
    }
};
