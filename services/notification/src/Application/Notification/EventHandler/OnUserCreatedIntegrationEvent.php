<?php

declare(strict_types=1);

namespace App\Application\Notification\EventHandler;

use App\Application\Notification\Command\CreateNotificationCommand;
use IntegrationEvents\Identity\UserCreated;
use SharedKernel\Application\Shared\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consumes `identity.user_created` off RabbitMQ — the cross-process counterpart of
 * the monolith's in-process OnUserCreatedHandler. Registered on `event.bus` (not
 * command.bus/query.bus) because that's the bus name the message carries from the
 * publishing side (Identity's IntegrationEventPublisher); Messenger's consume worker
 * routes an incoming message back through the bus name stamped on it at publish time.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class OnUserCreatedIntegrationEvent
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    public function __invoke(UserCreated $event): void
    {
        $this->commandBus->dispatch(new CreateNotificationCommand(
            title: 'New user registered',
            message: "The user {$event->email} has been created.",
            type: 'success',
            userId: null,
        ));
    }
}
