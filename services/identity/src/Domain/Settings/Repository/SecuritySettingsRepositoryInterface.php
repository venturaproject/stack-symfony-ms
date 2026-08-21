<?php

declare(strict_types=1);

namespace App\Domain\Settings\Repository;

use App\Domain\Settings\Entity\SecuritySettings;

interface SecuritySettingsRepositoryInterface
{
    /** Creates and persists the default (require2fa: false) row on first call if none exists. */
    public function get(): SecuritySettings;

    public function save(SecuritySettings $settings): SecuritySettings;
}
