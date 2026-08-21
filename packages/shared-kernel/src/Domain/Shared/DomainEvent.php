<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Shared;

use DateTimeImmutable;

abstract class DomainEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public readonly DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new DateTimeImmutable();
    }
}
