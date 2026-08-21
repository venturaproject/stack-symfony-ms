<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Auth\DTO\RoleResponse;
use App\Domain\Auth\Exception\RoleNotFoundException;
use App\Domain\Auth\Repository\RoleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class GetRoleHandler
{
    public function __construct(private readonly RoleRepositoryInterface $roles)
    {
    }

    public function handle(GetRoleQuery $query): RoleResponse
    {
        return RoleResponse::from(
            $this->roles->findById($query->id) ?? throw new RoleNotFoundException()
        );
    }
}
