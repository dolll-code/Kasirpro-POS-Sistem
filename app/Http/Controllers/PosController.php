<?php

namespace App\Http\Controllers;

use App\Exceptions\DiscountApprovalRequiredException;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientCashTenderException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Sales\CheckoutRequest;
use App\Models\CashAccount;
use App\Models\Category;
use App\Services\ProductService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class PosController extends Controller
{
    public function __construct(
        protected SaleService $saleService,
        protected ProductService $productService
    ) {}

    /**
     * Display POS checkout interface.
     */
    public function index(): View
    {
        $categories = Category::active()->orderBy('name')->get();
        $cashAccounts = CashAccount::active()->where('account_type', 'cash')->orderBy('name')->get();
        $defaultCashAccount = $cashAccounts->where('code', config('pos.default_cash_account_code', 'KAS-TOKO'))->first()
            ?? $cashAccounts->first();

        return view('pos.index', compact('categories', 'cashAccounts', 'defaultCashAccount'));
    }

    /**
     * Fast barcode lookup endpoint for USB scanner (PRD §5.3, §6.4).
     */
    public function barcodeLookup(string $barcode): JsonResponse
    {
        $product = $this->productService->findByBarcode($barcode);

        if (! $product) {
            return response()->json([
                'error' => [
                    'code' => 'PRODUCT_NOT_FOUND',
                    'message' => "Produk dengan barcode [{$barcode}] tidak ditemukan atau sedang nonaktif.",
                ],
            ], 404);
        }

        return response()->json([
            'data' => $this->productService->formatProductPayload($product),
        ]);
    }

    /**
     * Live server-authoritative cart calculation preview (PRD §5.5, §6.4).
     */
    public function calculate(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        $txDiscount = [
            'type' => $request->input('transaction_discount_type', 'amount'),
            'value' => $request->input('transaction_discount_value', '0.00'),
        ];
        $approvalData = $request->input('discount_approval');

        try {
            $calculation = $this->saleService->calculateCart(
                itemsInput: $items,
                txDiscountInput: $txDiscount,
                cashier: $request->user(),
                approvalData: $approvalData
            );

            $formattedLines = [];
            foreach ($calculation['lines'] as $line) {
                $formattedLines[] = [
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'sku' => $line['product']->sku,
                    'unit_symbol' => $line['product']->unit?->symbol ?? 'pcs',
                    'unit_price' => $line['unit_price']->toString(),
                    'quantity' => $line['quantity']->toString(),
                    'gross_line' => $line['gross_line']->toString(),
                    'line_discount' => $line['line_discount']->toString(),
                    'allocated_transaction_discount' => $line['allocated_transaction_discount']->toString(),
                    'net_line' => $line['net_line_total']->toString(),
                ];
            }

            return response()->json([
                'data' => [
                    'lines' => $formattedLines,
                    'gross_total' => $calculation['gross_total']->toString(),
                    'item_discount_total' => $calculation['item_discount_total']->toString(),
                    'transaction_discount_total' => $calculation['transaction_discount_total']->toString(),
                    'grand_total' => $calculation['grand_total']->toString(),
                    'requires_approval' => false,
                ],
            ]);
        } catch (DiscountApprovalRequiredException $e) {
            return response()->json([
                'error' => [
                    'code' => 'DISCOUNT_APPROVAL_REQUIRED',
                    'message' => $e->getMessage(),
                ],
                'data' => [
                    'requires_approval' => true,
                ],
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CART',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Process cash checkout (PRD §5.5, §6.4).
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        // Extract Idempotency-Key from header or payload
        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $request->input('idempotency_key')
            ?? (string) Str::uuid();

        try {
            $sale = $this->saleService->checkoutCash(
                payload: $request->validated(),
                cashier: $request->user(),
                idempotencyKey: $idempotencyKey
            );

            return response()->json([
                'message' => 'Transaksi penjualan berhasil diproses.',
                'data' => [
                    'id' => $sale->id,
                    'transaction_number' => $sale->transaction_number,
                    'gross_total' => $sale->gross_total,
                    'item_discount_total' => $sale->item_discount_total,
                    'transaction_discount_total' => $sale->transaction_discount_total,
                    'grand_total' => $sale->grand_total,
                    'cash_tendered' => $sale->payment?->cash_tendered,
                    'change_amount' => $sale->payment?->change_amount,
                    'completed_at' => $sale->completed_at?->toIso8601String(),
                    'receipt_url' => route('sales.receipt', $sale),
                    'items_count' => $sale->items->count(),
                ],
            ], 201);
        } catch (IdempotencyConflictException $e) {
            return response()->json([
                'error' => [
                    'code' => 'IDEMPOTENCY_CONFLICT',
                    'message' => $e->getMessage(),
                ],
            ], 409);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INSUFFICIENT_STOCK',
                    'message' => $e->getMessage(),
                    'product_id' => $e->productId,
                    'requested' => $e->requested,
                    'available' => $e->available,
                ],
            ], 409);
        } catch (InsufficientCashTenderException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INSUFFICIENT_CASH_TENDER',
                    'message' => $e->getMessage(),
                    'tendered' => $e->tendered,
                    'grand_total' => $e->grandTotal,
                ],
            ], 422);
        } catch (DiscountApprovalRequiredException $e) {
            return response()->json([
                'error' => [
                    'code' => 'DISCOUNT_APPROVAL_REQUIRED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TRANSACTION',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }
}
