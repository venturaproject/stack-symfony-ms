<?php

declare(strict_types=1);

namespace IntegrationEvents\Identity;

/**
 * Published by Identity to the `domain_events` exchange (routing key `identity.user_created`)
 * when a new user registers. Deliberately excludes the password — the monolith's in-process
 * welcome-email handler has direct access to the plaintext at creation time and stays local to
 * Identity; nothing sensitive crosses the broker.
 */
final readonly class UserCreated
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {
    }
}
