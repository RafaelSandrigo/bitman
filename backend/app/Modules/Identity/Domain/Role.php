<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

enum Role: string
{
    /** Padrão de todo cadastro. Joga e aparece nos rankings. */
    case Player = 'player';
    /** Concedida pelo admin. Submete jogos; nunca aprova. */
    case Developer = 'developer';
    case Admin = 'admin';

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Player => [],
            self::Developer => [Permission::SubmitGames],
            self::Admin => Permission::cases(),
        };
    }
}
