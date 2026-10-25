<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entity;

use DateTimeImmutable;
use LogicException;

class Permission
{
    private ?string $id = null;
    private string $name;
    private ?string $description = null;
    private DateTimeImmutable $createdAt;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
