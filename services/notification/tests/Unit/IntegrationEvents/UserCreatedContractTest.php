<?php

declare(strict_types=1);

namespace App\Tests\Unit\IntegrationEvents;

use IntegrationEvents\Identity\UserCreated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class UserCreatedContractTest extends TestCase
{
    public function testContractSurvivesJsonRoundTripWithStableScalarFields(): void
    {
        $event = new UserCreated('event-123', 'user-456', 'person@example.test');
        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        $payload = $serializer->serialize($event, 'json');

        self::assertSame(
            ['eventId' => 'event-123', 'userId' => 'user-456', 'email' => 'person@example.test'],
            json_decode($payload, true, flags: \JSON_THROW_ON_ERROR),
        );
        self::assertEquals($event, $serializer->deserialize($payload, UserCreated::class, 'json'));
    }
}
