<?php

namespace App\Exceptions;

use RuntimeException;

class ReservationExpiredException extends RuntimeException
{
    public function __construct(
        string $message = 'Masa berlaku reservasi stok untuk transfer ini telah kedaluwarsa.',
        public ?int $saleId = null,
        public ?string $expiresAt = null
    ) {
        parent::__construct($message);
    }
}
