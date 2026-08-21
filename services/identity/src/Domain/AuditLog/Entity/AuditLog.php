<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Entity;

use DateTimeImmutable;

class AuditLog
{
    public function __construct(
        private string $id,
        private ?string $actorId,
        private ?string $actorEmail,
        private string $action,
        private string $resourceType,
        private ?string $resourceId,
        /** @var array<string, mixed> */
        private array $metadata,
        private ?string $ipAddress,
        private string $previousHash,
        private string $hash,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getPreviousHash(): string
    {
        return $this->previousHash;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
