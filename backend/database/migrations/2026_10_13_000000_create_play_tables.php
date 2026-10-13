<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 — partidas e scores (contexto Play). O banco reforça I10 e S1: uma sessão aceita no
 * máximo um score (`unique(game_session_id)`) e cada jogador tem no máximo uma sessão aberta por jogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('game_id')->constrained('games')->restrictOnDelete();
            $table->foreignUlid('game_version_id')->constrained('game_versions')->restrictOnDelete();
            // SHA-256 do token de uso único; o token em si só existe na resposta que criou a sessão.
            $table->char('token_hash', 64);
            $table->string('status', 20);
            $table->timestampTz('started_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('ended_at')->nullable();

            $table->index(['user_id', 'started_at']);
        });

        DB::statement("ALTER TABLE game_sessions ADD CONSTRAINT game_sessions_status_check CHECK (status IN ('open', 'scored', 'expired'))");
        DB::statement("ALTER TABLE game_sessions ADD CONSTRAINT game_sessions_ended_check CHECK ((status = 'open') = (ended_at IS NULL))");
        DB::statement('ALTER TABLE game_sessions ADD CONSTRAINT game_sessions_expiry_check CHECK (expires_at > started_at)');
        DB::statement("ALTER TABLE game_sessions ADD CONSTRAINT game_sessions_token_hash_check CHECK (token_hash ~ '^[0-9a-f]{64}$')");
        // Começar uma partida encerra a anterior do mesmo jogo: nunca há duas abertas em paralelo.
        DB::statement("CREATE UNIQUE INDEX game_sessions_one_open ON game_sessions (user_id, game_id) WHERE status = 'open'");

        Schema::create('scores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('game_session_id')->unique()->constrained('game_sessions')->restrictOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('game_id')->constrained('games')->restrictOnDelete();
            $table->foreignUlid('game_version_id')->constrained('game_versions')->restrictOnDelete();
            $table->bigInteger('value');
            $table->boolean('flagged')->default(false);
            $table->text('flag_reason')->nullable();
            $table->foreignUlid('hidden_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('hidden_at')->nullable();
            $table->text('hide_reason')->nullable();
            $table->timestampTz('created_at');

            // Ranking: melhor valor por jogador (DISTINCT ON user_id ... ORDER BY value DESC).
            $table->index(['game_id', 'user_id', 'value']);
            $table->index(['user_id', 'created_at']);
        });

        // Score::MAX = Number.MAX_SAFE_INTEGER (docs/dominio.md#score-limite-técnico).
        DB::statement('ALTER TABLE scores ADD CONSTRAINT scores_value_check CHECK (value BETWEEN 0 AND 9007199254740991)');
        DB::statement('ALTER TABLE scores ADD CONSTRAINT scores_flag_check CHECK (NOT flagged OR flag_reason IS NOT NULL)');
        DB::statement('ALTER TABLE scores ADD CONSTRAINT scores_hidden_check CHECK (hidden_at IS NULL OR (hidden_by_id IS NOT NULL AND hide_reason IS NOT NULL))');
        DB::statement('CREATE INDEX scores_flagged ON scores (created_at) WHERE flagged');
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('game_sessions');
    }
};
