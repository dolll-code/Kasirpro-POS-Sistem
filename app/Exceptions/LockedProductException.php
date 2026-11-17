<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LockedProductException extends Exception
{
    protected ?int $productId;

    public function __construct(
        string $message = 'Produk sedang dikunci untuk proses stok opname.',
        ?int $productId = null,
        int $code = Response::HTTP_CONFLICT,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->productId = $productId;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => [
                    'code' => 'PRODUCT_LOCKED',
                    'message' => $this->getMessage(),
                    'details' => [
                        'product_id' => $this->productId,
                    ],
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ], Response::HTTP_CONFLICT);
        }

        return redirect()->back()->withErrors(['lock' => $this->getMessage()])->withInput();
    }
}
