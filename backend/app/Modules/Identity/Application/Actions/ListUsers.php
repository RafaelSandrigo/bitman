<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListUsers
{
    private const PER_PAGE = 25;

    public function __construct(private Gate $gate) {}

    /** @return LengthAwarePaginator<int, User> */
    public function __invoke(User $actor, ?string $search): LengthAwarePaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageRoles->value);

        return User::query()
            ->with('roles')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(fn (Builder $q) => $q->whereLike('name', $term)->orWhereLike('email', $term));
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE);
    }
}
