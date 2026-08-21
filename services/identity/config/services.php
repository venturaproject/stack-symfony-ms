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

    // Framework plumbing from the shared-kernel package (bus wrappers, listeners,
    // stateless-JWT auth pieces, the integration-event publisher). No domain code
    // lives there, so no exclusions are needed the way `src/Domain/` is excluded above.
    $services->load('SharedKernel\\Infrastructure\\', '../vendor/boilerplate/shared-kernel/src/Infrastructure/');

    $container->import('services/*.php');
};
