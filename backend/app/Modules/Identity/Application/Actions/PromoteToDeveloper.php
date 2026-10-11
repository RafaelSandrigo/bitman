<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\User;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

final readonly class PromoteToDeveloper
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $userId): User
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageRoles->value);

        $user = User::findOrFail($userId);

        $this->db->transaction(function () use ($user, $actor): void {
            $user->grantRole(Role::Developer, $actor);
            $this->publisher->publishFrom($user);
        });

        return $user;
    }
}
