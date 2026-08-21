<?php

declare(strict_types=1);

namespace App\Application\Notification\Command;

use App\Application\Notification\DTO\NotificationResponse;
use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class CreateNotificationHandler
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications)
    {
    }

    public function handle(CreateNotificationCommand $cmd): NotificationResponse
    {
        $n = new Notification();
        $n->setTitle($cmd->title);
        $n->setMessage($cmd->message);
        $n->setType($cmd->type);
        $n->setUserId($cmd->userId);
        $n->recordCreated();

        return NotificationResponse::from($this->notifications->save($n));
    }
}
