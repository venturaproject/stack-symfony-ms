<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entity;

use App\Domain\Auth\Event\RoleCreated;
use App\Domain\Auth\Event\RolePermissionsChanged;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LogicException;
use SharedKernel\Domain\Shared\HasDomainEvents;
use SharedKernel\Domain\Shared\RecordsDomainEvents;

class Role implements HasDomainEvents
{
    use RecordsDomainEvents;

    private ?string $id = null;
    private string $name;
    private ?string $description = null;
    /** @var Collection<int, Permission> */
    private Collection $permissions;
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
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

    /** @return Collection<int, Permission> */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): void
    {
        $this->permissions->add($permission);
    }

    /** @param Collection<int, Permission> $permissions */
    public function setPermissions(Collection $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function recordCreated(): void
    {
        $this->raise(new RoleCreated($this->id, $this->name));
    }

    public function recordPermissionsChanged(): void
    {
        $names = $this->permissions->map(static fn (Permission $p) => $p->getName())->toArray();
        $this->raise(new RolePermissionsChanged($this->getIdOrFail(), $this->name, $names));
    }
}
