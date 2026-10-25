<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\DTO\AuthResponse;
use App\Application\Auth\DTO\UserResponse;
use App\Application\Shared\Totp\TotpServiceInterface;
use App\Domain\Auth\Entity\RefreshToken;
use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Event\UserLoggedIn;
use App\Domain\Auth\Exception\TotpRequiredException;
use App\Domain\Auth\Repository\RefreshTokenRepositoryInterface;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use App\Domain\Settings\Repository\SecuritySettingsRepositoryInterface;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class LoginHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly TotpServiceInterface $totp,
        private readonly SecuritySettingsRepositoryInterface $securitySettings,
        private readonly int $refreshTtlSecs = 604800,
    ) {
    }

    public function handle(LoginCommand $cmd): LoginResult
    {
        $user = $this->users->findByEmail($cmd->email)
            ?? throw new BadCredentialsException();

        if (!$this->hasher->isPasswordValid($user, $cmd->password) || !$user->isActive()) {
            throw new BadCredentialsException();
        }

        $extraClaims = [];

        if ($user->isTotpEnabled()) {
            $this->verifySecondFactor($user, $cmd);
        } elseif ($this->securitySettings->get()->isRequire2fa()) {
            // Real session, but scoped down to the TOTP setup endpoints only — see
            // Require2faEnforcementListener — until they complete setup.
            $extraClaims['must_setup_totp'] = true;
        }

        $accessToken = [] === $extraClaims
            ? $this->jwtManager->create($user)
            : $this->jwtManager->createFromPayload($user, $extraClaims);
        $refreshValue = bin2hex(random_bytes(40));
        $expiresAt = new DateTimeImmutable("+{$this->refreshTtlSecs} seconds");

        $this->refreshTokens->save(new RefreshToken(hash('sha256', $refreshValue), $user, $expiresAt));

        $this->dispatcher->dispatch(new UserLoggedIn($user->getIdOrFail(), $user->getUserIdentifier()));

        return new LoginResult(
            accessToken: $accessToken,
            refreshToken: $refreshValue,
            refreshExpires: $expiresAt,
            auth: new AuthResponse(accessToken: $accessToken, user: UserResponse::from($user)),
        );
    }

    private function verifySecondFactor(User $user, LoginCommand $cmd): void
    {
        if (null === $cmd->totpCode && null === $cmd->backupCode) {
            throw new TotpRequiredException();
        }

        if (null !== $cmd->totpCode && $this->totp->verify($user->getTotpSecret() ?? '', $cmd->totpCode)) {
            return;
        }

        if (null !== $cmd->backupCode && $user->consumeBackupCode(hash('sha256', $cmd->backupCode))) {
            $this->users->save($user);

            return;
        }

        throw new BadCredentialsException();
    }
}
