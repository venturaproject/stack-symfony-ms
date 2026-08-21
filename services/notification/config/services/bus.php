<?php

use SharedKernel\Application\Shared\Bus\CommandBusInterface;
use SharedKernel\Application\Shared\Bus\QueryBusInterface;
use SharedKernel\Infrastructure\Bus\MessengerCommandBus;
use SharedKernel\Infrastructure\Bus\MessengerQueryBus;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(CommandBusInterface::class, MessengerCommandBus::class);
    $services->alias(QueryBusInterface::class, MessengerQueryBus::class);
};
