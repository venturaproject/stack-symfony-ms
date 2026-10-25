<?php

declare(strict_types=1);

namespace App\Application\Notification\Command;

use App\Application\Notification\DTO\NotificationResponse;
use App\Application\Notification\Repository\ProcessedIntegrationEventRepositoryInterface;
use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class CreateNotificationHandler
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly ProcessedIntegrationEventRepositoryInterface $processedEvents,
    ) {
    }

    public function handle(CreateNotificationCommand $cmd): ?NotificationResponse
    {
        // RabbitMQ is at-least-once: a prior delivery of the same integration event may
        // already have created this notification. Checked here (not in the event
        // handler) so the check-and-record happens in the same transaction as the
        // Notification insert — see markProcessed()'s doc comment.
        if (null !== $cmd->sourceEventId && $this->processedEvents->isProcessed($cmd->sourceEventId)) {
            return null;
        }

        $n = new Notification();
        $n->setTitle($cmd->title);
        $n->setMessage($cmd->message);
        $n->setType($cmd->type);
        $n->setUserId($cmd->userId);
        $n->recordCreated();

        if (null !== $cmd->sourceEventId) {
            $this->processedEvents->markProcessed($cmd->sourceEventId);
        }

        try {
            return NotificationResponse::from($this->notifications->save($n));
        } catch (UniqueConstraintViolationException) {
            // Two redeliveries raced past the isProcessed() check above; the other one
            // won and already committed. The event_id primary key is the real guarantee
            // here — this is just the friendly way to lose the race.
            return null;
        }
    }
}
