<?php

use App\Application\Notification\Repository\ProcessedIntegrationEventRepositoryInterface;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\DoctrineNotificationRepository;
use App\Infrastructure\Persistence\Doctrine\DoctrineProcessedIntegrationEventRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(NotificationRepositoryInterface::class, DoctrineNotificationRepository::class);
    $services->alias(ProcessedIntegrationEventRepositoryInterface::class, DoctrineProcessedIntegrationEventRepository::class);
};
