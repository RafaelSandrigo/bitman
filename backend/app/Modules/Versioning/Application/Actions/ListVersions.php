<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Versões para o admin: a fila de um estado (ordem de chegada) ou o histórico de um jogo (mais recente primeiro). */
final readonly class ListVersions
{
    public function __construct(private Gate $gate) {}

    /** @return LengthAwarePaginator<int, GameVersion> */
    public function __invoke(User $actor, ?VersionStatus $status, ?string $gameId = null): LengthAwarePaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::AuthorizeImports->value);

        $query = GameVersion::query()->with(['game', 'requestedBy']);
        if ($gameId !== null) {
            $query->where('game_id', $gameId);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        match (true) {
            $gameId !== null => $query->orderByDesc('number'),
            $status !== null => $query->oldest()->oldest('id'),
            default => $query->latest('updated_at'),
        };

        return $query->paginate(25);
    }
}
