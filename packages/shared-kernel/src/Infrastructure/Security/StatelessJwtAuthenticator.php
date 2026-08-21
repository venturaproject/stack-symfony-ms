<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Verifies a JWT (RS256, public key only — configured with no `secret_key` in this
 * service's lexik_jwt_authentication.yaml) and builds the Security user directly
 * from the token's own `roles` claim. No UserProviderInterface, no database lookup:
 * this service never holds a copy of the User table, and stays authenticatable even
 * if the Identity service is down (see JwtUser's docblock for why the token alone
 * is enough).
 */
final class StatelessJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly JWTTokenManagerInterface $jwtManager)
    {
    }

    public function supports(Request $request): ?bool
    {
        return null !== $this->extractToken($request);
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request)
            ?? throw new CustomUserMessageAuthenticationException('No JWT token found.');

        try {
            $payload = $this->jwtManager->parse($token);
        } catch (JWTDecodeFailureException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT token.', [], 0, $e);
        }

        $email = $payload['email'] ?? $payload['username'] ?? null;
        if (null === $email) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT payload.');
        }

        $roles = $payload['roles'] ?? [];
        $id = $payload['id'] ?? null;

        return new SelfValidatingPassport(new UserBadge($email, static fn () => new JwtUser($email, $roles, $id)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (null !== $header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->cookies->get('access_token');
    }
}
