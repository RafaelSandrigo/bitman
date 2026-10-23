<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\DeveloperApplication;
use App\Modules\Identity\Domain\DeveloperRequest;
use App\Modules\Identity\Domain\DeveloperRequestStatus;
use App\Modules\Identity\Domain\Exceptions\DeveloperRequestAlreadyOpen;
use App\Modules\Identity\Domain\User;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/** M18: o usuário pede para ser developer. Um pedido pendente por vez (índice parcial no banco). */
final readonly class RequestDeveloperRole
{
    public function __construct(
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $user, DeveloperApplication $application): DeveloperRequest
    {
        if (DeveloperRequest::query()->where('user_id', $user->id)->where('status', DeveloperRequestStatus::Pending)->exists()) {
            throw new DeveloperRequestAlreadyOpen;
        }

        try {
            return $this->db->transaction(function () use ($user, $application): DeveloperRequest {
                $request = DeveloperRequest::open($user, $application);
                $this->publisher->publishFrom($request);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DeveloperRequestAlreadyOpen;
        }
    }
}
