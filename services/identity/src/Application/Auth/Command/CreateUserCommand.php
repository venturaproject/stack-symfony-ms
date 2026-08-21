<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateUserCommand
{
    /**
     * @param array<string>|null $roleIds
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
        public string $password,

        #[Assert\NotBlank]
        public string $firstName,

        #[Assert\NotBlank]
        public string $lastName,

        public ?array $roleIds = null,
    ) {
    }
}
