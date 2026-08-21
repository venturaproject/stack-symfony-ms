<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\DTO\AuthResponse;
use App\Application\Auth\DTO\UserResponse;
use App\Domain\Auth\Entity\RefreshToken;
use App\Domain\Auth\Repository\RefreshTokenRepositoryInterface;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class RefreshTokenHandler
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly int $refreshTtlSecs = 604800,
    ) {
    }

    public function handle(RefreshTokenCommand $cmd): LoginResult
    {
        $token = $this->refreshTokens->findByToken(hash('sha256', $cmd->tokenValue));

        if (null === $token || !$token->isValid() || !$token->getUser()->isActive()) {
            throw new BadCredentialsException('Invalid or expired refresh token');
        }

        $user = $token->getUser();
        $accessToken = $this->jwtManager->create($user);

        $newValue = bin2hex(random_bytes(40));
        $expiresAt = new DateTimeImmutable("+{$this->refreshTtlSecs} seconds");

        $token->revoke();
        $this->refreshTokens->save(new RefreshToken(hash('sha256', $newValue), $user, $expiresAt));

        return new LoginResult(
            accessToken: $accessToken,
            refreshToken: $newValue,
            refreshExpires: $expiresAt,
            auth: new AuthResponse(accessToken: $accessToken, user: UserResponse::from($user)),
        );
    }
}
