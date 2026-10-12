<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Um admin viu estas versões (na fila ou no detalhe). Cada uma só é marcada se ainda estiver no
 * estado que foi exibido: se mudou enquanto isso, o estado novo continua "novo".
 */
final readonly class MarkVersionsSeen
{
    public function __construct(private Gate $gate) {}

    /** @param  array<string, VersionStatus>  $shown  id => estado exibido */
    public function __invoke(User $actor, array $shown): void
    {
        $this->gate->forUser($actor)->authorize(Permission::AuthorizeImports->value);

        $versions = GameVersion::query()->whereKey(array_keys($shown))->whereNull('status_seen_at')->get();
        foreach ($versions as $version) {
            if ($version->status === ($shown[$version->id] ?? null)) {
                $version->markSeen();
            }
        }
    }
}
