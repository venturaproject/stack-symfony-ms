<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\DTO\UserResponse;
use App\Domain\Auth\Exception\PermissionNotFoundException;
use App\Domain\Auth\Exception\RoleNotFoundException;
use App\Domain\Auth\Exception\UserNotFoundException;
use App\Domain\Auth\Repository\PermissionRepositoryInterface;
use App\Domain\Auth\Repository\RefreshTokenRepositoryInterface;
use App\Domain\Auth\Repository\RoleRepositoryInterface;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class UpdateUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function handle(UpdateUserCommand $cmd): UserResponse
    {
        $user = $this->users->findById($cmd->id)
            ?? throw new UserNotFoundException();

        $user->setFirstName($cmd->firstName);
        $user->setLastName($cmd->lastName);

        $revokeRefreshTokens = false;

        if ($cmd->active !== $user->isActive()) {
            $cmd->active ? $user->activate() : $user->deactivate();
            $revokeRefreshTokens = true;
        }

        if (null !== $cmd->email && '' !== $cmd->email) {
            $user->changeEmail($cmd->email);
            $revokeRefreshTokens = true;
        }

        if (null !== $cmd->password && '' !== $cmd->password) {
            $user->changePassword($this->hasher->hashPassword($user, $cmd->password));
            $revokeRefreshTokens = true;
        }
        $user->setUpdatedAt(new DateTimeImmutable());

        if (null !== $cmd->roleIds) {
            $roleCollection = new ArrayCollection();
            foreach ($cmd->roleIds as $roleId) {
                $roleCollection->add(
                    $this->roles->findById($roleId)
                        ?? throw new RoleNotFoundException()
                );
            }
            $user->setRoles($roleCollection);
            $revokeRefreshTokens = true;
        }

        if (null !== $cmd->permissionIds) {
            $permCollection = new ArrayCollection();
            foreach ($cmd->permissionIds as $permId) {
                $permCollection->add(
                    $this->permissions->findById($permId)
                        ?? throw new PermissionNotFoundException()
                );
            }
            $user->setDirectPermissions($permCollection);
            $revokeRefreshTokens = true;
        }

        $user->recordUpdated();

        if ($revokeRefreshTokens) {
            $this->refreshTokens->revokeAllForUser($user);
        }

        return UserResponse::from($this->users->save($user));
    }
}
