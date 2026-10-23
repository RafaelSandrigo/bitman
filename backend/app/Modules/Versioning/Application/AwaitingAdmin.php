<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application;

use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;

/** M3: quantas versões esperam uma decisão do admin em cada portão. Roda como sistema (resumo diário). */
final readonly class AwaitingAdmin
{
    /** @return array<string, int> rótulo => total, só as filas com algo */
    public function queues(): array
    {
        $labels = [
            VersionStatus::ImportRequested->value => 'Portão 0 (autorizar importação)',
            VersionStatus::AwaitingCommitReview->value => 'Portão 1 (revisar commit)',
            VersionStatus::AwaitingFileReview->value => 'Portão 2 (revisar arquivos)',
        ];
        $counts = GameVersion::query()->whereIn('status', array_keys($labels))->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $queues = [];
        foreach ($labels as $status => $label) {
            $total = (int) ($counts[$status] ?? 0);
            if ($total > 0) {
                $queues[$label] = $total;
            }
        }

        return $queues;
    }
}
