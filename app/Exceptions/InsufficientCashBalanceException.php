<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InsufficientCashBalanceException extends Exception
{
    protected ?int $cashAccountId;

    protected string $requested;

    protected string $available;

    public function __construct(
        string $message = 'Saldo kas/bank tidak mencukupi untuk transaksi ini.',
        ?int $cashAccountId = null,
        string $requested = '0.00',
        string $available = '0.00',
        int $code = Response::HTTP_CONFLICT,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->cashAccountId = $cashAccountId;
        $this->requested = $requested;
        $this->available = $available;
    }

    public function getCashAccountId(): ?int
    {
        return $this->cashAccountId;
    }

    public function getRequested(): string
    {
        return $this->requested;
    }

    public function getAvailable(): string
    {
        return $this->available;
    }

    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => [
                    'code' => 'INSUFFICIENT_CASH_BALANCE',
                    'message' => $this->getMessage(),
                    'details' => [
                        'cash_account_id' => $this->cashAccountId,
                        'requested' => $this->requested,
                        'available' => $this->available,
                    ],
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], Response::HTTP_CONFLICT);
        }

        return redirect()->back()->withErrors(['cash' => $this->getMessage()])->withInput();
    }
}
