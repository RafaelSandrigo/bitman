<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\User;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Database\ConnectionInterface;

/**
 * Bootstrap do primeiro admin. Só é alcançável por quem tem shell no servidor,
 * por isso não passa pelo Gate; a concessão é auditada com ator nulo (via console).
 */
final readonly class GrantRoleFromConsole
{
    public function __construct(
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(string $email, Role $role): User
    {
        $user = User::where('email', mb_strtolower($email))->firstOrFail();

        $this->db->transaction(function () use ($user, $role): void {
            $user->grantRole($role, null);
            $this->publisher->publishFrom($user);
        });

        return $user;
    }
}
