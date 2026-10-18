<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientWalletBalanceException extends RuntimeException
{
    public function __construct(public readonly int $balance, public readonly int $requested)
    {
        parent::__construct('موجودی کیف پول برای این برداشت کافی نیست.');
    }
}
