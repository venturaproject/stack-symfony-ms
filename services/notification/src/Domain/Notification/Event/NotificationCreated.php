<?php

declare(strict_types=1);

namespace App\Domain\Notification\Event;

use SharedKernel\Domain\Shared\DomainEvent;

final class NotificationCreated extends DomainEvent
{
    public function __construct(
        public readonly ?string $notificationId,
        public readonly string $title,
        public readonly ?string $userId,
    ) {
        parent::__construct();
    }
}
