<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use IntegrationEvents\Identity\UserCreated;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Second half of the transactional outbox: PublishUserCreatedIntegrationEvent only ever
 * writes UserCreated to the local `async` transport (see messenger.php's routing), which
 * is what the identity-worker's `messenger:consume async` actually invokes handlers for.
 * This is that handler — it forwards the event to the real `domain_events` exchange with
 * its routing key. If RabbitMQ is down, sending here throws, Messenger retries per
 * `async`'s retry strategy, and after exhausting retries the message lands in the
 * `failed` transport instead of being silently dropped. Unlike publishing straight to
 * `amqp` at dispatch time, a broker outage here can never lose the event or block
 * whatever request raised it in the first place.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class RelayUserCreatedToBroker
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.amqp')] private readonly TransportInterface $amqp,
    ) {
    }

    public function __invoke(UserCreated $event): void
    {
        // Bypassing $eventBus->dispatch() here (straight to the transport's send())
        // skips SendMessageMiddleware, which is what normally stamps BusNameStamp on
        // its way out. Without it, a consumer with no BusNameStamp on the envelope
        // falls back to its default bus (command.bus here) instead of event.bus,
        // and OnUserCreatedIntegrationEvent — registered on event.bus — silently
        // never matches. Stamped explicitly to name the bus the receiving side must
        // dispatch onto.
        $this->amqp->send(new Envelope($event, [
            new AmqpStamp('identity.user_created'),
            new BusNameStamp('event.bus'),
        ]));
    }
}
