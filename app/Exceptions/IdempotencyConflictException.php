<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct(
        string $message = 'Idempotency-Key telah digunakan sebelumnya dengan muatan (payload) yang berbeda.',
        public ?string $idempotencyKey = null,
        int $code = 409,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
