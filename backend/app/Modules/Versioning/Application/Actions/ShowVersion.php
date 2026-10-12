<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use Illuminate\Contracts\Auth\Access\Gate;

final readonly class ShowVersion
{
    public function __construct(private Gate $gate) {}

    public function __invoke(User $actor, string $versionId): GameVersion
    {
        $this->gate->forUser($actor)->authorize(Permission::AuthorizeImports->value);

        return GameVersion::query()->with(['game', 'requestedBy', 'importDecidedBy', 'files'])->findOrFail($versionId);
    }
}
