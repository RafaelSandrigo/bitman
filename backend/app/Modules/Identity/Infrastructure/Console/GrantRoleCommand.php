<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Console;

use App\Modules\Identity\Application\Actions\GrantRoleFromConsole;
use App\Modules\Identity\Domain\Role;
use App\Shared\Domain\BusinessRuleViolation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class GrantRoleCommand extends Command
{
    protected $signature = 'user:grant-role {email} {role : developer ou admin}';

    protected $description = 'Concede uma role a um usuário (bootstrap do primeiro admin)';

    public function handle(GrantRoleFromConsole $grantRole): int
    {
        $role = Role::tryFrom((string) $this->argument('role'));

        if ($role === null || $role === Role::Player) {
            $this->error('Role inválida. Use developer ou admin.');

            return self::INVALID;
        }

        try {
            $user = $grantRole((string) $this->argument('email'), $role);
        } catch (ModelNotFoundException) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        } catch (BusinessRuleViolation $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Role {$role->value} concedida a {$user->email}.");

        return self::SUCCESS;
    }
}
