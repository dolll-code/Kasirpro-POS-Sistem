<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientCashTenderException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ReservationExpiredException;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockReservation;
use App\Models\StockReservationItem;
use App\Models\StoreProfile;
use App\Models\User;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
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
     */
    public function checkoutCash(array $payload, User $cashier, string $idempotencyKey): Sale
    {
        $idempotencyKey = trim($idempotencyKey);
        if (empty($idempotencyKey)) {
            throw new InvalidArgumentException('Idempotency-Key wajib disertakan saat checkout.');
        }

        // Canonical payload hash (PRD §5.5, §6.4)
        unset($payload['idempotency_key']);
        $payloadHash = $this->payloadHash($payload);

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

                // Current average cost snapshot before deduction.
                $balance = $this->inventoryService->getOrCreateBalance($prod);
                $costPriceSnapshot = Decimal::of($balance->average_cost ?? '0.00', 2);
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
                    'cost_total_snapshot' => $qty->mul($costPriceSnapshot, 2)->toString(),
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

    /**
     * Unified checkout entry point (PRD §5.5, §6.4).
     */
    public function checkout(array $payload, User $cashier, string $idempotencyKey): Sale
    {
        $method = $payload['payment']['method'] ?? 'cash';

        if ($method === 'bank_transfer') {
            return $this->checkoutTransfer($payload, $cashier, $idempotencyKey);
        }

        return $this->checkoutCash($payload, $cashier, $idempotencyKey);
    }

    /**
     * Process manual bank transfer checkout with stock reservation (PRD §5.5, §6.4, §7.3).
     * Creates a pending sale and holds stock reservations without deducting physical on-hand or adding cash.
     */
    public function checkoutTransfer(array $payload, User $cashier, string $idempotencyKey): Sale
    {
        $idempotencyKey = trim($idempotencyKey);
        if (empty($idempotencyKey)) {
            throw new InvalidArgumentException('Idempotency-Key wajib disertakan saat checkout transfer.');
        }

        unset($payload['idempotency_key']);
        $payloadHash = $this->payloadHash($payload);

        // 1. Idempotency Check
        $existingSale = Sale::where('idempotency_key', $idempotencyKey)->first();
        if ($existingSale) {
            if ($existingSale->payload_hash === $payloadHash) {
                return $existingSale->load(['items.product.unit', 'payment.cashAccount', 'cashier', 'reservation.items']);
            }

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
            // Determine Cash/Bank Account (Prefer Bank Account for transfer)
            $cashAccountId = $paymentData['cash_account_id'] ?? null;
            if (! $cashAccountId) {
                $bankAcc = CashAccount::where('account_type', 'bank')->where('is_active', true)->first()
                    ?? CashAccount::where('is_active', true)->firstOrFail();
                $cashAccountId = $bankAcc->id;
            }

            // Lock Cash/Bank Account
            $cashAccount = $this->cashMovementService->lockAccount($cashAccountId, requireActive: true);

            // Lock all inventory balances upfront
            foreach ($itemsInput as $it) {
                $this->inventoryService->lockBalance($it['product_id']);
            }

            // Authoritative server-side pricing
            $calculation = $this->calculateCart($itemsInput, $txDiscountInput, $cashier, $approvalData);
            $grandTotal = $calculation['grand_total'];

            // Validate available stock for each line (available = on_hand - reserved)
            foreach ($calculation['lines'] as $line) {
                $prod = $line['product'];
                $qty = $line['quantity'];

                $balance = $this->inventoryService->getOrCreateBalance($prod);
                $available = Decimal::of($balance->available_quantity, 3);

                if ($available->isLessThan($qty)) {
                    throw new InsufficientStockException(
                        message: "Stok tersedia tidak mencukupi untuk direservasi [{$prod->name}]. Diminta: {$qty}, Tersedia: {$available}.",
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
            $ttlMinutes = config('pos.transfer_reservation_ttl_minutes', 15);
            $expiresAt = $now->copy()->addMinutes($ttlMinutes);

            // Create Pending Sale Header
            $sale = Sale::create([
                'transaction_number' => $trxNumber,
                'cashier_id' => $cashier->id,
                'sale_status' => 'pending_payment',
                'payment_status' => 'pending_verification',
                'gross_total' => $calculation['gross_total']->toString(),
                'item_discount_total' => $calculation['item_discount_total']->toString(),
                'transaction_discount_total' => $calculation['transaction_discount_total']->toString(),
                'grand_total' => $grandTotal->toString(),
                'store_snapshot' => $storeSnapshot,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'discount_approved_by' => $calculation['approver']?->id,
                'discount_override_reason' => $approvalData['reason'] ?? null,
                'pending_expires_at' => $expiresAt,
                'completed_at' => null,
                'notes' => $payload['notes'] ?? null,
            ]);

            // Create pending Sale Items; HPP is snapshotted at verification/post time.
            foreach ($calculation['lines'] as $line) {
                $prod = $line['product'];
                $qty = $line['quantity'];

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
                    'cost_price_snapshot' => '0.00',
                    'cost_total_snapshot' => '0.00',
                ]);
            }

            // Create Stock Reservation Header
            $reservation = StockReservation::create([
                'sale_id' => $sale->id,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);

            // Create Stock Reservation Items and increment reserved quantity in balances
            $reservationQuantities = [];
            foreach ($calculation['lines'] as $line) {
                $productId = $line['product']->id;
                $reservationQuantities[$productId] = isset($reservationQuantities[$productId])
                    ? $reservationQuantities[$productId]->add($line['quantity'])
                    : $line['quantity'];
            }

            foreach ($reservationQuantities as $productId => $qty) {
                StockReservationItem::create([
                    'reservation_id' => $reservation->id,
                    'product_id' => $productId,
                    'quantity' => $qty->toString(),
                ]);

                // Reserve stock once per product (increments reserved, never on-hand).
                $this->inventoryService->reserveStock((int) $productId, $qty, transactional: false);
            }

            // Create Pending Sale Payment (no cash movement created yet)
            SalePayment::create([
                'sale_id' => $sale->id,
                'payment_method' => 'bank_transfer',
                'cash_account_id' => $cashAccount->id,
                'amount' => $grandTotal->toString(),
                'cash_tendered' => null,
                'change_amount' => '0.00',
                'reference_number' => $paymentData['reference_number'] ?? null,
                'verification_status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
                'paid_at' => null,
                'cash_movement_id' => null,
            ]);

            // Audit Trail
            AuditLogger::log(
                action: 'sale.transfer_initiated',
                module: 'sales',
                reference: $sale,
                newValues: [
                    'transaction_number' => $sale->transaction_number,
                    'grand_total' => $sale->grand_total,
                    'items_count' => count($calculation['lines']),
                    'reference_number' => $paymentData['reference_number'] ?? null,
                    'reservation_expires_at' => $expiresAt->toIso8601String(),
                    'cashier' => $cashier->name,
                ]
            );

            return $sale->load(['items.product.unit', 'payment.cashAccount', 'cashier', 'reservation.items']);
        });
    }

    /**
     * Manual verification of pending bank transfer (PRD §5.5, §6.4, §7.3).
     *
     * @param  string  $decision  ('verified' or 'rejected')
     */
    public function verifyTransfer(
        Sale|int $sale,
        User $verifier,
        string $decision,
        ?string $note = null,
        ?string $overrideReason = null
    ): Sale {
        $saleId = $sale instanceof Sale ? $sale->id : $sale;

        // 1. Permission check
        if (! $verifier->hasPermission('payments.verify_transfer')) {
            throw new AuthorizationException('Anda tidak memiliki izin payments.verify_transfer untuk memverifikasi transfer bank.');
        }

        // 2. Fetch sale for Maker-Checker check
        $saleRecord = Sale::with(['cashier'])->findOrFail($saleId);

        // Maker-Checker Rule: Requester cannot verify own transfer (PRD §4.2, §6.4)
        if ((int) $saleRecord->cashier_id === (int) $verifier->id) {
            $isAdmin = $verifier->hasRole('admin');
            $hasReason = ! empty(trim((string) $overrideReason));

            if (! ($isAdmin && $hasReason)) {
                throw new AuthorizationException(
                    'Prinsip Maker-Checker: Kasir dilarang memverifikasi transfer penjualannya sendiri. Otorisasi Admin Override mewajibkan alasan tertulis.'
                );
            }
        }

        // 3. Atomic Verification Execution
        $expiredData = null;

        $resultSale = DB::transaction(function () use ($saleId, $verifier, $decision, $note, $overrideReason, &$expiredData) {
            // Lock Sale row
            $sale = Sale::where('id', $saleId)->lockForUpdate()->firstOrFail();

            if ($sale->sale_status !== 'pending_payment' || $sale->payment_status !== 'pending_verification') {
                throw new InvalidArgumentException(
                    "Transaksi #{$sale->transaction_number} tidak berada dalam status menunggu verifikasi pembayaran."
                );
            }

            // Lock Stock Reservation row
            $reservation = StockReservation::where('sale_id', $sale->id)->lockForUpdate()->firstOrFail();
            $sale->load('payment');
            $now = Carbon::now('Asia/Jakarta');

            // Check Expiry (PRD §6.4, §7.3, §21.1)
            if ($reservation->isExpired() || $now->isAfter($reservation->expires_at)) {
                // Mark reservation expired and release held stock
                $reservation->status = 'expired';
                $reservation->released_at = $now;
                $reservation->save();

                foreach ($reservation->items as $resItem) {
                    $this->inventoryService->releaseReservation($resItem->product_id, $resItem->quantity, transactional: false);
                }

                $sale->update([
                    'sale_status' => 'cancelled',
                    'payment_status' => 'expired',
                    'cancelled_at' => $now,
                    'cancellation_reason' => 'Reservasi stok kedaluwarsa (TTL habis)',
                ]);

                $sale->payment?->update([
                    'verification_status' => 'expired',
                    'verification_note' => 'Reservasi kedaluwarsa sebelum verifikasi disahkan.',
                ]);

                AuditLogger::log(
                    action: 'sale.reservation_expired',
                    module: 'sales',
                    reference: $sale,
                    newValues: [
                        'transaction_number' => $sale->transaction_number,
                        'reason' => 'Reservasi stok kedaluwarsa (TTL habis)',
                        'verifier' => $verifier->name,
                    ]
                );

                $expiredData = [
                    'message' => "Masa berlaku reservasi stok untuk transaksi #{$sale->transaction_number} telah kedaluwarsa. Transaksi dibatalkan secara otomatis.",
                    'saleId' => $sale->id,
                    'expiresAt' => $reservation->expires_at?->toIso8601String(),
                ];

                return $sale;
            }

            if ($decision === 'rejected') {
                // Release reservation hold
                $reservation->status = 'released';
                $reservation->released_at = $now;
                $reservation->save();

                foreach ($reservation->items as $resItem) {
                    $this->inventoryService->releaseReservation($resItem->product_id, $resItem->quantity, transactional: false);
                }

                $rejectionReason = $note ?: 'Verifikasi transfer ditolak oleh pengawas.';

                $sale->update([
                    'sale_status' => 'cancelled',
                    'payment_status' => 'rejected',
                    'cancelled_at' => $now,
                    'cancellation_reason' => $rejectionReason,
                ]);

                $sale->payment?->update([
                    'verification_status' => 'rejected',
                    'verified_by' => $verifier->id,
                    'verified_at' => $now,
                    'verification_note' => $rejectionReason,
                ]);

                AuditLogger::log(
                    action: 'sale.transfer_rejected',
                    module: 'sales',
                    reference: $sale,
                    newValues: [
                        'transaction_number' => $sale->transaction_number,
                        'reason' => $rejectionReason,
                        'verifier' => $verifier->name,
                        'admin_override' => ! empty($overrideReason),
                    ]
                );

                return $sale->load(['items.product.unit', 'payment.cashAccount', 'cashier', 'reservation']);
            }

            if ($decision === 'verified') {
                $payment = $sale->payment;
                if (! $payment) {
                    throw new InvalidArgumentException("Transaksi #{$sale->transaction_number} tidak memiliki catatan pembayaran.");
                }

                // Lock bank account
                $cashAccount = $this->cashMovementService->lockAccount($payment->cash_account_id, requireActive: true);

                // Lock inventory balances for all reserved products
                $lockedBalances = [];
                foreach ($reservation->items as $resItem) {
                    $lockedBalances[$resItem->product_id] = $this->inventoryService->lockBalance($resItem->product_id);
                }

                // Persist historical HPP snapshot at posting time, after rows are locked.
                foreach ($sale->items as $saleItem) {
                    $balance = $lockedBalances[$saleItem->product_id] ?? $this->inventoryService->lockBalance($saleItem->product_id);
                    $cost = Decimal::of($balance->average_cost ?? '0.00', 2);
                    $saleItem->cost_price_snapshot = $cost->toString();
                    $saleItem->cost_total_snapshot = Decimal::of($saleItem->quantity, 3)->mul($cost, 2)->toString();
                    $saleItem->save();
                }

                // Consume reservation & deduct on-hand stock atomically
                $reservation->status = 'consumed';
                $reservation->released_at = $now;
                $reservation->save();

                foreach ($reservation->items as $resItem) {
                    // Release the reservation hold
                    $this->inventoryService->releaseReservation($resItem->product_id, $resItem->quantity, transactional: false);

                    // Deduct physical on-hand stock (writes OUT_SALE movement)
                    $this->inventoryService->deductStock(
                        product: $resItem->product_id,
                        quantity: $resItem->quantity,
                        movementType: 'OUT_SALE',
                        sourceType: 'sale',
                        sourceId: $sale->id,
                        sourceNumber: $sale->transaction_number,
                        userId: $verifier->id,
                        notes: "Penjualan transfer #{$sale->transaction_number} diverifikasi",
                        occurredAt: $now
                    );
                }

                // Record Bank Cash Inflow (exact grand_total, PRD §5.5, §6.4)
                $cashMovement = $this->cashMovementService->recordInflow(
                    account: $cashAccount,
                    amount: Decimal::of($sale->grand_total, 2),
                    movementType: 'IN_SALE',
                    sourceType: 'sale',
                    sourceId: $sale->id,
                    sourceNumber: $sale->transaction_number,
                    description: "Penerimaan transfer penjualan #{$sale->transaction_number}",
                    userId: $verifier->id,
                    occurredAt: $now,
                    transactional: false
                );

                // Update Payment Record
                $payment->update([
                    'verification_status' => 'verified',
                    'verified_by' => $verifier->id,
                    'verified_at' => $now,
                    'paid_at' => $now,
                    'verification_note' => $note,
                    'cash_movement_id' => $cashMovement->id,
                ]);

                // Update Sale Record
                $sale->update([
                    'sale_status' => 'completed',
                    'payment_status' => 'paid',
                    'completed_at' => $now,
                ]);

                // Audit Trail
                AuditLogger::log(
                    action: 'sale.transfer_verified',
                    module: 'sales',
                    reference: $sale,
                    newValues: [
                        'transaction_number' => $sale->transaction_number,
                        'grand_total' => $sale->grand_total,
                        'verifier' => $verifier->name,
                        'cash_account' => $cashAccount->name,
                        'admin_override' => ! empty($overrideReason),
                        'override_reason' => $overrideReason,
                    ]
                );

                return $sale->load(['items.product.unit', 'payment.cashAccount', 'cashier', 'reservation']);
            }

            throw new InvalidArgumentException("Keputusan verifikasi [{$decision}] tidak valid. Gunakan 'verified' atau 'rejected'.");
        });

        if ($expiredData) {
            throw new ReservationExpiredException(
                message: $expiredData['message'],
                saleId: $expiredData['saleId'],
                expiresAt: $expiredData['expiresAt']
            );
        }

        return $resultSale;
    }

    /**
     * Cancel a pending transfer transaction before expiration (PRD §5.5, §6.4).
     */
    public function cancelPendingTransfer(Sale|int $sale, User $user, string $reason): Sale
    {
        $saleId = $sale instanceof Sale ? $sale->id : $sale;

        return DB::transaction(function () use ($saleId, $user, $reason) {
            $sale = Sale::where('id', $saleId)->lockForUpdate()->firstOrFail();

            if ($sale->sale_status !== 'pending_payment') {
                throw new InvalidArgumentException('Hanya transaksi menunggu pembayaran yang dapat dibatalkan.');
            }

            $reservation = StockReservation::where('sale_id', $sale->id)->lockForUpdate()->firstOrFail();
            $now = Carbon::now('Asia/Jakarta');

            // Release reservation hold
            $reservation->status = 'released';
            $reservation->released_at = $now;
            $reservation->save();

            foreach ($reservation->items as $resItem) {
                $this->inventoryService->releaseReservation($resItem->product_id, $resItem->quantity, transactional: false);
            }

            $sale->update([
                'sale_status' => 'cancelled',
                'payment_status' => 'rejected',
                'cancelled_at' => $now,
                'cancellation_reason' => $reason,
            ]);

            $sale->payment?->update([
                'verification_status' => 'rejected',
                'verification_note' => "Dibatalkan: {$reason}",
            ]);

            AuditLogger::log(
                action: 'sale.transfer_cancelled',
                module: 'sales',
                reference: $sale,
                newValues: [
                    'transaction_number' => $sale->transaction_number,
                    'reason' => $reason,
                    'cancelled_by' => $user->name,
                ]
            );

            return $sale->load(['items.product.unit', 'payment.cashAccount', 'cashier', 'reservation']);
        });
    }

    /**
     * Expire all overdue active stock reservations (Scheduler housekeeping, PRD §9.5, §15.4).
     */
    public function expireOverdueReservations(): int
    {
        $now = Carbon::now('Asia/Jakarta');
        $overdueReservations = StockReservation::where('status', 'active')
            ->where('expires_at', '<=', $now)
            ->with(['items', 'sale.payment'])
            ->get();

        $count = 0;
        foreach ($overdueReservations as $reservation) {
            DB::transaction(function () use ($reservation, $now, &$count) {
                // Re-lock reservation
                $lockedRes = StockReservation::where('id', $reservation->id)->lockForUpdate()->first();
                if (! $lockedRes || $lockedRes->status !== 'active') {
                    return;
                }

                $lockedRes->status = 'expired';
                $lockedRes->released_at = $now;
                $lockedRes->save();

                // Release reserved stock on inventory balances
                foreach ($lockedRes->items as $resItem) {
                    $this->inventoryService->releaseReservation($resItem->product_id, $resItem->quantity, transactional: false);
                }

                $sale = $lockedRes->sale;
                if ($sale && $sale->sale_status === 'pending_payment') {
                    $sale->update([
                        'sale_status' => 'cancelled',
                        'payment_status' => 'expired',
                        'cancelled_at' => $now,
                        'cancellation_reason' => 'Reservasi stok kedaluwarsa otomatis oleh scheduler',
                    ]);

                    $sale->payment?->update([
                        'verification_status' => 'expired',
                        'verification_note' => 'Kedaluwarsa otomatis (TTL habis).',
                    ]);

                    AuditLogger::log(
                        action: 'sale.reservation_expired',
                        module: 'sales',
                        reference: $sale,
                        newValues: [
                            'transaction_number' => $sale->transaction_number,
                            'reason' => 'Kedaluwarsa otomatis oleh scheduler',
                        ]
                    );
                }

                $count++;
            });
        }

        return $count;
    }

    /** Produce stable payload identity independent of JSON key ordering. */
    private function payloadHash(array $payload): string
    {
        $normalize = function (array $value) use (&$normalize): array {
            ksort($value);
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $normalize($item);
                }
            }
            unset($item);

            return $value;
        };

        return hash('sha256', json_encode($normalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
