<?php

namespace App\Domain\Payments;

use RuntimeException;

class GatewayException extends RuntimeException
{
    /**
     * @param  bool  $isTransient  the gateway could not be reached at all, so the outcome of the payment is unknown and asking again later is safe
     */
    public function __construct(string $message, public readonly bool $isTransient = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
