<?php

use App\Application\Auth\Command\LoginHandler;
use App\Application\Auth\Command\RefreshTokenHandler;
use App\Application\Auth\Command\UploadAvatarHandler;
use SharedKernel\Application\Shared\Bus\CommandBusInterface;
use SharedKernel\Application\Shared\Bus\QueryBusInterface;
use App\Domain\Auth\Repository\PermissionRepositoryInterface;
use App\Domain\Auth\Repository\RefreshTokenRepositoryInterface;
use App\Domain\Auth\Repository\RoleRepositoryInterface;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use SharedKernel\Infrastructure\Bus\MessengerCommandBus;
use SharedKernel\Infrastructure\Bus\MessengerQueryBus;
use App\Infrastructure\Http\Auth\AuthCookieFactory;
use SharedKernel\Infrastructure\Security\JwtClaimsListener;
use App\Infrastructure\Persistence\Doctrine\DoctrinePermissionRepository;
use App\Infrastructure\Persistence\Doctrine\DoctrineRefreshTokenRepository;
use App\Infrastructure\Persistence\Doctrine\DoctrineRoleRepository;
use App\Infrastructure\Persistence\Doctrine\DoctrineUserRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Repository aliases
    $services->alias(UserRepositoryInterface::class, DoctrineUserRepository::class);
    $services->alias(RoleRepositoryInterface::class, DoctrineRoleRepository::class);
    $services->alias(PermissionRepositoryInterface::class, DoctrinePermissionRepository::class);
    $services->alias(RefreshTokenRepositoryInterface::class, DoctrineRefreshTokenRepository::class);

    // Bus interfaces → implementations
    $services->alias(CommandBusInterface::class, MessengerCommandBus::class);
    $services->alias(QueryBusInterface::class, MessengerQueryBus::class);

    // Handlers with scalar params
    $services->set(LoginHandler::class)
        ->autowire()->autoconfigure()
        ->arg('$refreshTtlSecs', '%env(int:JWT_REFRESH_TTL_SECS)%');

    $services->set(RefreshTokenHandler::class)
        ->autowire()->autoconfigure()
        ->arg('$refreshTtlSecs', '%env(int:JWT_REFRESH_TTL_SECS)%');

    $services->set(UploadAvatarHandler::class)
        ->autowire()->autoconfigure()
        ->arg('$mediaDir', '%env(MEDIA_DIR)%');

    $services->set(AuthCookieFactory::class)
        ->autowire()
        ->arg('$cookieSecure', '%env(bool:COOKIE_SECURE)%');

    $services->set(JwtClaimsListener::class)
        ->autowire()->autoconfigure()
        ->arg('$appUrl', '%env(APP_URL)%');
};
