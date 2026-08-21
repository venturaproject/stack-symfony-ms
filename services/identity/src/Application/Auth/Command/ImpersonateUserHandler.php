<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\DTO\AuthResponse;
use App\Application\Auth\DTO\UserResponse;
use App\Domain\Auth\Event\UserImpersonated;
use App\Domain\Auth\Exception\CannotImpersonateException;
use App\Domain\Auth\Exception\UserNotFoundException;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class ImpersonateUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function handle(ImpersonateUserCommand $cmd): AuthResponse
    {
        $target = $this->users->findById($cmd->targetUserId)
            ?? throw new UserNotFoundException();

        if (!$target->isActive()) {
            throw CannotImpersonateException::inactiveUser();
        }

        if (0 === strcasecmp($target->getUserIdentifier(), $cmd->impersonatorEmail)) {
            throw CannotImpersonateException::own();
        }

        // The extra claim is what StopImpersonatingHandler reads to hand control back
        // to the real admin — see AuthController::stopImpersonating().
        $accessToken = $this->jwtManager->createFromPayload($target, [
            'impersonator_email' => $cmd->impersonatorEmail,
        ]);

        $this->dispatcher->dispatch(new UserImpersonated(
            $target->getId(),
            $target->getUserIdentifier(),
            $cmd->impersonatorEmail,
        ));

        return new AuthResponse(accessToken: $accessToken, user: UserResponse::from($target));
    }
}
