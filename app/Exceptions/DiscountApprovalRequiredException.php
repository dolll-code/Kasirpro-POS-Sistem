<?php

namespace App\Exceptions;

use RuntimeException;

class DiscountApprovalRequiredException extends RuntimeException
{
    public function __construct(
        string $message = 'Diskon melebihi batas wewenang kasir dan membutuhkan persetujuan supervisor/admin.',
        public ?string $discountType = null,
        public ?string $discountValue = null,
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
