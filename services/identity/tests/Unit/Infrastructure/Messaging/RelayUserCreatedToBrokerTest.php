<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Messaging;

use App\Infrastructure\Messaging\RelayUserCreatedToBroker;
use IntegrationEvents\Identity\UserCreated;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Regression test: an earlier version of this relay built the outgoing Envelope with
 * only an AmqpStamp, dropping the BusNameStamp. Without it, a consumer with no
 * BusNameStamp on the envelope falls back to its default bus (command.bus) instead of
 * event.bus, so OnUserCreatedIntegrationEvent — registered on event.bus — never
 * matched and every delivery failed with "No handler for message".
 */
final class RelayUserCreatedToBrokerTest extends TestCase
{
    public function testSendsWithRoutingKeyAndBusNameStamps(): void
    {
        if (!\defined('AMQP_NOPARAM')) {
            self::markTestSkipped('The AMQP PHP extension is required to instantiate AmqpStamp.');
        }

        $event = new UserCreated(eventId: 'event-1', userId: 'user-1', email: 'a@b.com');

        $amqp = $this->createMock(TransportInterface::class);
        $amqp->expects(self::once())->method('send')->with(new Callback(
            static function (Envelope $envelope) use ($event): bool {
                self::assertSame($event, $envelope->getMessage());

                $amqpStamp = $envelope->last(AmqpStamp::class);
                self::assertInstanceOf(AmqpStamp::class, $amqpStamp);
                self::assertSame('identity.user_created', $amqpStamp->getRoutingKey());

                $busNameStamp = $envelope->last(BusNameStamp::class);
                self::assertInstanceOf(BusNameStamp::class, $busNameStamp);
                self::assertSame('event.bus', $busNameStamp->getBusName());

                return true;
            }
        ))->willReturn(new Envelope($event));

        (new RelayUserCreatedToBroker($amqp))($event);
    }

    public function testPropagatesBrokerFailureSoMessengerCanRetry(): void
    {
        if (!\defined('AMQP_NOPARAM')) {
            self::markTestSkipped('The AMQP PHP extension is required to instantiate AmqpStamp.');
        }

        $amqp = $this->createMock(TransportInterface::class);
        $amqp->expects(self::once())
            ->method('send')
            ->willThrowException(new RuntimeException('broker unavailable'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('broker unavailable');

        (new RelayUserCreatedToBroker($amqp))(
            new UserCreated(eventId: 'event-1', userId: 'user-1', email: 'a@b.com'),
        );
    }
}
