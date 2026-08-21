<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\AuditLog\DTO\AuditLogEntryResponse;
use App\Application\AuditLog\DTO\PagedAuditLogResponse;
use App\Domain\AuditLog\Entity\AuditLog;
use App\Domain\AuditLog\Repository\AuditLogRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditLog> */
final class DoctrineAuditLogRepository extends ServiceEntityRepository implements AuditLogRepositoryInterface
{
    private const GENESIS_HASH = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $entry): void
    {
        $this->getEntityManager()->persist($entry);
        $this->getEntityManager()->flush();
    }

    public function getLastHash(): string
    {
        $result = $this->createQueryBuilder('al')
            ->select('al.hash')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['hash'] ?? self::GENESIS_HASH;
    }

    public function findByActorId(string $actorId, int $page, int $size): PagedAuditLogResponse
    {
        $qb = $this->createQueryBuilder('al')
            ->where('al.actorId = :actorId')
            ->setParameter('actorId', $actorId)
            ->orderBy('al.createdAt', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(al.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var AuditLog[] $entries */
        $entries = $qb
            ->setFirstResult(($page - 1) * $size)
            ->setMaxResults($size)
            ->getQuery()
            ->getResult();

        return new PagedAuditLogResponse(
            data: array_map(AuditLogEntryResponse::from(...), $entries),
            total: $total,
            page: $page,
            lastPage: $total > 0 ? (int) ceil($total / $size) : 1,
            perPage: $size,
        );
    }
}
