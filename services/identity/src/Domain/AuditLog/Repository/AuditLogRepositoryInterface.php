<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Repository;

use App\Application\AuditLog\DTO\PagedAuditLogResponse;
use App\Domain\AuditLog\Entity\AuditLog;

interface AuditLogRepositoryInterface
{
    public function save(AuditLog $entry): void;

    public function getLastHash(): string;

    public function findByActorId(string $actorId, int $page, int $size): PagedAuditLogResponse;
}
