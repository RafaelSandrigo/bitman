<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Score;
use Illuminate\Contracts\Auth\Access\Gate;

/** M21: quantos scores sinalizados esperam a revisão do admin (o card do painel). */
final readonly class SummarizeScores
{
    public function __construct(private Gate $gate) {}

    public function inReview(User $actor): int
    {
        $this->gate->forUser($actor)->authorize(Permission::ModerateScores->value);

        return Score::query()->where('flagged', true)->whereNull('hidden_at')->whereNull('flag_cleared_at')->count();
    }
}
