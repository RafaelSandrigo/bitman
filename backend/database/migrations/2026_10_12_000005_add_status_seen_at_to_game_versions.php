<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag "Novo" nas filas do admin: quando um admin viu a versão no estado atual. Volta a null a
 * cada transição feita pelo sistema ou pelo dono. As versões existentes começam como "novas".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->timestampTz('status_seen_at')->nullable();
            $table->index(['status', 'status_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->dropIndex(['status', 'status_seen_at']);
            $table->dropColumn('status_seen_at');
        });
    }
};
