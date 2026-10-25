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
        /**
         * eventId of the integration event that caused this notification, if any —
         * used to deduplicate RabbitMQ redeliveries. Null for notifications created
         * outside the event pipeline (nothing to dedupe against).
         */
        public ?string $sourceEventId = null,
    ) {
    }
}
