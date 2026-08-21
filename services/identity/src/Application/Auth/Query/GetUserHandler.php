<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Auth\DTO\UserResponse;
use App\Domain\Auth\Exception\UserNotFoundException;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class GetUserHandler
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(GetUserQuery $query): UserResponse
    {
        return UserResponse::from(
            $this->users->findById($query->id) ?? throw new UserNotFoundException()
        );
    }
}
