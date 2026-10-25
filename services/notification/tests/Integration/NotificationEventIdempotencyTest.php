<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Notification\Command\CreateNotificationCommand;
use App\Application\Notification\Command\CreateNotificationHandler;
use App\Infrastructure\Persistence\Doctrine\Entity\ProcessedIntegrationEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NotificationEventIdempotencyTest extends KernelTestCase
{
    public function testDuplicateIntegrationEventCreatesOneNotificationAndOneLedgerEntry(): void
    {
        self::bootKernel();

        $handler = static::getContainer()->get(CreateNotificationHandler::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $command = new CreateNotificationCommand(
            title: 'New user registered',
            message: 'A user was created.',
            type: 'success',
            userId: null,
            sourceEventId: 'integration-event-duplicate-test',
        );

        self::assertNotNull($handler->handle($command));
        self::assertNull($handler->handle($command));

        self::assertSame(1, (int) $entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM notifications WHERE message = :message',
            ['message' => 'A user was created.'],
        ));
        self::assertSame(1, (int) $entityManager->getRepository(ProcessedIntegrationEvent::class)->count([
            'eventId' => 'integration-event-duplicate-test',
        ]));
    }
}
