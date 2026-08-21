<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Settings\DTO\SecuritySettingsResponse;
use App\Domain\Settings\Event\SecuritySettingsUpdated;
use App\Domain\Settings\Repository\SecuritySettingsRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class UpdateSecuritySettingsHandler
{
    public function __construct(
        private readonly SecuritySettingsRepositoryInterface $settings,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function handle(UpdateSecuritySettingsCommand $cmd): SecuritySettingsResponse
    {
        $settings = $this->settings->get();
        $settings->setRequire2fa($cmd->require2fa);
        $settings = $this->settings->save($settings);

        $this->dispatcher->dispatch(new SecuritySettingsUpdated($cmd->require2fa));

        return SecuritySettingsResponse::from($settings);
    }
}
