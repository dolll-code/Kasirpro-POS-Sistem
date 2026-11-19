<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientCashTenderException extends RuntimeException
{
    public function __construct(
        string $message = 'Uang yang diserahkan kurang dari total tagihan belanja.',
        public ?string $tendered = null,
        public ?string $grandTotal = null,
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
