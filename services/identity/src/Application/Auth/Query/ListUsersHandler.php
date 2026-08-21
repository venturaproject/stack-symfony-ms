<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Auth\DTO\UserResponse;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use SharedKernel\Application\Shared\DTO\PagedResponse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class ListUsersHandler
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /** @return PagedResponse<mixed> */
    public function handle(ListUsersQuery $query): PagedResponse
    {
        $items = $this->users->findPaginated($query->page, $query->size);
        $total = $this->users->countAll();

        return PagedResponse::of($items, $query->page, $query->size, $total, UserResponse::from(...));
    }
}
