<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\DTO\RoleResponse;
use App\Domain\Auth\Entity\Role;
use App\Domain\Auth\Exception\RoleAlreadyExistsException;
use App\Domain\Auth\Repository\RoleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class CreateRoleHandler
{
    public function __construct(private readonly RoleRepositoryInterface $roles)
    {
    }

    public function handle(CreateRoleCommand $cmd): RoleResponse
    {
        if (null !== $this->roles->findByName($cmd->name)) {
            throw new RoleAlreadyExistsException($cmd->name);
        }

        $role = new Role();
        $role->setName($cmd->name);
        $role->setDescription($cmd->description);
        $role->recordCreated();

        return RoleResponse::from($this->roles->save($role));
    }
}
