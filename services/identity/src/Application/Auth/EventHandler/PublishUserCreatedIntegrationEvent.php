<?php

declare(strict_types=1);

namespace App\Application\Auth\EventHandler;

use App\Domain\Auth\Event\UserCreated;
use IntegrationEvents\Identity\UserCreated as UserCreatedIntegrationEvent;
use SharedKernel\Application\Shared\Messaging\IntegrationEventPublisherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The cross-service counterpart of OnUserCreatedHandler in the monolith (which
 * dispatched a CreateNotificationCommand in-process). Here Identity and Notification
 * are separate processes, so this publishes onto the `domain_events` exchange instead —
 * Notification's own consumer creates the notification on the other side.
 */
final class PublishUserCreatedIntegrationEvent implements EventSubscriberInterface
{
    public function __construct(private readonly IntegrationEventPublisherInterface $publisher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [UserCreated::class => 'handle'];
    }

    public function handle(UserCreated $event): void
    {
        $this->publisher->publish(
            new UserCreatedIntegrationEvent(
                eventId: Uuid::v4()->toRfc4122(),
                userId: (string) $event->userId,
                email: $event->email,
            ),
        );
    }
}
