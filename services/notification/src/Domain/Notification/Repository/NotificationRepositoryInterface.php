<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repository;

use App\Domain\Notification\Entity\Notification;

interface NotificationRepositoryInterface
{
    public function findById(string $id): ?Notification;

    /** @return Notification[] */
    public function findForUser(string $userId): array;

    public function save(Notification $notification): Notification;

    public function delete(Notification $notification): void;

    public function deleteAllForUser(string $userId): void;

    public function markAllReadForUser(string $userId): void;
}
