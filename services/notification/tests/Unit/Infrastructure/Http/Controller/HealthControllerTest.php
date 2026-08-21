<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Controller;

use App\Infrastructure\Http\Controller\HealthController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends TestCase
{
    public function testReturnsOkWhenDatabaseIsReachable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeQuery')->with('SELECT 1');

        $response = (new HealthController($connection))();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['status' => 'ok', 'checks' => ['database' => 'ok']],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testReturnsServiceUnavailableWhenDatabaseIsDown(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(new RuntimeException('connection refused'));

        $response = (new HealthController($connection))();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame(
            ['status' => 'error', 'checks' => ['database' => 'error']],
            json_decode((string) $response->getContent(), true),
        );
    }
}
