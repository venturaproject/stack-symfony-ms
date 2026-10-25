<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $config
        ->paths('./src', '../../packages/shared-kernel/src', '../../packages/integration-events/src')
        ->excludeFiles('#.*test.*#')
        ->layers(
            $domain = Layer::withName('Domain')->collectors(
                ClassLikeConfig::create('.*App.Domain.*'),
            ),
            $application = Layer::withName('Application')->collectors(
                ClassLikeConfig::create('.*App.Application.*'),
            ),
            $infrastructure = Layer::withName('Infrastructure')->collectors(
                ClassLikeConfig::create('.*App.Infrastructure.*'),
            ),
            $bootstrap = Layer::withName('Bootstrap')->collectors(
                ClassLikeConfig::create('.*App.Kernel$'),
            ),
            $sharedDomain = Layer::withName('SharedKernelDomain')->collectors(
                ClassLikeConfig::create('.*SharedKernel.Domain.*'),
            ),
            $sharedApplication = Layer::withName('SharedKernelApplication')->collectors(
                ClassLikeConfig::create('.*SharedKernel.Application.*'),
            ),
            $sharedInfrastructure = Layer::withName('SharedKernelInfrastructure')->collectors(
                ClassLikeConfig::create('.*SharedKernel.Infrastructure.*'),
            ),
            $integrationEvents = Layer::withName('IntegrationEvents')->collectors(
                ClassLikeConfig::create('.*IntegrationEvents.*'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($domain)->accesses($sharedDomain),
            Ruleset::forLayer($application)->accesses($domain, $sharedApplication, $integrationEvents),
            Ruleset::forLayer($infrastructure)->accesses($application, $domain, $sharedDomain, $sharedApplication, $sharedInfrastructure, $integrationEvents),
            Ruleset::forLayer($bootstrap)->accesses($infrastructure, $application, $domain, $sharedDomain, $sharedApplication, $sharedInfrastructure, $integrationEvents),
            Ruleset::forLayer($sharedDomain),
            Ruleset::forLayer($sharedApplication)->accesses($sharedDomain),
            Ruleset::forLayer($sharedInfrastructure)->accesses($sharedDomain, $sharedApplication),
            Ruleset::forLayer($integrationEvents),
        )
    ;
};
