<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Persistence\Doctrine;

use SharedKernel\Domain\Shared\HasDomainEvents;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Doctrine's EventManager calls a method named after the event on any listener
 * tagged for it (no getSubscribedEvents() involved — that's the now-deprecated
 * Doctrine\Common\EventSubscriber contract, which doctrine-bundle 2.18+ no longer
 * wires up; #[AsDoctrineListener] per event is the current supported API).
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class DomainEventDispatcherSubscriber
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->dispatch($args);
    }

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->dispatch($args);
    }

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->dispatch($args);
    }

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    private function dispatch(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof HasDomainEvents) {
            return;
        }

        foreach ($entity->pullEvents() as $event) {
            $this->dispatcher->dispatch($event, $event::class);
        }
    }
}
