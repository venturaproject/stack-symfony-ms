<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exception;

use SharedKernel\Domain\Shared\Exception\EntityNotFoundException;

class PermissionNotFoundException extends EntityNotFoundException
{
    public function __construct()
    {
        parent::__construct('Permission');
    }
}
