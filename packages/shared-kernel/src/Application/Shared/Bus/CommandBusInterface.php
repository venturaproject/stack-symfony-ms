<?php

declare(strict_types=1);

namespace SharedKernel\Application\Shared\Bus;

interface CommandBusInterface
{
    public function dispatch(object $command): mixed;
}
