<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InactiveCashAccountException extends Exception
{
    protected ?int $cashAccountId;

    public function __construct(
        string $message = 'Akun kas/bank sedang nonaktif dan tidak dapat digunakan untuk transaksi baru.',
        ?int $cashAccountId = null,
        int $code = Response::HTTP_UNPROCESSABLE_ENTITY,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->cashAccountId = $cashAccountId;
    }

    public function getCashAccountId(): ?int
    {
        return $this->cashAccountId;
    }

    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => [
                    'code' => 'INACTIVE_CASH_ACCOUNT',
                    'message' => $this->getMessage(),
                    'details' => [
                        'cash_account_id' => $this->cashAccountId,
                    ],
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return redirect()->back()->withErrors(['cash_account_id' => $this->getMessage()])->withInput();
    }
}
