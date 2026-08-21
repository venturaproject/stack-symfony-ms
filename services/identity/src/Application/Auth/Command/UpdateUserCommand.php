<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateUserCommand
{
    public string $id = '';

    #[Assert\NotBlank]
    public string $firstName = '';

    public string $lastName = '';

    #[Assert\Email]
    public ?string $email = null;

    public ?string $password = null;

    public bool $active = true;

    /** @var array<string>|null */
    public ?array $roleIds = null;

    /** @var array<string>|null */
    public ?array $permissionIds = null;
}
