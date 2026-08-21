<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Messaging;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\AmqpStamp;

/**
 * Publishes an integration event (a plain DTO from `IntegrationEvents\*`) to the
 * `domain_events` topic exchange under the given routing key. Backed by a dedicated
 * `event.bus` that every service routes entirely to its `amqp` transport — kept
 * separate from `command.bus`/`query.bus`, which stay synchronous/in-process.
 */
final class IntegrationEventPublisher
{
    public function __construct(
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function publish(object $event, string $routingKey): void
    {
        $this->eventBus->dispatch($event, [new AmqpStamp($routingKey)]);
    }
}
