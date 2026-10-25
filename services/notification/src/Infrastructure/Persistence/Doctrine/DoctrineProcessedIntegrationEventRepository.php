<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Notification\Repository\ProcessedIntegrationEventRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Entity\ProcessedIntegrationEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessedIntegrationEvent>
 */
class DoctrineProcessedIntegrationEventRepository extends ServiceEntityRepository implements ProcessedIntegrationEventRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedIntegrationEvent::class);
    }

    public function isProcessed(string $eventId): bool
    {
        return null !== $this->find($eventId);
    }

    /**
     * Persists only — deliberately no flush(). The caller (CreateNotificationHandler)
     * flushes once, so this commits atomically alongside the Notification it guards.
     */
    public function markProcessed(string $eventId): void
    {
        $this->getEntityManager()->persist(new ProcessedIntegrationEvent($eventId));
    }
}
