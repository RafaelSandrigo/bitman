<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 — publicação, retirada e bloqueio (I6–I9, J6). O `public_id` (UUID) é o único nome do
 * diretório servido pela origem de jogos; ele nunca é derivado de nada que venha do Git.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->foreignUlid('published_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignUlid('withdrawn_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE game_versions ADD CONSTRAINT game_versions_publication_check CHECK (
                status NOT IN ('published', 'superseded', 'withdrawn')
                OR (public_id IS NOT NULL AND published_by_id IS NOT NULL AND published_at IS NOT NULL)
            )
            SQL);
        DB::statement("ALTER TABLE game_versions ADD CONSTRAINT game_versions_withdrawal_check CHECK (status <> 'withdrawn' OR (withdrawal_reason IS NOT NULL AND withdrawn_by_id IS NOT NULL))");
        // I8: no máximo uma versão publicada por jogo.
        DB::statement("CREATE UNIQUE INDEX game_versions_one_published ON game_versions (game_id) WHERE status = 'published'");

        Schema::table('games', function (Blueprint $table): void {
            $table->foreignUlid('published_version_id')->nullable()->unique()->constrained('game_versions')->restrictOnDelete();
            $table->timestampTz('first_published_at')->nullable();
            $table->foreignUlid('blocked_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('blocked_at')->nullable();
            $table->text('block_reason')->nullable();
        });

        DB::statement("ALTER TABLE games ADD CONSTRAINT games_block_check CHECK (status <> 'blocked' OR (block_reason IS NOT NULL AND blocked_by_id IS NOT NULL))");
        DB::statement("ALTER TABLE games ADD CONSTRAINT games_published_check CHECK (status NOT IN ('published', 'blocked') OR first_published_at IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS games_published_check');
        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS games_block_check');
        Schema::table('games', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_version_id');
            $table->dropConstrainedForeignId('blocked_by_id');
            $table->dropColumn(['first_published_at', 'blocked_at', 'block_reason']);
        });

        DB::statement('DROP INDEX IF EXISTS game_versions_one_published');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT IF EXISTS game_versions_withdrawal_check');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT IF EXISTS game_versions_publication_check');
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_by_id');
            $table->dropConstrainedForeignId('withdrawn_by_id');
            $table->dropColumn(['public_id', 'published_at', 'withdrawn_at', 'withdrawal_reason']);
        });
    }
};
