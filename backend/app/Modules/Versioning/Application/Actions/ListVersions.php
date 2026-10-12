<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Versões para o admin. Filtradas por estado, em ordem de chegada (é uma fila). */
final readonly class ListVersions
{
    public function __construct(private Gate $gate) {}

    /** @return LengthAwarePaginator<int, GameVersion> */
    public function __invoke(User $actor, ?VersionStatus $status): LengthAwarePaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::AuthorizeImports->value);

        $query = GameVersion::query()->with(['game', 'requestedBy']);

        $status === null
            ? $query->latest('updated_at')
            : $query->where('status', $status)->oldest()->oldest('id');

        return $query->paginate(25);
    }
}
