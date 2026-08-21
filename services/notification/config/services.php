<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('App\\', '../src/')
        ->exclude([
            '../src/Domain/',
            '../src/Kernel.php',
        ]);

    $services->load('SharedKernel\\Infrastructure\\', '../vendor/boilerplate/shared-kernel/src/Infrastructure/');

    $container->import('services/*.php');
};
