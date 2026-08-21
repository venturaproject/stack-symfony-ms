<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Shared;

interface HasDomainEvents
{
    /** @return DomainEvent[] */
    public function pullEvents(): array;
}
