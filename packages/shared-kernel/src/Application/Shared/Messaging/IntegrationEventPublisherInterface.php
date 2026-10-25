<?php

declare(strict_types=1);

namespace SharedKernel\Application\Shared\Messaging;

interface IntegrationEventPublisherInterface
{
    public function publish(object $event): void;
}
