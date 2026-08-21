<?php

declare(strict_types=1);

namespace App\Application\AuditLog\Query;

use App\Application\AuditLog\DTO\PagedAuditLogResponse;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class ListAuditLogByUserHandler
{
    public function __construct(private readonly AuditLogRepositoryInterface $repository)
    {
    }

    public function handle(ListAuditLogByUserQuery $query): PagedAuditLogResponse
    {
        return $this->repository->findByActorId($query->userId, $query->page, $query->size);
    }
}
