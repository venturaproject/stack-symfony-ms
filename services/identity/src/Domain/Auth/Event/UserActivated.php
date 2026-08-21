<?php

declare(strict_types=1);

namespace App\Domain\Auth\Event;

use SharedKernel\Domain\Shared\DomainEvent;

final class UserActivated extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
    ) {
        parent::__construct();
    }
}
