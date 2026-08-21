<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Auth\Command\LoginCommand;
use App\Application\Auth\Command\LoginResult;
use App\Application\Auth\Command\LogoutCommand;
use App\Application\Auth\Command\RefreshTokenCommand;
use App\Application\Auth\Command\StopImpersonatingCommand;
use App\Application\Auth\Command\UpdateProfileCommand;
use App\Application\Auth\Command\UploadAvatarCommand;
use App\Application\Auth\DTO\MeResponse;
use App\Application\Auth\DTO\UserResponse;
use App\Domain\Auth\Entity\User;
use App\Domain\Auth\Exception\TotpRequiredException;
use App\Infrastructure\Http\Auth\AuthCookieFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use SharedKernel\Application\Shared\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly AuthCookieFactory $cookieFactory,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    /** @return array<string, mixed>|null */
    private function currentJwtPayload(): ?array
    {
        $token = $this->security->getToken();
        if (null === $token) {
            return null;
        }

        $payload = $this->jwtManager->decode($token);

        return \is_array($payload) ? $payload : null;
    }

    /**
     * The `impersonator_email` claim set by ImpersonateUserHandler, if the current
     * request is authenticated with an impersonation token — null otherwise.
     */
    private function impersonatorEmail(): ?string
    {
        $email = $this->currentJwtPayload()['impersonator_email'] ?? null;

        return \is_string($email) ? $email : null;
    }

    /**
     * The `must_setup_totp` claim set by LoginHandler when SecuritySettings.require2fa
     * is on and this user hasn't configured TOTP yet — see Require2faEnforcementListener,
     * which is what actually restricts the session; this just lets the frontend redirect
     * to the setup screen proactively instead of discovering it via a 403.
     */
    private function mustSetupTotp(): bool
    {
        return true === ($this->currentJwtPayload()['must_setup_totp'] ?? false);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(
        Request $request,
        #[Autowire(service: 'limiter.login_limiter')]
        RateLimiterFactory $loginLimiter,
    ): JsonResponse {
        $limiter = $loginLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['message' => 'Too many login attempts. Try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $cmd = $this->serializer->deserialize($request->getContent(), LoginCommand::class, 'json');
        $errors = $this->validator->validate($cmd);

        if (\count($errors) > 0) {
            return $this->json(['message' => 'Validation failed', 'errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            /** @var LoginResult $result */
            $result = $this->commandBus->dispatch($cmd);
        } catch (TotpRequiredException) {
            return $this->json(['totpRequired' => true], Response::HTTP_UNAUTHORIZED);
        }

        $response = $this->json($result->auth);
        $this->cookieFactory->setCookies($response, $result);

        return $response;
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(#[CurrentUser] User $user): JsonResponse
    {
        $this->commandBus->dispatch(new LogoutCommand($user->getId()));
        $response = $this->json(null, Response::HTTP_NO_CONTENT);
        $this->cookieFactory->clearCookies($response);

        return $response;
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        #[Autowire(service: 'limiter.refresh_token_limiter')]
        RateLimiterFactory $refreshTokenLimiter,
    ): JsonResponse {
        $limiter = $refreshTokenLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['message' => 'Too many requests. Try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $tokenValue = $request->cookies->get('refresh_token');
        if (null === $tokenValue) {
            return $this->json(['message' => 'No refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var LoginResult $result */
        $result = $this->commandBus->dispatch(new RefreshTokenCommand($tokenValue));

        $response = $this->json($result->auth);
        $this->cookieFactory->setCookies($response, $result);

        return $response;
    }

    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(new MeResponse(UserResponse::from($user), $this->impersonatorEmail(), $this->mustSetupTotp()));
    }

    #[Route('/stop-impersonating', methods: ['POST'])]
    public function stopImpersonating(): JsonResponse
    {
        $impersonatorEmail = $this->impersonatorEmail();
        if (null === $impersonatorEmail) {
            return $this->json(['message' => 'Not currently impersonating'], Response::HTTP_BAD_REQUEST);
        }

        /** @var LoginResult $result */
        $result = $this->commandBus->dispatch(new StopImpersonatingCommand($impersonatorEmail));

        $response = $this->json($result->auth);
        $this->cookieFactory->setCookies($response, $result);

        return $response;
    }

    #[Route('/me', methods: ['PATCH'])]
    public function updateMe(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $cmd = $this->serializer->deserialize($request->getContent(), UpdateProfileCommand::class, 'json');
        $cmd->userId = $user->getId();
        $errors = $this->validator->validate($cmd);

        if (\count($errors) > 0) {
            return $this->json(['message' => 'Validation failed'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->commandBus->dispatch($cmd));
    }

    #[Route('/me/avatar', methods: ['POST'])]
    public function uploadAvatar(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $file = $request->files->get('avatar');

        if (null === $file) {
            return $this->json(['message' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->commandBus->dispatch(
            new UploadAvatarCommand($user->getId(), $file->getPathname())
        ));
    }
}
