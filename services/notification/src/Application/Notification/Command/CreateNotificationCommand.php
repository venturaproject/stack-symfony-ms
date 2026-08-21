<?php

declare(strict_types=1);

namespace App\Application\Notification\Command;

final readonly class CreateNotificationCommand
{
    public function __construct(
        public string $title,
        public string $message,
        public string $type,
        public ?string $userId,
    ) {
    }
}
