<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Auth\Command\ConfirmTotpSetupCommand;
use App\Application\Auth\Command\DisableTotpCommand;
use App\Application\Auth\Command\SetUpTotpCommand;
use App\Domain\Auth\Entity\User;
use App\Infrastructure\Http\Auth\AuthCookieFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use SharedKernel\Application\Shared\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth/totp')]
class TotpController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly AuthCookieFactory $cookieFactory,
    ) {
    }

    #[Route('/setup', methods: ['POST'])]
    public function setup(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->commandBus->dispatch(new SetUpTotpCommand($user->getId() ?? '')));
    }

    #[Route('/confirm', methods: ['POST'])]
    public function confirm(
        #[CurrentUser] User $user,
        Request $request,
        #[Autowire(service: 'limiter.totp_confirm_limiter')]
        RateLimiterFactory $totpConfirmLimiter,
    ): JsonResponse {
        $limiter = $totpConfirmLimiter->create($user->getId() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['message' => 'Too many attempts. Try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        $code = \is_array($data) && \is_string($data['code'] ?? null) ? $data['code'] : '';

        if ('' === $code) {
            return $this->json(['message' => 'Validation failed'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->commandBus->dispatch(new ConfirmTotpSetupCommand($user->getId() ?? '', $code));

        // The access_token cookie set at login may still carry `must_setup_totp: true`
        // (JWT claims are immutable once issued) — mint a fresh one now that setup is
        // confirmed, so the mandatory-setup redirect doesn't fire again on this same
        // session (see Require2faEnforcementListener / the frontend's requiredStep guard).
        $response = $this->json($result);
        $this->cookieFactory->refreshAccessTokenCookie($response, $this->jwtManager->create($user));

        return $response;
    }

    #[Route('/disable', methods: ['POST'])]
    public function disable(
        #[CurrentUser] User $user,
        Request $request,
        #[Autowire(service: 'limiter.totp_disable_limiter')]
        RateLimiterFactory $totpDisableLimiter,
    ): JsonResponse {
        $limiter = $totpDisableLimiter->create($user->getId() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['message' => 'Too many attempts. Try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        $password = \is_array($data) && \is_string($data['password'] ?? null) ? $data['password'] : '';

        if ('' === $password) {
            return $this->json(['message' => 'Validation failed'], Response::HTTP_BAD_REQUEST);
        }

        $this->commandBus->dispatch(new DisableTotpCommand($user->getId() ?? '', $password));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
