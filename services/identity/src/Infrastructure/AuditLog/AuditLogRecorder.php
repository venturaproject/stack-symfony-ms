<?php

declare(strict_types=1);

namespace App\Infrastructure\AuditLog;

use App\Application\AuditLog\Repository\AuditLogRepositoryInterface;
use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\Auth\Entity\User;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

final class AuditLogRecorder
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $repository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        array $metadata = [],
        ?string $explicitActorId = null,
        ?string $explicitActorEmail = null,
    ): void {
        [$actorId, $actorEmail] = $this->resolveActor($explicitActorId, $explicitActorEmail);

        $id = Uuid::v7()->toRfc4122();
        $createdAt = new DateTimeImmutable();
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();
        $previousHash = $this->repository->getLastHash();

        $hash = $this->computeHash(
            $id, $actorId, $actorEmail, $action, $resourceType,
            $resourceId, $metadata, $ip, $createdAt, $previousHash,
        );

        $this->repository->save(new AuditLog(
            id: $id,
            actorId: $actorId,
            actorEmail: $actorEmail,
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            metadata: $metadata,
            ipAddress: $ip,
            previousHash: $previousHash,
            hash: $hash,
            createdAt: $createdAt,
        ));
    }

    /** @return array{?string, ?string} */
    private function resolveActor(?string $explicitId, ?string $explicitEmail): array
    {
        if (null !== $explicitId) {
            return [$explicitId, $explicitEmail];
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            return [null, null];
        }

        return [$user instanceof User ? $user->getId() : null, $user->getUserIdentifier()];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function computeHash(
        string $id,
        ?string $actorId,
        ?string $actorEmail,
        string $action,
        string $resourceType,
        ?string $resourceId,
        array $metadata,
        ?string $ip,
        DateTimeImmutable $createdAt,
        string $previousHash,
    ): string {
        $content = implode("\0", [
            $id,
            $actorId ?? '',
            $actorEmail ?? '',
            $action,
            $resourceType,
            $resourceId ?? '',
            json_encode($metadata, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR),
            $ip ?? '',
            $createdAt->format(DateTimeInterface::ATOM),
            $previousHash,
        ]);

        return hash('sha256', $content);
    }
}
