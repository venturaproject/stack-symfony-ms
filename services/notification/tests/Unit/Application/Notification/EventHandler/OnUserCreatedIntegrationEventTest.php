<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Notification\EventHandler;

use App\Application\Notification\Command\CreateNotificationCommand;
use App\Application\Notification\EventHandler\OnUserCreatedIntegrationEvent;
use IntegrationEvents\Identity\UserCreated;
use PHPUnit\Framework\TestCase;
use SharedKernel\Application\Shared\Bus\CommandBusInterface;

final class OnUserCreatedIntegrationEventTest extends TestCase
{
    public function testMapsWireContractToLocalCommandIncludingIdempotencyKey(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(
            static function (object $command): bool {
                self::assertInstanceOf(CreateNotificationCommand::class, $command);
                self::assertSame('event-123', $command->sourceEventId);
                self::assertNull($command->userId, 'Welcome notifications are broadcast to all users.');
                self::assertSame('The user person@example.test has been created.', $command->message);

                return true;
            },
        ));

        (new OnUserCreatedIntegrationEvent($bus))(
            new UserCreated('event-123', 'user-456', 'person@example.test'),
        );
    }
}
