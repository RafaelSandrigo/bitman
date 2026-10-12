<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Catálogo (docs/dominio.md#game-catalog). O status só aceita os valores do enum GameStatus.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('slug', 60)->unique();
            $table->string('name', 60);
            $table->text('description');
            $table->string('author_name', 60);
            $table->string('thumbnail_path', 64);
            $table->foreignUlid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('repository_url', 255);
            $table->string('default_ref', 100);
            $table->string('status', 20)->index();
            $table->timestampsTz();

            $table->index(['owner_id', 'created_at']);
        });

        DB::statement("ALTER TABLE games ADD CONSTRAINT games_status_check CHECK (status IN ('submitted', 'published', 'blocked', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
