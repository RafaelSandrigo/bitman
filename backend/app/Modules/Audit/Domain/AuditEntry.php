<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use App\Modules\Identity\Domain\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fato imutável. Um trigger no PostgreSQL impede UPDATE, DELETE e TRUNCATE em audit_entries.
 *
 * @property string $id
 * @property CarbonImmutable $occurred_at
 * @property string|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, scalar|null> $context
 * @property string|null $reason
 * @property string|null $ip_address null quando a ação vem do console ou da fila
 * @property-read User|null $actor
 */
final class AuditEntry extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['*'];

    public static function fromRecord(AuditRecord $record, CarbonImmutable $occurredAt, ?string $ipAddress = null): self
    {
        $entry = new self;
        $entry->occurred_at = $occurredAt;
        $entry->ip_address = $ipAddress;
        $entry->actor_id = $record->actorId;
        $entry->action = $record->action;
        $entry->subject_type = $record->subjectType;
        $entry->subject_id = $record->subjectId;
        $entry->context = $record->context;
        $entry->reason = $record->reason;

        return $entry;
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'context' => 'array',
        ];
    }
}
