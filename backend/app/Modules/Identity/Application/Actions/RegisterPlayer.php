<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Cadastro aberto: toda conta nasce com a role player (ADR 0007).
 * Os dados chegam já validados (Infrastructure\Fortify\CreateNewUser).
 */
final readonly class RegisterPlayer
{
    public function __construct(private ConnectionInterface $db) {}

    public function __invoke(string $name, string $email, string $password): User
    {
        return $this->db->transaction(fn (): User => User::register($name, $email, $password));
    }
}
