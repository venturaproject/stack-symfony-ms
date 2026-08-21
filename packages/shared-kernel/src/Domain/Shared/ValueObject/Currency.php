<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Shared\ValueObject;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
}
