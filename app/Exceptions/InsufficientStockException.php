<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InsufficientStockException extends Exception
{
    protected ?int $productId;

    protected string $requested;

    protected string $available;

    public function __construct(
        string $message = 'Stok tersedia tidak mencukupi.',
        ?int $productId = null,
        string $requested = '0.000',
        string $available = '0.000',
        int $code = Response::HTTP_CONFLICT,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->productId = $productId;
        $this->requested = $requested;
        $this->available = $available;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
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
                    'code' => 'INSUFFICIENT_STOCK',
                    'message' => $this->getMessage(),
                    'details' => [
                        'product_id' => $this->productId,
                        'requested' => $this->requested,
                        'available' => $this->available,
                    ],
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], Response::HTTP_CONFLICT);
        }

        return redirect()->back()->withErrors(['stock' => $this->getMessage()])->withInput();
    }
}
