<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Contracts\Auth\Access\Gate;

/** Contadores das filas do admin: total por estado e quantas ainda não foram vistas por nenhum admin. */
final readonly class SummarizeVersions
{
    public function __construct(private Gate $gate) {}

    /** @return array<string, array{total: int, new: int}> todos os estados, inclusive os vazios */
    public function __invoke(User $actor): array
    {
        $this->gate->forUser($actor)->authorize(Permission::AuthorizeImports->value);

        $rows = GameVersion::query()
            ->toBase()
            ->selectRaw('status, count(*) as total, count(*) filter (where status_seen_at is null) as unseen')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $summary = [];
        foreach (VersionStatus::cases() as $status) {
            $row = $rows->get($status->value);
            $summary[$status->value] = [
                'total' => is_object($row) && is_numeric($row->total ?? null) ? (int) $row->total : 0,
                'new' => is_object($row) && is_numeric($row->unseen ?? null) ? (int) $row->unseen : 0,
            ];
        }

        return $summary;
    }
}
