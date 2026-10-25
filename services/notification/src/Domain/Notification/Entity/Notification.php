<?php

declare(strict_types=1);

namespace App\Domain\Notification\Entity;

use App\Domain\Notification\Event\NotificationCreated;
use App\Domain\Notification\Exception\NotificationNotFoundException;
use DateTimeImmutable;
use LogicException;
use SharedKernel\Domain\Shared\HasDomainEvents;
use SharedKernel\Domain\Shared\RecordsDomainEvents;

class Notification implements HasDomainEvents
{
    use RecordsDomainEvents;

    private ?string $id = null;
    private string $title;
    private string $message;
    private string $type = 'info';
    private ?DateTimeImmutable $readAt = null;
    private DateTimeImmutable $createdAt;
    private ?string $userId = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /** @throws LogicException if called before this entity has been persisted */
    public function getIdOrFail(): string
    {
        return $this->id ?? throw new LogicException(self::class.' has no id yet — not persisted.');
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Throws (rather than returning bool) with the same "not found" exception a
     * missing row would use — callers shouldn't be able to distinguish "doesn't
     * exist" from "exists but isn't yours" any more than they could before this
     * was centralized out of MarkReadHandler/DeleteNotificationHandler.
     */
    public function assertOwnedBy(string $userId): void
    {
        if (null !== $this->userId && $this->userId !== $userId) {
            throw new NotificationNotFoundException();
        }
    }

    public function markAsRead(): void
    {
        if (null === $this->readAt) {
            $this->readAt = new DateTimeImmutable();
        }
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function recordCreated(): void
    {
        $this->raise(new NotificationCreated($this->id, $this->title, $this->userId));
    }
}
