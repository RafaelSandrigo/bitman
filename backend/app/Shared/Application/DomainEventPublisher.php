<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\EmitsDomainEvents;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Despacha os eventos registrados pelo agregado. Chamado dentro da transação da Action,
 * para que os listeners síncronos (auditoria) gravem no mesmo commit.
 */
final readonly class DomainEventPublisher
{
    public function __construct(private Dispatcher $events) {}

    public function publishFrom(EmitsDomainEvents $aggregate): void
    {
        foreach ($aggregate->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
