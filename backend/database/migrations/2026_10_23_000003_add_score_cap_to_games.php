<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** M15: teto opcional de score por jogo, definido pelo admin. Acima dele, o score é sinalizado (S2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->bigInteger('score_cap')->nullable();
        });

        DB::statement('ALTER TABLE games ADD CONSTRAINT games_score_cap_check CHECK (score_cap IS NULL OR score_cap BETWEEN 1 AND 9007199254740991)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE games DROP CONSTRAINT games_score_cap_check');
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('score_cap');
        });
    }
};
