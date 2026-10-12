<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Fase 3: decisão do Portão 0, resultado do fetch e arquivos candidatos (docs/dominio.md).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->foreignUlid('import_decided_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('import_decided_at')->nullable();
            $table->text('import_rejection_reason')->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->jsonb('validation_report')->nullable();
            $table->string('fetch_failure', 40)->nullable();
            $table->timestampTz('fetched_at')->nullable();
        });

        DB::statement("ALTER TABLE game_versions ADD CONSTRAINT game_versions_commit_sha_check CHECK (commit_sha ~ '^([0-9a-f]{40}|[0-9a-f]{64})$')");
        // I13: da revisão do commit em diante, a versão está sempre vinculada ao commit buscado.
        DB::statement(<<<'SQL'
            ALTER TABLE game_versions ADD CONSTRAINT game_versions_fetched_commit_check CHECK (
                status IN ('import_requested', 'import_rejected', 'fetching', 'fetch_failed')
                OR (commit_sha IS NOT NULL AND validation_report IS NOT NULL)
            )
            SQL);
        DB::statement("ALTER TABLE game_versions ADD CONSTRAINT game_versions_rejection_reason_check CHECK (status <> 'import_rejected' OR import_rejection_reason IS NOT NULL)");
        // No máximo uma versão em andamento por jogo.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX game_versions_one_in_progress ON game_versions (game_id)
            WHERE status IN ('import_requested', 'fetching', 'awaiting_commit_review', 'awaiting_file_review')
            SQL);

        Schema::create('version_files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('game_version_id')->constrained('game_versions')->restrictOnDelete();
            $table->string('path', 100);
            $table->char('sha256', 64);
            $table->unsignedInteger('size');
            $table->string('git_blob_sha', 64);
            $table->string('change_type', 20);
            $table->string('review_status', 20);
            $table->timestampsTz();

            $table->unique(['game_version_id', 'path']);
        });

        DB::statement("ALTER TABLE version_files ADD CONSTRAINT version_files_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE version_files ADD CONSTRAINT version_files_change_type_check CHECK (change_type IN ('added', 'modified', 'unchanged'))");
        DB::statement("ALTER TABLE version_files ADD CONSTRAINT version_files_review_status_check CHECK (review_status IN ('pending', 'approved', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('version_files');
        DB::statement('DROP INDEX IF EXISTS game_versions_one_in_progress');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT IF EXISTS game_versions_rejection_reason_check');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT IF EXISTS game_versions_fetched_commit_check');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT IF EXISTS game_versions_commit_sha_check');
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('import_decided_by_id');
            $table->dropColumn(['import_decided_at', 'import_rejection_reason', 'commit_sha', 'validation_report', 'fetch_failure', 'fetched_at']);
        });
    }
};
