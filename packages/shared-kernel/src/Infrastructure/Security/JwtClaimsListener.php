<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class JwtClaimsListener
{
    public function __construct(private readonly string $appUrl)
    {
    }

    #[AsEventListener(event: Events::JWT_CREATED)]
    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $payload = $event->getData();
        $payload['iss'] = $this->appUrl;

        // Duck-typed on purpose: shared-kernel must not depend on any service's own
        // User entity. Identity's User has getId(); embedding it here is what lets
        // every other service (which has no User table of its own) scope records to
        // "the current user" from the token alone — see JwtUser.
        $user = $event->getUser();
        if (method_exists($user, 'getId')) {
            $payload['id'] = (string) $user->getId();
        }

        $event->setData($payload);
    }

    #[AsEventListener(event: Events::JWT_DECODED)]
    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        if (!isset($payload['iss']) || $payload['iss'] !== $this->appUrl) {
            $event->markAsInvalid();
        }
    }
}
