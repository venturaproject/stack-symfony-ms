<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\DTO\UserResponse;
use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Exception\EmailAlreadyInUseException;
use App\Domain\Auth\Exception\RoleNotFoundException;
use App\Domain\Auth\Repository\RoleRepositoryInterface;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class CreateUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function handle(CreateUserCommand $cmd): UserResponse
    {
        if ($this->users->existsByEmail($cmd->email)) {
            throw new EmailAlreadyInUseException();
        }

        $user = new User();
        $user->setEmail($cmd->email);
        $user->setPasswordHash($this->hasher->hashPassword($user, $cmd->password));
        $user->setFirstName($cmd->firstName);
        $user->setLastName($cmd->lastName);

        if (null !== $cmd->roleIds) {
            $roleCollection = new ArrayCollection();
            foreach ($cmd->roleIds as $roleId) {
                $roleCollection->add(
                    $this->roles->findById($roleId)
                        ?? throw new RoleNotFoundException()
                );
            }
            $user->setRoles($roleCollection);
        }

        $user->recordCreated();

        return UserResponse::from($this->users->save($user));
    }
}
