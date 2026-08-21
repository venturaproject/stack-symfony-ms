<?php

use App\Domain\Settings\Repository\SecuritySettingsRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\DoctrineSecuritySettingsRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(SecuritySettingsRepositoryInterface::class, DoctrineSecuritySettingsRepository::class);
};
