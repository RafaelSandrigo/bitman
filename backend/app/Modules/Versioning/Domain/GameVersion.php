<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

use App\Modules\Versioning\Domain\Events\ImportRequested;
use App\Shared\Domain\EmitsDomainEvents;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use App\Shared\Domain\RecordsDomainEvents;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Agregado central do versionamento. Nasce como solicitação de importação: nenhum acesso à
 * rede acontece antes da autorização do Portão 0 (I2).
 *
 * @property string $id
 * @property string $game_id
 * @property int $number
 * @property VersionStatus $status
 * @property string $repository_url snapshot da URL no momento da solicitação
 * @property string $requested_ref
 * @property string $requested_by_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class GameVersion extends Model implements EmitsDomainEvents
{
    use HasUlids, RecordsDomainEvents;

    protected $guarded = ['*'];

    public static function requestImport(string $gameId, RepositoryUrl $repositoryUrl, GitRef $ref, string $requestedById): self
    {
        $version = new self;
        $version->game_id = $gameId;
        $version->number = self::nextNumberFor($gameId);
        $version->repository_url = $repositoryUrl->value;
        $version->requested_ref = $ref->value;
        $version->requested_by_id = $requestedById;
        $version->status = VersionStatus::ImportRequested;
        $version->save();

        $version->recordEvent(new ImportRequested($version->id, $gameId, $version->number, $repositoryUrl, $ref, $requestedById));

        return $version;
    }

    /** Numeração sequencial por jogo; `unique(game_id, number)` no banco é a garantia final. */
    private static function nextNumberFor(string $gameId): int
    {
        $last = self::query()->where('game_id', $gameId)->max('number');

        return (is_numeric($last) ? (int) $last : 0) + 1;
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => VersionStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
