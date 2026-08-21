<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The Security "user" for every non-Identity service — built entirely from a
 * verified JWT's own `roles` claim, never loaded from a database. The Identity
 * service's LoginHandler bakes the user's full effective roles+permissions into
 * the token at login time (Lexik's default payload builder calls User::getRoles()),
 * so the token alone is enough to authorize a request here.
 */
final class JwtUser implements UserInterface
{
    /** @param string[] $roles */
    public function __construct(
        private readonly string $email,
        private readonly array $roles,
        private readonly ?string $id = null,
    ) {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** The Identity user's own id (the `id` JWT claim — see JwtClaimsListener), not this service's own concept of identity. */
    public function getId(): ?string
    {
        return $this->id;
    }
}
