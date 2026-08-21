<?php

declare(strict_types=1);

namespace App\Application\Auth\EventHandler;

use App\Domain\Auth\Event\UserCreated;
use IntegrationEvents\Identity\UserCreated as UserCreatedIntegrationEvent;
use SharedKernel\Infrastructure\Messaging\IntegrationEventPublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The cross-service counterpart of OnUserCreatedHandler in the monolith (which
 * dispatched a CreateNotificationCommand in-process). Here Identity and Notification
 * are separate processes, so this publishes onto the `domain_events` exchange instead —
 * Notification's own consumer creates the notification on the other side.
 */
final class PublishUserCreatedIntegrationEvent implements EventSubscriberInterface
{
    public function __construct(private readonly IntegrationEventPublisher $publisher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [UserCreated::class => 'handle'];
    }

    public function handle(UserCreated $event): void
    {
        $this->publisher->publish(
            new UserCreatedIntegrationEvent(userId: (string) $event->userId, email: $event->email),
            'identity.user_created',
        );
    }
}
