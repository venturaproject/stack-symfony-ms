<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Auth\DTO\PermissionResponse;
use App\Domain\Auth\Repository\PermissionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class ListPermissionsHandler
{
    public function __construct(private readonly PermissionRepositoryInterface $permissions)
    {
    }

    /** @return PermissionResponse[] */
    public function handle(ListPermissionsQuery $query): array
    {
        return array_map(PermissionResponse::from(...), $this->permissions->findAll());
    }
}
