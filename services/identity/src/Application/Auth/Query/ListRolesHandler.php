<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Auth\DTO\RoleResponse;
use App\Domain\Auth\Repository\RoleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class ListRolesHandler
{
    public function __construct(private readonly RoleRepositoryInterface $roles)
    {
    }

    /** @return RoleResponse[] */
    public function handle(ListRolesQuery $query): array
    {
        return array_map(RoleResponse::from(...), $this->roles->findAll());
    }
}
