<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Messaging;

use SharedKernel\Application\Shared\Messaging\IntegrationEventPublisherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Publishes an integration event (a plain DTO from `IntegrationEvents\*`) onto the
 * transactional outbox — a dedicated `event.bus` that each publishing service routes
 * entirely to its own local `async` (Doctrine) transport, per messenger.php's routing.
 * That transport shares the DB connection with the entity manager, so the outbox row
 * commits atomically with whatever domain write raised the event: either both happen
 * or neither does. Never dispatched straight to `amqp` — a dedicated per-event relay
 * handler (e.g. RelayUserCreatedToBroker), consumed from the outbox by the service's
 * worker, is what actually forwards it to the real `domain_events` exchange, retrying
 * independently of this request if the broker is briefly unavailable. Kept separate
 * from `command.bus`/`query.bus`, which stay synchronous/in-process.
 */
final class IntegrationEventPublisher implements IntegrationEventPublisherInterface
{
    public function __construct(
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function publish(object $event): void
    {
        $this->eventBus->dispatch($event);
    }
}
