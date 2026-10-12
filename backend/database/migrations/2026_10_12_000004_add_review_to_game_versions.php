<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 — Portões 1 e 2. Decisão do commit e rejeição final na própria versão; decisões de
 * arquivo num registro append-only (`file_reviews`), cada uma vinculada ao SHA-256 analisado (I5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->foreignUlid('commit_decided_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('commit_decided_at')->nullable();
            $table->text('commit_rejection_reason')->nullable();
            $table->foreignUlid('rejected_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });

        DB::statement("ALTER TABLE game_versions ADD CONSTRAINT game_versions_commit_rejection_check CHECK (status <> 'commit_rejected' OR (commit_rejection_reason IS NOT NULL AND commit_decided_by_id IS NOT NULL))");
        // Da revisão dos arquivos em diante, o commit foi aprovado por alguém (I1, I11).
        DB::statement(<<<'SQL'
            ALTER TABLE game_versions ADD CONSTRAINT game_versions_commit_approved_check CHECK (
                status NOT IN ('awaiting_file_review', 'rejected', 'published', 'superseded', 'withdrawn')
                OR (commit_decided_by_id IS NOT NULL AND commit_rejection_reason IS NULL)
            )
            SQL);
        DB::statement("ALTER TABLE game_versions ADD CONSTRAINT game_versions_rejection_check CHECK (status <> 'rejected' OR (rejection_reason IS NOT NULL AND rejected_by_id IS NOT NULL))");

        Schema::create('file_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('version_file_id')->constrained('version_files')->restrictOnDelete();
            $table->string('sha256', 64);
            $table->string('decision', 20);
            $table->foreignUlid('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');

            $table->index(['version_file_id', 'created_at']);
        });

        DB::statement("ALTER TABLE file_reviews ADD CONSTRAINT file_reviews_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE file_reviews ADD CONSTRAINT file_reviews_decision_check CHECK (decision IN ('approved', 'rejected'))");
        DB::statement("ALTER TABLE file_reviews ADD CONSTRAINT file_reviews_reason_check CHECK (decision <> 'rejected' OR reason IS NOT NULL)");
        // Histórico de decisões: nunca muda nem some (ADR 0008; a função vem da migration da auditoria).
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER file_reviews_append_only
                BEFORE UPDATE OR DELETE ON file_reviews
                FOR EACH ROW EXECUTE FUNCTION bitman_reject_mutation();

            CREATE TRIGGER file_reviews_no_truncate
                BEFORE TRUNCATE ON file_reviews
                FOR EACH STATEMENT EXECUTE FUNCTION bitman_reject_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('file_reviews');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT IF EXISTS game_versions_rejection_check');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT IF EXISTS game_versions_commit_approved_check');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT IF EXISTS game_versions_commit_rejection_check');
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rejected_by_id');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
            $table->dropConstrainedForeignId('commit_decided_by_id');
            $table->dropColumn(['commit_decided_at', 'commit_rejection_reason']);
        });
    }
};
