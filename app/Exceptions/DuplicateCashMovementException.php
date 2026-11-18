<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DuplicateCashMovementException extends Exception
{
    protected ?string $sourceType;

    protected ?int $sourceId;

    public function __construct(
        string $message = 'Transaksi telah memiliki catatan pergerakan kas dan tidak dapat diposting ganda.',
        ?string $sourceType = null,
        ?int $sourceId = null,
        int $code = Response::HTTP_CONFLICT,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => [
                    'code' => 'DUPLICATE_CASH_MOVEMENT',
                    'message' => $this->getMessage(),
                    'details' => [
                        'source_type' => $this->sourceType,
                        'source_id' => $this->sourceId,
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
