<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\DeveloperRequest;
use App\Modules\Identity\Domain\DeveloperRequestStatus;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** M18: a fila de pedidos para o admin (pendentes primeiro, os mais antigos antes). */
final readonly class ListDeveloperRequests
{
    public function __construct(private Gate $gate) {}

    /** @return LengthAwarePaginator<int, DeveloperRequest> */
    public function __invoke(User $actor, DeveloperRequestStatus $status): LengthAwarePaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageRoles->value);

        return DeveloperRequest::query()
            ->where('status', $status)
            ->with(['user', 'decidedBy'])
            ->orderBy($status === DeveloperRequestStatus::Pending ? 'created_at' : 'decided_at', $status === DeveloperRequestStatus::Pending ? 'asc' : 'desc')
            ->paginate(20);
    }

    /** O card do painel. */
    public function pendingCount(User $actor): int
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageRoles->value);

        return DeveloperRequest::query()->where('status', DeveloperRequestStatus::Pending)->count();
    }
}
