<?php

use SharedKernel\Infrastructure\Security\JwtClaimsListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(JwtClaimsListener::class)
        ->autowire()->autoconfigure()
        ->arg('$appUrl', '%env(APP_URL)%');
};
