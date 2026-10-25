<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateRoleCommand
{
    #[Assert\NotBlank]
    public string $name = '';

    public ?string $description = null;
}
