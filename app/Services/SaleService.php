<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientCashTenderException;
use App\Exceptions\InsufficientStockException;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StoreProfile;
use App\Models\User;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected CostingService $costingService,
        protected CashMovementService $cashMovementService,
        protected DocumentSequenceService $documentSequenceService,
        protected DiscountService $discountService,
        protected ProductService $productService
    ) {}

    /**
     * Calculate cart totals with server-authoritative pricing (PRD §5.5, §6.4).
     * Client is NEVER trusted for unit price, discounts, or totals.
     *
     * @param  array  $itemsInput  [['product_id' => int, 'quantity' => string|float, 'line_discount_type' => ?string, 'line_discount_value' => ?string], ...]
     * @param  array  $txDiscountInput  ['type' => ?string, 'value' => ?string]
     * @param  User  $cashier
     * @param  array|null  $approvalData
     * @return array
     */
    public function calculateCart(
        array $itemsInput,
        array $txDiscountInput,
        User $cashier,
        ?array $approvalData = null
    ): array {
        if (empty($itemsInput)) {
            throw new InvalidArgumentException('Keranjang belanja tidak boleh kosong.');
        }

        $calculatedLines = [];
        $lineBases = [];
        $grossTotal = Decimal::zero(2);
        $itemDiscountTotal = Decimal::zero(2);
        $lineDiscountsForValidation = [];

        foreach ($itemsInput as $index => $item) {
            $productId = $item['product_id'] ?? null;
            if (! $productId) {
                throw new InvalidArgumentException("Item ke-{$index} tidak memiliki product_id.");
            }

            $product = Product::with(['unit', 'category', 'inventoryBalance'])->findOrFail($productId);

            if (! $product->isSellable()) {
                throw new InvalidArgumentException("Produk [{$product->name}] sedang nonaktif atau memiliki kategori/satuan nonaktif sehingga tidak dapat dijual.");
            }

            $qtyRaw = (string) ($item['quantity'] ?? '1');
            if ($product->unit && ! $product->unit->isValidQuantity($qtyRaw)) {
                throw new InvalidArgumentException(
                    "Kuantitas [{$qtyRaw}] untuk produk [{$product->name}] melebihi presisi desimal satuan ({$product->unit->decimal_precision} desimal)."
                );
            }

            $qty = Decimal::of($qtyRaw, 3);
            if ($qty->isNegative() || $qty->isZero()) {
                throw new InvalidArgumentException("Kuantitas produk [{$product->name}] harus bernilai positif (> 0).");
            }

            // Server-authoritative selling price
            $unitPrice = Decimal::of($product->selling_price, 2);
            $grossLine = $qty->mul($unitPrice, 2);

            // Calculate line discount
            $discType = $item['line_discount_type'] ?? 'amount';
            $discVal = $item['line_discount_value'] ?? '0.00';
            $lineDiscount = $this->discountService->calculateLineDiscount($discType, $discVal, $grossLine);

            $preTransactionNet = $grossLine->sub($lineDiscount);

            $grossTotal = $grossTotal->add($grossLine);
            $itemDiscountTotal = $itemDiscountTotal->add($lineDiscount);

            $calculatedLines[$index] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'gross_line' => $grossLine,
                'line_discount_type' => $discType,
                'line_discount_value' => (string) $discVal,
                'line_discount' => $lineDiscount,
                'pre_transaction_net' => $preTransactionNet,
            ];

            $lineBases[$index] = $preTransactionNet->toString();

            $lineDiscountsForValidation[] = [
                'type' => $discType,
                'value' => $discVal,
                'amount' => $lineDiscount,
                'gross' => $grossLine,
            ];
        }

        // Calculate transaction-level discount
        $preTransactionNetTotal = $grossTotal->sub($itemDiscountTotal);
        $txDiscType = $txDiscountInput['type'] ?? 'amount';
        $txDiscVal = $txDiscountInput['value'] ?? '0.00';
        $txDiscount = $this->discountService->calculateTransactionDiscount($txDiscType, $txDiscVal, $preTransactionNetTotal);

        // Proportional allocation of transaction discount (PRD §8.2)
        $allocatedDiscounts = $this->discountService->allocateTransactionDiscount($txDiscount, $lineBases);

        // Finalize net line totals and grand total
        $grandTotal = Decimal::zero(2);
        foreach ($calculatedLines as $index => &$line) {
            $allocated = Decimal::of($allocatedDiscounts[$index] ?? '0.00', 2);
            $netLine = $line['pre_transaction_net']->sub($allocated);

            $line['allocated_transaction_discount'] = $allocated;
            $line['net_line_total'] = $netLine;

            $grandTotal = $grandTotal->add($netLine);
        }
        unset($line);

        // Validate discount limits and approvals (Maker-checker)
        $approver = $this->discountService->validateDiscountLimitsAndApproval(
            cashier: $cashier,
            lineDiscounts: $lineDiscountsForValidation,
            transactionDiscount: [
                'type' => $txDiscType,
                'value' => $txDiscVal,
                'amount' => $txDiscount,
            ],
            approvalData: $approvalData
        );

        return [
            'lines' => $calculatedLines,
            'gross_total' => $grossTotal,
            'item_discount_total' => $itemDiscountTotal,
            'transaction_discount_total' => $txDiscount,
            'transaction_discount_type' => $txDiscType,
            'transaction_discount_value' => (string) $txDiscVal,
            'grand_total' => $grandTotal,
            'approver' => $approver,
        ];
    }

    /**
     * Process cash checkout atomically in a single DB transaction (PRD §5.5, §6.4, §9.4).
     *
     * @param  array  $payload
     * @param  User  $cashier
     * @param  string  $idempotencyKey
     * @return Sale
     */
    public function checkoutCash(array $payload, User $cashier, string $idempotencyKey): Sale
    {
        $idempotencyKey = trim($idempotencyKey);
        if (empty($idempotencyKey)) {
            throw new InvalidArgumentException('Idempotency-Key wajib disertakan saat checkout.');
        }

        // Canonical payload hash (PRD §5.5, §6.4)
        $payloadHash = hash('sha256', json_encode($payload));

        // 1. Idempotency Check
        $existingSale = Sale::where('idempotency_key', $idempotencyKey)->first();
        if ($existingSale) {
            if ($existingSale->payload_hash === $payloadHash) {
                // Replay existing sale without mutating stock or cash (PRD §5.5, §6.4)
                return $existingSale->load(['items.product.unit', 'payment.cashAccount', 'cashier']);
            }

            // Same key with different payload => 409 Conflict (PRD §6.4)
            throw new IdempotencyConflictException(
                message: "Idempotency-Key [{$idempotencyKey}] telah digunakan sebelumnya dengan parameter pesanan berbeda.",
                idempotencyKey: $idempotencyKey
            );
        }

        $itemsInput = $payload['items'] ?? [];
        $txDiscountInput = [
            'type' => $payload['transaction_discount_type'] ?? 'amount',
            'value' => $payload['transaction_discount_value'] ?? '0.00',
        ];
        $paymentData = $payload['payment'] ?? [];
        $approvalData = $payload['discount_approval'] ?? null;

        // 2. Atomic Transaction Execution
        return DB::transaction(function () use (
            $payload,
            $payloadHash,
            $idempotencyKey,
            $itemsInput,
            $txDiscountInput,
            $paymentData,
            $approvalData,
            $cashier
        ) {
            // Determine Cash Account (Default to Kas Toko if not provided)
            $cashAccountId = $paymentData['cash_account_id'] ?? null;
            if (! $cashAccountId) {
                $defaultCode = config('pos.default_cash_account_code', 'KAS-TOKO');
                $defaultAcc = CashAccount::where('code', $defaultCode)->first()
                    ?? CashAccount::where('account_type', 'cash')->where('is_active', true)->firstOrFail();
                $cashAccountId = $defaultAcc->id;
            }

            // Lock Cash Account (Row-level lock)
            $cashAccount = $this->cashMovementService->lockAccount($cashAccountId, requireActive: true);

            // Lock all inventory balances upfront to prevent race conditions
            foreach ($itemsInput as $it) {
                $this->inventoryService->lockBalance($it['product_id']);
            }

            // Calculate authoritative pricing with locked balances
            $calculation = $this->calculateCart($itemsInput, $txDiscountInput, $cashier, $approvalData);

            $grandTotal = $calculation['grand_total'];
            $tendered = Decimal::of($paymentData['cash_tendered'] ?? '0.00', 2);

            // Cash Tender Validation
            if ($tendered->isLessThan($grandTotal)) {
                throw new InsufficientCashTenderException(
                    message: "Uang pembayaran tunai (Rp{$tendered}) kurang dari total belanja (Rp{$grandTotal}).",
                    tendered: $tendered->toString(),
                    grandTotal: $grandTotal->toString()
                );
            }

            $changeAmount = $tendered->sub($grandTotal);

            // Validate available stock for each line
            foreach ($calculation['lines'] as $line) {
                $prod = $line['product'];
                $qty = $line['quantity'];

                $balance = $this->inventoryService->getOrCreateBalance($prod);
                $available = Decimal::of($balance->available_quantity, 3);

                if ($available->isLessThan($qty)) {
                    throw new InsufficientStockException(
                        message: "Stok tersedia tidak mencukupi untuk [{$prod->name}]. Diminta: {$qty}, Tersedia: {$available}.",
                        productId: $prod->id,
                        requested: $qty->toString(),
                        available: $available->toString()
                    );
                }
            }

            // Generate unique transaction number
            $trxNumber = $this->documentSequenceService->generate('TRX');

            // Snapshot store profile
            $storeProfile = StoreProfile::current();
            $storeSnapshot = [
                'store_name' => $storeProfile->name ?? 'KasirPro Store',
                'address' => $storeProfile->address ?? '-',
                'phone' => $storeProfile->phone ?? '-',
                'receipt_header' => $storeProfile->receipt_header ?? 'Selamat Datang',
                'receipt_footer' => $storeProfile->receipt_footer ?? 'Terima kasih atas kunjungan Anda!',
                'receipt_width' => $storeProfile->receipt_paper_width ?? '58',
                'logo_path' => $storeProfile->logo_path ?? null,
            ];

            $now = Carbon::now('Asia/Jakarta');

            // Create Sale Header
            $sale = Sale::create([
                'transaction_number' => $trxNumber,
                'cashier_id' => $cashier->id,
                'sale_status' => 'completed',
                'payment_status' => 'paid',
                'gross_total' => $calculation['gross_total']->toString(),
                'item_discount_total' => $calculation['item_discount_total']->toString(),
                'transaction_discount_total' => $calculation['transaction_discount_total']->toString(),
                'grand_total' => $grandTotal->toString(),
                'store_snapshot' => $storeSnapshot,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'discount_approved_by' => $calculation['approver']?->id,
                'discount_override_reason' => $approvalData['reason'] ?? null,
                'completed_at' => $now,
                'notes' => $payload['notes'] ?? null,
            ]);

            // Create Sale Items, Deduct Stock, Capture HPP Snapshots
            foreach ($calculation['lines'] as $line) {
                $prod = $line['product'];
                $qty = $line['quantity'];

                // Current average cost snapshot before deduction
                $balance = $this->inventoryService->getOrCreateBalance($prod);
                $costPriceSnapshot = Decimal::of($balance->average_cost ?? '0.00', 2);
                $costTotalSnapshot = $qty->mul($costPriceSnapshot, 2);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $prod->id,
                    'product_name_snapshot' => $prod->name,
                    'sku_snapshot' => $prod->sku,
                    'barcode_snapshot' => $prod->barcode,
                    'unit_name_snapshot' => $prod->unit?->symbol ?? 'pcs',
                    'quantity' => $qty->toString(),
                    'unit_price_snapshot' => $line['unit_price']->toString(),
                    'gross_line_total' => $line['gross_line']->toString(),
                    'line_discount' => $line['line_discount']->toString(),
                    'allocated_transaction_discount' => $line['allocated_transaction_discount']->toString(),
                    'net_line_total' => $line['net_line_total']->toString(),
                    'cost_price_snapshot' => $costPriceSnapshot->toString(),
                    'cost_total_snapshot' => $costTotalSnapshot->toString(),
                ]);

                // Deduct stock via InventoryService (creates OUT_SALE movement & updates MWA if stock reaches 0)
                $this->inventoryService->deductStock(
                    product: $prod,
                    quantity: $qty,
                    movementType: 'OUT_SALE',
                    sourceType: 'sale',
                    sourceId: $sale->id,
                    sourceNumber: $sale->transaction_number,
                    userId: $cashier->id,
                    notes: "Penjualan kasir #{$sale->transaction_number}",
                    occurredAt: $now
                );
            }

            // Record Cash Inflow (CRITICAL: Must equal grand_total, NOT cash_tendered!)
            $cashMovement = $this->cashMovementService->recordInflow(
                account: $cashAccount,
                amount: $grandTotal,
                movementType: 'IN_SALE',
                sourceType: 'sale',
                sourceId: $sale->id,
                sourceNumber: $sale->transaction_number,
                description: "Penerimaan penjualan kasir #{$sale->transaction_number}",
                userId: $cashier->id,
                occurredAt: $now
            );

            // Create Sale Payment
            SalePayment::create([
                'sale_id' => $sale->id,
                'payment_method' => 'cash',
                'cash_account_id' => $cashAccount->id,
                'amount' => $grandTotal->toString(),
                'cash_tendered' => $tendered->toString(),
                'change_amount' => $changeAmount->toString(),
                'reference_number' => $paymentData['reference_number'] ?? null,
                'verification_status' => 'verified',
                'verified_by' => $cashier->id,
                'verified_at' => $now,
                'paid_at' => $now,
                'cash_movement_id' => $cashMovement->id,
            ]);

            // Audit Trail
            AuditLogger::log(
                action: 'sale.completed',
                module: 'sales',
                reference: $sale,
                newValues: [
                    'transaction_number' => $sale->transaction_number,
                    'grand_total' => $sale->grand_total,
                    'items_count' => count($calculation['lines']),
                    'cash_tendered' => $tendered->toString(),
                    'change_amount' => $changeAmount->toString(),
                    'cashier' => $cashier->name,
                ]
            );

            return $sale->load(['items.product.unit', 'payment.cashAccount', 'cashier']);
        });
    }
}
