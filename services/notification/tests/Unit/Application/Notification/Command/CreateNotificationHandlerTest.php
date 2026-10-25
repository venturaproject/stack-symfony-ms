<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Notification\Command;

use App\Application\Notification\Command\CreateNotificationCommand;
use App\Application\Notification\Command\CreateNotificationHandler;
use App\Application\Notification\Repository\ProcessedIntegrationEventRepositoryInterface;
use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers the idempotency guard described in CreateNotificationHandler: RabbitMQ is
 * at-least-once, so a redelivered UserCreated (same eventId) must not create a
 * second notification.
 */
final class CreateNotificationHandlerTest extends TestCase
{
    public function testCreatesNotificationAndRecordsTheSourceEvent(): void
    {
        $saved = $this->notificationWithId('notif-1');

        $notifications = $this->createMock(NotificationRepositoryInterface::class);
        $notifications->expects(self::once())->method('save')->willReturn($saved);

        $processedEvents = $this->createMock(ProcessedIntegrationEventRepositoryInterface::class);
        $processedEvents->method('isProcessed')->with('event-1')->willReturn(false);
        $processedEvents->expects(self::once())->method('markProcessed')->with('event-1');

        $response = (new CreateNotificationHandler($notifications, $processedEvents))->handle(
            new CreateNotificationCommand('New user registered', 'msg', 'success', null, sourceEventId: 'event-1'),
        );

        self::assertNotNull($response);
        self::assertSame('notif-1', $response->id);
    }

    public function testSkipsAlreadyProcessedEventWithoutTouchingNotifications(): void
    {
        $notifications = $this->createMock(NotificationRepositoryInterface::class);
        $notifications->expects(self::never())->method('save');

        $processedEvents = $this->createMock(ProcessedIntegrationEventRepositoryInterface::class);
        $processedEvents->method('isProcessed')->with('event-1')->willReturn(true);

        $response = (new CreateNotificationHandler($notifications, $processedEvents))->handle(
            new CreateNotificationCommand('New user registered', 'msg', 'success', null, sourceEventId: 'event-1'),
        );

        self::assertNull($response);
    }

    public function testSkipsDedupWhenCommandHasNoSourceEvent(): void
    {
        $saved = $this->notificationWithId('notif-2');

        $notifications = $this->createMock(NotificationRepositoryInterface::class);
        $notifications->expects(self::once())->method('save')->willReturn($saved);

        $processedEvents = $this->createMock(ProcessedIntegrationEventRepositoryInterface::class);
        $processedEvents->expects(self::never())->method('isProcessed');
        $processedEvents->expects(self::never())->method('markProcessed');

        $response = (new CreateNotificationHandler($notifications, $processedEvents))->handle(
            new CreateNotificationCommand('Manual notice', 'msg', 'info', null),
        );

        self::assertNotNull($response);
    }

    /** Notification::getId() is only ever populated by Doctrine — set it via reflection to fake a persisted row. */
    private function notificationWithId(string $id): Notification
    {
        $notification = new Notification();
        $notification->setTitle('New user registered');
        $notification->setMessage('msg');
        $notification->setType('success');

        $idProperty = new ReflectionProperty(Notification::class, 'id');
        $idProperty->setValue($notification, $id);

        return $notification;
    }
}
