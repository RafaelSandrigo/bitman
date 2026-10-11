<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\Role;

it('player não tem permissões especiais', function (): void {
    expect(Role::Player->permissions())->toBe([]);
});

it('developer só submete jogos: nenhuma permissão administrativa (I1)', function (): void {
    expect(Role::Developer->permissions())->toBe([Permission::SubmitGames]);
});

it('admin tem todas as permissões', function (): void {
    expect(Role::Admin->permissions())->toBe(Permission::cases());
});
