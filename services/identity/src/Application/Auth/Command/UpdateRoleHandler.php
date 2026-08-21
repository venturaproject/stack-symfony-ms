<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\DTO\RoleResponse;
use App\Domain\Auth\Exception\RoleNotFoundException;
use App\Domain\Auth\Repository\RoleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class UpdateRoleHandler
{
    public function __construct(private readonly RoleRepositoryInterface $roles)
    {
    }

    public function handle(UpdateRoleCommand $cmd): RoleResponse
    {
        $role = $this->roles->findById($cmd->id)
            ?? throw new RoleNotFoundException();

        $role->setName($cmd->name);
        $role->setDescription($cmd->description);

        return RoleResponse::from($this->roles->save($role));
    }
}
