<?php

declare(strict_types=1);

namespace App\Domain\ApiClient\Exception;

use SharedKernel\Domain\Shared\Exception\EntityNotFoundException;

class ApiClientNotFoundException extends EntityNotFoundException
{
    public function __construct()
    {
        parent::__construct('ApiClient');
    }
}
