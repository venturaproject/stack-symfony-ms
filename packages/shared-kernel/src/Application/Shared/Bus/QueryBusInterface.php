<?php

declare(strict_types=1);

namespace SharedKernel\Application\Shared\Bus;

interface QueryBusInterface
{
    public function ask(object $query): mixed;
}
