<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Versões (docs/dominio.md#gameversion-versioning--agregado-central). As colunas dos portões,
| do commit e da publicação entram nas fases que as usam.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('game_id')->constrained('games')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 30)->index();
            $table->string('repository_url', 255);
            $table->string('requested_ref', 100);
            $table->foreignUlid('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['game_id', 'number']);
        });

        DB::statement('ALTER TABLE game_versions ADD CONSTRAINT game_versions_number_check CHECK (number >= 1)');
        DB::statement(<<<'SQL'
            ALTER TABLE game_versions ADD CONSTRAINT game_versions_status_check CHECK (status IN (
                'import_requested', 'import_rejected', 'fetching', 'fetch_failed', 'awaiting_commit_review',
                'commit_rejected', 'awaiting_file_review', 'rejected', 'published', 'superseded', 'withdrawn'
            ))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('game_versions');
    }
};
