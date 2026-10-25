<?php

declare(strict_types=1);

namespace App\Application\Notification\Repository;

/** Port for the technical idempotency ledger used while consuming integration events. */
interface ProcessedIntegrationEventRepositoryInterface
{
    public function isProcessed(string $eventId): bool;

    /** Persist in the caller's transaction; implementations must not flush independently. */
    public function markProcessed(string $eventId): void;
}
