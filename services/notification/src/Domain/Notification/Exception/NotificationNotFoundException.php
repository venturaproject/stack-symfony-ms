<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exception;

use SharedKernel\Domain\Shared\Exception\EntityNotFoundException;

class NotificationNotFoundException extends EntityNotFoundException
{
    public function __construct()
    {
        parent::__construct('Notification');
    }
}
