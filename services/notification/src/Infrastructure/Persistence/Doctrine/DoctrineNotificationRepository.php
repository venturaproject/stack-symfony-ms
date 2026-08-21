<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class DoctrineNotificationRepository extends ServiceEntityRepository implements NotificationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findById(string $id): ?Notification
    {
        return $this->find($id);
    }

    public function findForUser(string $userId): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.userId = :userId OR n.userId IS NULL')
            ->setParameter('userId', $userId)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(Notification $notification): Notification
    {
        $this->getEntityManager()->persist($notification);
        $this->getEntityManager()->flush();

        return $notification;
    }

    public function delete(Notification $notification): void
    {
        $this->getEntityManager()->remove($notification);
        $this->getEntityManager()->flush();
    }

    public function deleteAllForUser(string $userId): void
    {
        $this->createQueryBuilder('n')
            ->delete()
            ->where('n.userId = :userId OR n.userId IS NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    public function markAllReadForUser(string $userId): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('(n.userId = :userId OR n.userId IS NULL) AND n.readAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
