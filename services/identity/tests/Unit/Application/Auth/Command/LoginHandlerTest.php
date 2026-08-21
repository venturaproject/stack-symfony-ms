<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Auth\Command;

use App\Application\Auth\Command\LoginCommand;
use App\Application\Auth\Command\LoginHandler;
use App\Application\Auth\Command\LoginResult;
use App\Application\Shared\Totp\TotpServiceInterface;
use App\Domain\Auth\Entity\RefreshToken;
use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Event\UserLoggedIn;
use App\Domain\Auth\Repository\RefreshTokenRepositoryInterface;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use App\Domain\Settings\Entity\SecuritySettings;
use App\Domain\Settings\Repository\SecuritySettingsRepositoryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Reference example of unit-testing an Application-layer CQRS handler: every
 * collaborator is a mocked interface, so this never boots the kernel or touches
 * a database — see tests/Functional/Http/AuthLoginTest.php for the same flow
 * exercised end-to-end instead.
 */
final class LoginHandlerTest extends TestCase
{
    public function testSuccessfulLoginReturnsTokensAndDispatchesUserLoggedIn(): void
    {
        $user = $this->activeUser('jane@example.com');

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn($user);

        $refreshTokens = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokens->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(RefreshToken::class))
            ->willReturnArgument(0);

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects(self::once())->method('create')->with($user)->willReturn('signed.jwt.token');
        $jwtManager->expects(self::never())->method('createFromPayload');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturn(true);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn ($event) => $event instanceof UserLoggedIn && 'jane@example.com' === $event->email))
            ->willReturnArgument(0);

        $handler = new LoginHandler(
            $users,
            $refreshTokens,
            $jwtManager,
            $hasher,
            $dispatcher,
            $this->createStub(TotpServiceInterface::class),
            $this->securitySettingsRepository(),
        );

        $result = $handler->handle(new LoginCommand('jane@example.com', 'correct horse'));

        self::assertInstanceOf(LoginResult::class, $result);
        self::assertSame('signed.jwt.token', $result->accessToken);
        self::assertSame('signed.jwt.token', $result->auth->accessToken);
        self::assertSame('jane@example.com', $result->auth->user->email);
    }

    public function testUnknownEmailThrowsBadCredentials(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);

        $handler = new LoginHandler(
            $users,
            $this->createStub(RefreshTokenRepositoryInterface::class),
            $this->createStub(JWTTokenManagerInterface::class),
            $this->createStub(UserPasswordHasherInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(TotpServiceInterface::class),
            $this->securitySettingsRepository(),
        );

        $this->expectException(BadCredentialsException::class);

        $handler->handle(new LoginCommand('ghost@example.com', 'whatever'));
    }

    public function testWrongPasswordThrowsBadCredentials(): void
    {
        $user = $this->activeUser('jane@example.com');

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn($user);

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturn(false);

        $handler = new LoginHandler(
            $users,
            $this->createStub(RefreshTokenRepositoryInterface::class),
            $this->createStub(JWTTokenManagerInterface::class),
            $hasher,
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(TotpServiceInterface::class),
            $this->securitySettingsRepository(),
        );

        $this->expectException(BadCredentialsException::class);

        $handler->handle(new LoginCommand('jane@example.com', 'wrong-password'));
    }

    private function securitySettingsRepository(): SecuritySettingsRepositoryInterface
    {
        $repository = $this->createStub(SecuritySettingsRepositoryInterface::class);
        $repository->method('get')->willReturn(new SecuritySettings());

        return $repository;
    }

    /** User::getId() is only ever populated by Doctrine — set it via reflection to fake a persisted user. */
    private function activeUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $user->setPasswordHash('irrelevant-already-mocked-by-hasher');

        $id = new ReflectionProperty(User::class, 'id');
        $id->setValue($user, 'user-1');

        return $user;
    }
}
