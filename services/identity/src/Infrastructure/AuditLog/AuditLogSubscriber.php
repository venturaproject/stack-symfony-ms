<?php

declare(strict_types=1);

namespace App\Infrastructure\AuditLog;

use App\Domain\Auth\Event\UserActivated;
use App\Domain\Auth\Event\UserCreated;
use App\Domain\Auth\Event\UserDeactivated;
use App\Domain\Auth\Event\UserImpersonated;
use App\Domain\Auth\Event\UserLoggedIn;
use App\Domain\Auth\Event\UserPasswordChanged;
use App\Domain\Auth\Event\UserTotpDisabled;
use App\Domain\Auth\Event\UserTotpEnabled;
use App\Domain\Auth\Event\UserUpdated;
use App\Domain\Settings\Event\SecuritySettingsUpdated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AuditLogSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuditLogRecorder $recorder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserLoggedIn::class => 'onUserLoggedIn',
            UserCreated::class => 'onUserCreated',
            UserUpdated::class => 'onUserUpdated',
            UserPasswordChanged::class => 'onUserPasswordChanged',
            UserImpersonated::class => 'onUserImpersonated',
            UserDeactivated::class => 'onUserDeactivated',
            UserActivated::class => 'onUserActivated',
            UserTotpEnabled::class => 'onUserTotpEnabled',
            UserTotpDisabled::class => 'onUserTotpDisabled',
            SecuritySettingsUpdated::class => 'onSecuritySettingsUpdated',
        ];
    }

    public function onUserLoggedIn(UserLoggedIn $event): void
    {
        $this->recorder->record(
            action: 'user.login',
            resourceType: 'User',
            resourceId: $event->userId,
            explicitActorId: $event->userId,
            explicitActorEmail: $event->email,
        );
    }

    public function onUserCreated(UserCreated $event): void
    {
        $this->recorder->record(
            action: 'user.created',
            resourceType: 'User',
            resourceId: $event->userId,
            metadata: ['email' => $event->email],
        );
    }

    public function onUserUpdated(UserUpdated $event): void
    {
        $this->recorder->record(
            action: 'user.updated',
            resourceType: 'User',
            resourceId: $event->userId,
            metadata: ['email' => $event->email],
        );
    }

    public function onUserPasswordChanged(UserPasswordChanged $event): void
    {
        $this->recorder->record(
            action: 'user.password_changed',
            resourceType: 'User',
            resourceId: $event->userId,
        );
    }

    public function onUserImpersonated(UserImpersonated $event): void
    {
        // No explicit actor: the security token at this point in the request is still
        // the admin's (the impersonation JWT isn't in use yet), so the recorder's
        // fallback to the current token's user already attributes this correctly.
        $this->recorder->record(
            action: 'user.impersonated',
            resourceType: 'User',
            resourceId: $event->userId,
            metadata: ['impersonatorEmail' => $event->impersonatorEmail],
        );
    }

    public function onUserTotpEnabled(UserTotpEnabled $event): void
    {
        $this->recorder->record(
            action: 'user.totp_enabled',
            resourceType: 'User',
            resourceId: $event->userId,
        );
    }

    public function onUserTotpDisabled(UserTotpDisabled $event): void
    {
        $this->recorder->record(
            action: 'user.totp_disabled',
            resourceType: 'User',
            resourceId: $event->userId,
        );
    }

    public function onSecuritySettingsUpdated(SecuritySettingsUpdated $event): void
    {
        $this->recorder->record(
            action: 'security_settings.updated',
            resourceType: 'SecuritySettings',
            resourceId: null,
            metadata: ['require2fa' => $event->require2fa],
        );
    }

    public function onUserDeactivated(UserDeactivated $event): void
    {
        $this->recorder->record(
            action: 'user.deactivated',
            resourceType: 'User',
            resourceId: $event->userId,
            metadata: ['email' => $event->email],
        );
    }

    public function onUserActivated(UserActivated $event): void
    {
        $this->recorder->record(
            action: 'user.activated',
            resourceType: 'User',
            resourceId: $event->userId,
            metadata: ['email' => $event->email],
        );
    }
}
