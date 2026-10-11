<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * O agregado registra eventos; a Action os despacha depois de persistir, dentro da transação.
 */
trait RecordsDomainEvents
{
    /** @var list<object> */
    private array $recordedEvents = [];

    protected function recordEvent(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
