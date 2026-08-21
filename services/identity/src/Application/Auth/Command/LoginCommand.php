<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginCommand
{
    public function __construct(
        #[Assert\NotBlank]
        public string $email,

        #[Assert\NotBlank]
        public string $password,

        /** Required on the second call to login() once totpRequired has been signaled. */
        public ?string $totpCode = null,
        public ?string $backupCode = null,
    ) {
    }
}
