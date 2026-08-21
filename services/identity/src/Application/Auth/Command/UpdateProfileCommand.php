<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateProfileCommand
{
    public string $userId = '';

    #[Assert\NotBlank]
    public string $firstName = '';

    #[Assert\NotBlank]
    public string $lastName = '';

    #[Assert\Choice(choices: ['en', 'es'])]
    public ?string $preferredLanguage = null;
}
