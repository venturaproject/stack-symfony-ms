<?php

declare(strict_types=1);

namespace App\Application\ApiClient\Query;

use App\Application\ApiClient\DTO\ApiClientResponse;
use App\Domain\ApiClient\Repository\ApiClientRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class ListApiClientsHandler
{
    public function __construct(private readonly ApiClientRepositoryInterface $clients)
    {
    }

    /** @return ApiClientResponse[] */
    public function handle(ListApiClientsQuery $query): array
    {
        return array_map(ApiClientResponse::from(...), $this->clients->findAll());
    }
}
