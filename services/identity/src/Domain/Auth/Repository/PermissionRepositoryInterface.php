<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repository;

use App\Domain\Auth\Entity\Permission;

interface PermissionRepositoryInterface
{
    public function findById(string $id): ?Permission;

    public function findByName(string $name): ?Permission;

    /** @return Permission[] */
    public function findAll(): array;

    public function save(Permission $permission): Permission;

    public function delete(Permission $permission): void;
}
