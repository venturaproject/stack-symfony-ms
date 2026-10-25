<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateRoleCommand
{
    /** Set from the route path param, not the request body — see RoleController::update(). */
    public string $id = '';

    #[Assert\NotBlank]
    public string $name = '';

    public ?string $description = null;
}
