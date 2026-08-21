<?php

declare(strict_types=1);

namespace App\Application\Notification\Query;

use App\Application\Notification\DTO\NotificationResponse;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class ListNotificationsHandler
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications)
    {
    }

    /** @return NotificationResponse[] */
    public function handle(ListNotificationsQuery $query): array
    {
        return array_map(
            NotificationResponse::from(...),
            $this->notifications->findForUser($query->userId)
        );
    }
}
