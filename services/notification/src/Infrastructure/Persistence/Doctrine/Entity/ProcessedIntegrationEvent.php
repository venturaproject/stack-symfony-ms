<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Persistence record for idempotent integration-event consumption; not a domain entity. */
#[ORM\Entity]
#[ORM\Table(name: 'processed_integration_events')]
final class ProcessedIntegrationEvent
{
    #[ORM\Id]
    #[ORM\Column(name: 'event_id', type: 'string', length: 36)]
    private string $eventId;

    #[ORM\Column(name: 'processed_at', type: 'datetime_immutable')]
    private DateTimeImmutable $processedAt;

    public function __construct(string $eventId)
    {
        $this->eventId = $eventId;
        $this->processedAt = new DateTimeImmutable();
    }
}
