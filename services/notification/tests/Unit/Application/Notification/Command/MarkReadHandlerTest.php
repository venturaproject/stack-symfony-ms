<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Notification\Command;

use App\Application\Notification\Command\MarkReadCommand;
use App\Application\Notification\Command\MarkReadHandler;
use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Exception\NotificationNotFoundException;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Reference example of unit-testing an Application-layer CQRS handler: the
 * repository is a mocked interface, so this never boots the kernel or touches
 * a database — see tests/Functional/Http/HealthEndpointTest.php for a real,
 * end-to-end request instead.
 */
final class MarkReadHandlerTest extends TestCase
{
    public function testMarksOwnedNotificationAsRead(): void
    {
        $notification = $this->notificationFor('user-1');

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with('notif-1')->willReturn($notification);
        $repository->expects(self::once())->method('save')->with($notification);

        $response = (new MarkReadHandler($repository))->handle(new MarkReadCommand('notif-1', 'user-1'));

        self::assertTrue($notification->isRead());
        self::assertNotNull($response->readAt);
    }

    public function testThrowsNotFoundWhenNotificationDoesNotExist(): void
    {
        $repository = $this->createStub(NotificationRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(NotificationNotFoundException::class);

        (new MarkReadHandler($repository))->handle(new MarkReadCommand('missing', 'user-1'));
    }

    public function testThrowsNotFoundWhenNotificationBelongsToAnotherUser(): void
    {
        $notification = $this->notificationFor('user-1');

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->method('findById')->willReturn($notification);
        $repository->expects(self::never())->method('save');

        $this->expectException(NotificationNotFoundException::class);

        (new MarkReadHandler($repository))->handle(new MarkReadCommand('notif-1', 'someone-else'));
    }

    /** Notification::getId() is only ever populated by Doctrine — set it via reflection to fake a persisted row. */
    private function notificationFor(string $userId): Notification
    {
        $notification = new Notification();
        $notification->setTitle('Welcome');
        $notification->setMessage('Your account is ready.');
        $notification->setType('info');
        $notification->setUserId($userId);

        $id = new ReflectionProperty(Notification::class, 'id');
        $id->setValue($notification, 'notif-1');

        return $notification;
    }
}
