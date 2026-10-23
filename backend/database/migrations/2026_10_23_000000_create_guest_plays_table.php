<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partida de visitante (docs/dominio.md#guestplay). O score fica pendente até uma conta com
 * e-mail confirmado salvá-lo (A5); salvar é uma única vez (`claimed_score_id` único).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_plays', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('game_id')->constrained('games')->restrictOnDelete();
            $table->foreignUlid('game_version_id')->constrained('game_versions')->restrictOnDelete();
            // SHA-256 do token; o token em si só existe na resposta que criou a partida.
            $table->char('token_hash', 64);
            $table->timestampTz('started_at');
            $table->timestampTz('expires_at');
            $table->bigInteger('value')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampTz('claim_expires_at')->nullable();
            $table->foreignUlid('claimed_score_id')->nullable()->unique()->constrained('scores')->restrictOnDelete();

            $table->index('started_at');
        });

        DB::statement("ALTER TABLE guest_plays ADD CONSTRAINT guest_plays_token_hash_check CHECK (token_hash ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE guest_plays ADD CONSTRAINT guest_plays_value_check CHECK (value BETWEEN 0 AND 9007199254740991)');
        // O score, o fim e o prazo para salvar nascem juntos; só se salva o que tem score.
        DB::statement('ALTER TABLE guest_plays ADD CONSTRAINT guest_plays_scored_check CHECK ((value IS NULL) = (ended_at IS NULL) AND (value IS NULL) = (claim_expires_at IS NULL))');
        DB::statement('ALTER TABLE guest_plays ADD CONSTRAINT guest_plays_claimed_check CHECK (claimed_score_id IS NULL OR value IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_plays');
    }
};
