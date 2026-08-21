<?php

declare(strict_types=1);

namespace App\Application\Auth\DTO;

final readonly class AuthResponse
{
    public function __construct(
        public string $accessToken,
        public UserResponse $user,
    ) {
    }
}
