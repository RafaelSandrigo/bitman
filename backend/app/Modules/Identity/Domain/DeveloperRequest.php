<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Events\DeveloperRequestApproved;
use App\Modules\Identity\Domain\Events\DeveloperRequestRejected;
use App\Modules\Identity\Domain\Events\DeveloperRoleRequested;
use App\Modules\Identity\Domain\Exceptions\AlreadyDeveloper;
use App\Modules\Identity\Domain\Exceptions\DeveloperRequestAlreadyDecided;
use App\Modules\Identity\Domain\Exceptions\EmailNotVerified;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Shared\Domain\EmitsDomainEvents;
use App\Shared\Domain\RecordsDomainEvents;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido para ser developer (M18). Nome, GitHub e apresentação são texto do usuário: a SPA os
 * mostra só como texto. Aprovar concede a role pelo `User::grantRole()`, como a promoção direta.
 *
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string $github_url
 * @property string $bio
 * @property DeveloperRequestStatus $status
 * @property string|null $decided_by_id
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_reason
 * @property CarbonImmutable $created_at
 * @property-read User $user
 * @property-read User|null $decidedBy
 */
final class DeveloperRequest extends Model implements EmitsDomainEvents
{
    use HasUlids, RecordsDomainEvents;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    public static function open(User $user, DeveloperApplication $application): self
    {
        if ($user->hasRole(Role::Developer)) {
            throw new AlreadyDeveloper;
        }
        if (! $user->hasVerifiedEmail()) {
            throw EmailNotVerified::forRoleGrant();
        }

        $request = new self;
        $request->user_id = $user->id;
        $request->name = $application->name;
        $request->github_url = $application->github->value;
        $request->bio = $application->bio;
        $request->status = DeveloperRequestStatus::Pending;
        $request->save();
        $request->recordEvent(new DeveloperRoleRequested($request->id, $user->id, $user->id));

        return $request;
    }

    /** O usuário vira developer; o `RoleGranted` sai do próprio User. */
    public function approve(User $admin): void
    {
        $this->decide($admin, DeveloperRequestStatus::Approved, null);
        $this->user->grantRole(Role::Developer, $admin);
        $this->recordEvent(new DeveloperRequestApproved($this->id, $this->user_id, $admin->id));
    }

    public function reject(User $admin, DecisionReason $reason): void
    {
        $this->decide($admin, DeveloperRequestStatus::Rejected, $reason->value);
        $this->recordEvent(new DeveloperRequestRejected($this->id, $this->user_id, $admin->id, $reason->value));
    }

    private function decide(User $admin, DeveloperRequestStatus $status, ?string $reason): void
    {
        if ($this->status !== DeveloperRequestStatus::Pending) {
            throw new DeveloperRequestAlreadyDecided;
        }

        $this->status = $status;
        $this->decided_by_id = $admin->id;
        $this->decided_at = CarbonImmutable::now();
        $this->decision_reason = $reason;
        $this->save();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    protected function casts(): array
    {
        return [
            'status' => DeveloperRequestStatus::class,
            'decided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
