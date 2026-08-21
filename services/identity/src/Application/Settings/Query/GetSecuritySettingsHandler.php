<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Settings\DTO\SecuritySettingsResponse;
use App\Domain\Settings\Repository\SecuritySettingsRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', method: 'handle')]
final class GetSecuritySettingsHandler
{
    public function __construct(private readonly SecuritySettingsRepositoryInterface $settings)
    {
    }

    public function handle(GetSecuritySettingsQuery $query): SecuritySettingsResponse
    {
        return SecuritySettingsResponse::from($this->settings->get());
    }
}
