<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Reference example of a Functional test: boots the real kernel and runs a
 * real HTTP request through the firewall against the real (test) database —
 * confirms the service actually boots end-to-end, not just that its classes
 * compile in isolation. See tests/Unit for handler-level examples instead.
 */
final class HealthEndpointTest extends WebTestCase
{
    public function testHealthEndpointIsPublicAndReportsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();

        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('ok', $body['status']);
        self::assertSame('ok', $body['checks']['database']);
    }
}
