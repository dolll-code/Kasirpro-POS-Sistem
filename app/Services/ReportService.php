<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public function period(?string $from, ?string $to): array
    {
        $tz = self::TIMEZONE;
        $start = $from ? Carbon::createFromFormat('Y-m-d', $from, $tz)->startOfDay() : Carbon::now($tz)->startOfMonth()->startOfDay();
        $end = $to ? Carbon::createFromFormat('Y-m-d', $to, $tz)->endOfDay() : Carbon::now($tz)->endOfDay();
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }
        $days = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        return [
            'from' => $start,
            'to' => $end,
            'from_date' => $start->toDateString(),
            'to_date' => $end->toDateString(),
            'days' => (int) $days,
            'comparison_from_date' => $start->copy()->subDays($days)->toDateString(),
            'comparison_to_date' => $start->copy()->subDay()->toDateString(),
        ];
    }

    public function dashboard(User $user): array
    {
        $canViewAll = $user->hasPermission('sales.view_all');
        $period = $this->period(now(self::TIMEZONE)->toDateString(), now(self::TIMEZONE)->toDateString());
        $sales = $this->salesReport($user, $period['from_date'], $period['to_date']);

        $lowStock = InventoryBalance::query()
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->where('inventory_balances.quantity_on_hand', '>', 0)
            ->whereColumn('inventory_balances.quantity_on_hand', '<=', 'products.minimum_stock')
            ->count();
        $outOfStock = Product::where('is_active', true)
            ->where(function ($q) {
                $q->doesntHave('inventoryBalance')
                    ->orWhereHas('inventoryBalance', fn ($b) => $b->where('quantity_on_hand', '<=', 0));
            })->count();

        $todayProfit = $this->profitLossPeriod($period['from_date'], $period['to_date'])['summary'];
        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now(self::TIMEZONE)->subDays($i);
            $sum = (string) Sale::whereIn('sale_status', ['completed', 'partially_returned', 'fully_returned'])
                ->whereDate('completed_at', $day->toDateString())
                ->when(! $canViewAll, fn ($q) => $q->where('cashier_id', $user->id))
                ->sum('grand_total');
            $chart[] = ['label' => $day->format('d/m'), 'date' => $day->toDateString(), 'value' => Decimal::of($sum, 2)->toString()];
        }
        $max = max(array_map(fn ($p) => (float) $p['value'], $chart)) ?: 1;
        foreach ($chart as &$point) {
            $point['percent'] = round(((float) $point['value'] / $max) * 100, 1);
        }
        unset($point);

        return [
            'can_view_all' => $canViewAll,
            'sales' => $sales['summary'],
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'today_profit' => $todayProfit,
            'chart' => $chart,
            'recent_sales' => Sale::with(['cashier:id,name'])
                ->when(! $canViewAll, fn ($q) => $q->where('cashier_id', $user->id))
                ->where('sale_status', '!=', 'pending_payment')
                ->latest('created_at')->limit(8)->get(),
            'recent_cash' => ($user->hasPermission('reports.view_all') || $user->hasPermission('reports.view_limited'))
                ? CashMovement::with(['cashAccount:id,name', 'creator:id,name'])->latest('occurred_at')->limit(8)->get()
                : collect(),
        ];
    }

    public function salesReport(User $user, ?string $from = null, ?string $to = null, int $perPage = 15, string $sort = 'completed_at', string $dir = 'desc'): array
    {
        $period = $this->period($from, $to);
        $allowed = ['transaction_number', 'completed_at', 'grand_total', 'sale_status'];
        $sort = in_array($sort, $allowed, true) ? $sort : 'completed_at';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        $base = Sale::query()->whereIn('sale_status', ['completed', 'partially_returned', 'fully_returned'])
            ->whereBetween('completed_at', [$period['from'], $period['to']])
            ->when(! $user->hasPermission('sales.view_all'), fn ($q) => $q->where('cashier_id', $user->id));

        $summary = (clone $base)->selectRaw('COUNT(*) as transactions, COALESCE(SUM(gross_total),0) as gross, COALESCE(SUM(item_discount_total),0) as item_discount, COALESCE(SUM(transaction_discount_total),0) as transaction_discount, COALESCE(SUM(grand_total),0) as net')->first();
        $sales = (clone $base)->with(['cashier:id,name'])->withCount('items')->orderBy($sort, $dir)->paginate($perPage);

        return [
            'period' => $period,
            'summary' => [
                'transactions' => (int) ($summary->transactions ?? 0),
                'gross' => Decimal::of((string) ($summary->gross ?? 0), 2)->toString(),
                'item_discount' => Decimal::of((string) ($summary->item_discount ?? 0), 2)->toString(),
                'transaction_discount' => Decimal::of((string) ($summary->transaction_discount ?? 0), 2)->toString(),
                'net' => Decimal::of((string) ($summary->net ?? 0), 2)->toString(),
            ],
            'data' => $sales,
        ];
    }

    public function purchaseReport(?string $from = null, ?string $to = null, int $perPage = 15): array
    {
        $period = $this->period($from, $to);
        $base = Purchase::query()->whereDate('order_date', '>=', $period['from_date'])->whereDate('order_date', '<=', $period['to_date']);
        $summary = (clone $base)->selectRaw('COUNT(*) as count, COALESCE(SUM(payable_total),0) as payable, COALESCE(SUM(paid_amount),0) as paid')->first();
        $data = (clone $base)->with('supplier:id,name')->orderByDesc('order_date')->orderByDesc('id')->paginate($perPage);

        return [
            'period' => $period,
            'summary' => [
                'count' => (int) ($summary->count ?? 0),
                'payable' => Decimal::of((string) ($summary->payable ?? 0), 2)->toString(),
                'paid' => Decimal::of((string) ($summary->paid ?? 0), 2)->toString(),
                'outstanding' => Decimal::of((string) ($summary->payable ?? 0), 2)->sub((string) ($summary->paid ?? 0))->toString(),
            ],
            'data' => $data,
        ];
    }

    public function returnReport(?string $from = null, ?string $to = null, int $perPage = 15): array
    {
        $period = $this->period($from, $to);
        $base = SalesReturn::query()->whereIn('status', ['approved', 'completed'])->whereBetween('approved_at', [$period['from'], $period['to']]);
        $summary = (clone $base)->selectRaw('COUNT(*) as count, COALESCE(SUM(return_total),0) as total')->first();
        $resellable = SalesReturnItem::whereHas('salesReturn', fn ($q) => $q->whereIn('status', ['approved', 'completed'])->whereBetween('approved_at', [$period['from'], $period['to']]))
            ->where('condition', 'resellable')->sum('cost_value');
        $damaged = SalesReturnItem::whereHas('salesReturn', fn ($q) => $q->whereIn('status', ['approved', 'completed'])->whereBetween('approved_at', [$period['from'], $period['to']]))
            ->where('condition', 'damaged')->sum('damaged_loss');
        $data = (clone $base)->with(['sale:id,transaction_number', 'requester:id,name', 'approver:id,name', 'payment'])->latest('approved_at')->paginate($perPage);

        return [
            'period' => $period,
            'summary' => [
                'count' => (int) ($summary->count ?? 0),
                'total' => Decimal::of((string) ($summary->total ?? 0), 2)->toString(),
                'cogs_reversal' => Decimal::of((string) $resellable, 2)->toString(),
                'damaged_loss_informational' => Decimal::of((string) $damaged, 2)->toString(),
            ],
            'data' => $data,
        ];
    }

    public function expenseReport(?string $from = null, ?string $to = null, int $perPage = 15): array
    {
        $period = $this->period($from, $to);
        $base = Expense::query()->whereIn('status', ['posted', 'voided'])->whereDate('expense_date', '>=', $period['from_date'])->whereDate('expense_date', '<=', $period['to_date']);
        $recognized = (clone $base)->sum('amount');
        $voided = Expense::where('status', 'voided')->whereBetween('voided_at', [$period['from'], $period['to']])->sum('amount');
        $data = (clone $base)->with(['category:id,name', 'creator:id,name', 'poster:id,name'])->orderByDesc('expense_date')->paginate($perPage);

        return [
            'period' => $period,
            'summary' => [
                'gross' => Decimal::of((string) $recognized, 2)->toString(),
                'void_reversal' => Decimal::of((string) $voided, 2)->toString(),
                'net' => Decimal::of((string) $recognized, 2)->sub((string) $voided)->toString(),
            ],
            'data' => $data,
        ];
    }

    public function inventoryReport(?string $from = null, ?string $to = null, int $perPage = 15): array
    {
        $period = $this->period($from, $to);
        $products = Product::with(['category:id,name', 'unit:id,symbol', 'inventoryBalance'])->orderBy('name')->paginate($perPage);
        $movements = StockMovement::with(['product:id,name,sku'])->whereBetween('occurred_at', [$period['from'], $period['to']])->latest('occurred_at')->limit(50)->get();
        $onHandValue = InventoryBalance::query()->selectRaw('COALESCE(SUM(quantity_on_hand * average_cost),0) as value')->value('value');

        return [
            'period' => $period,
            'summary' => ['current_stock_value' => Decimal::of((string) $onHandValue, 2)->toString(), 'movement_count' => $movements->count()],
            'data' => $products,
            'movements' => $movements,
        ];
    }

    public function profitLoss(?string $from = null, ?string $to = null, ?string $compareFrom = null, ?string $compareTo = null): array
    {
        $current = $this->profitLossPeriod($from, $to);
        $comparison = ($compareFrom || $compareTo) ? $this->profitLossPeriod($compareFrom, $compareTo) : null;
        if ($comparison) {
            foreach (['net_sales', 'gross_profit', 'operating_result'] as $key) {
                $base = Decimal::of($comparison['summary'][$key], 2);
                $comparison['summary'][$key.'_change_pct'] = $base->isZero() ? null : Decimal::of($current['summary'][$key], 2)->sub($base)->div($base, 4)->mul(100, 2)->toString();
            }
        }

        return ['current' => $current, 'comparison' => $comparison, 'label' => 'Laba Rugi Sederhana / Ringkasan Hasil Operasional'];
    }

    private function profitLossPeriod(?string $from, ?string $to): array
    {
        $period = $this->period($from, $to);
        $sales = Sale::whereIn('sale_status', ['completed', 'partially_returned', 'fully_returned'])->whereBetween('completed_at', [$period['from'], $period['to']]);
        $gross = Decimal::of((string) (clone $sales)->sum('gross_total'), 2);
        $itemDisc = Decimal::of((string) (clone $sales)->sum('item_discount_total'), 2);
        $txDisc = Decimal::of((string) (clone $sales)->sum('transaction_discount_total'), 2);
        $returns = Decimal::of((string) SalesReturn::whereIn('status', ['approved', 'completed'])->whereBetween('approved_at', [$period['from'], $period['to']])->sum('return_total'), 2);
        $grossCogs = Decimal::zero(2);
        foreach ((clone $sales)->with('items')->cursor() as $sale) {
            foreach ($sale->items as $item) {
                $grossCogs = $grossCogs->add($item->cost_total_snapshot);
            }
        }
        $cogsReversal = Decimal::of((string) SalesReturnItem::whereHas('salesReturn', fn ($q) => $q->whereIn('status', ['approved', 'completed'])->whereBetween('approved_at', [$period['from'], $period['to']]))->where('condition', 'resellable')->sum('cost_value'), 2);
        $opex = Decimal::of((string) Expense::whereIn('status', ['posted', 'voided'])->whereDate('expense_date', '>=', $period['from_date'])->whereDate('expense_date', '<=', $period['to_date'])->sum('amount'), 2)
            ->sub((string) Expense::where('status', 'voided')->whereBetween('voided_at', [$period['from'], $period['to']])->sum('amount'));
        $shrinkage = Decimal::of((string) StockOpnameItem::whereHas('opname', fn ($q) => $q->where('status', 'posted')->whereBetween('posted_at', [$period['from'], $period['to']]))->where('variance_quantity', '<', 0)->sum('variance_value'), 2)->mul(-1, 2);

        $netSales = $gross->sub($itemDisc)->sub($txDisc)->sub($returns);
        $netCogs = $grossCogs->sub($cogsReversal);
        $grossProfit = $netSales->sub($netCogs);
        $result = $grossProfit->sub($opex)->sub($shrinkage);
        $margin = $netSales->isZero() ? null : $result->div($netSales, 4)->mul(100, 2)->toString();

        return [
            'period' => $period,
            'summary' => [
                'gross_sales' => $gross->toString(),
                'sales_discounts' => $itemDisc->add($txDisc)->toString(),
                'sales_returns' => $returns->toString(),
                'net_sales' => $netSales->toString(),
                'gross_cogs' => $grossCogs->toString(),
                'cogs_reversal' => $cogsReversal->toString(),
                'net_cogs' => $netCogs->toString(),
                'gross_profit' => $grossProfit->toString(),
                'operating_expenses' => $opex->toString(),
                'shrinkage_loss' => $shrinkage->toString(),
                'operating_result' => $result->toString(),
                'operating_margin' => $margin,
            ],
        ];
    }

    public function cashFlow(?string $from = null, ?string $to = null, int $perPage = 15): array
    {
        $period = $this->period($from, $to);
        $base = CashMovement::query()->whereBetween('occurred_at', [$period['from'], $period['to']]);
        $inflow = Decimal::of((string) (clone $base)->where('direction', 'in')->sum('amount'), 2);
        $outflow = Decimal::of((string) (clone $base)->where('direction', 'out')->sum('amount'), 2);
        $priorIn = Decimal::of((string) CashMovement::where('direction', 'in')->where('occurred_at', '<', $period['from'])->sum('amount'), 2);
        $priorOut = Decimal::of((string) CashMovement::where('direction', 'out')->where('occurred_at', '<', $period['from'])->sum('amount'), 2);
        $opening = $priorIn->sub($priorOut);
        $data = (clone $base)->with(['cashAccount:id,name', 'creator:id,name'])->orderByDesc('occurred_at')->paginate($perPage);

        return [
            'period' => $period,
            'summary' => ['opening' => $opening->toString(), 'inflow' => $inflow->toString(), 'outflow' => $outflow->toString(), 'closing' => $opening->add($inflow)->sub($outflow)->toString()],
            'data' => $data,
        ];
    }

    public function stockOpnameReport(?string $from = null, ?string $to = null, int $perPage = 15): array
    {
        $period = $this->period($from, $to);
        $base = StockOpname::query()->whereBetween('created_at', [$period['from'], $period['to']]);
        $summary = (clone $base)->selectRaw('COUNT(*) as count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as posted', ['posted'])->first();
        $data = (clone $base)->with(['creator:id,name', 'approver:id,name', 'poster:id,name'])->withCount('items')->latest()->paginate($perPage);

        return ['period' => $period, 'summary' => ['count' => (int) ($summary->count ?? 0), 'posted' => (int) ($summary->posted ?? 0)], 'data' => $data];
    }

    public function productPerformance(?string $from = null, ?string $to = null, int $perPage = 15): array
    {
        $period = $this->period($from, $to);
        $sold = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.sale_status', ['completed', 'partially_returned', 'fully_returned'])
            ->whereBetween('sales.completed_at', [$period['from'], $period['to']])
            ->selectRaw('sale_items.product_id, sale_items.product_name_snapshot, sale_items.sku_snapshot, SUM(sale_items.quantity) as quantity, SUM(sale_items.gross_line_total) as gross_sales, SUM(sale_items.line_discount + sale_items.allocated_transaction_discount) as discounts, SUM(sale_items.net_line_total) as net_sales, SUM(sale_items.cost_total_snapshot) as cogs')
            ->groupBy('sale_items.product_id', 'sale_items.product_name_snapshot', 'sale_items.sku_snapshot')
            ->get()
            ->keyBy('product_id');

        $returned = SalesReturnItem::query()
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sales_return_items.sale_item_id')
            ->whereIn('sales_returns.status', ['approved', 'completed'])
            ->whereBetween('sales_returns.approved_at', [$period['from'], $period['to']])
            ->selectRaw('sale_items.product_id, SUM(sales_return_items.quantity) as return_quantity, SUM(sales_return_items.return_value) as returns, SUM(CASE WHEN sales_return_items.condition = ? THEN sales_return_items.cost_value ELSE 0 END) as cogs_reversal', ['resellable'])
            ->groupBy('sale_items.product_id')
            ->get()
            ->keyBy('product_id');

        $rows = $sold->map(function ($row) use ($returned) {
            $returnRow = $returned->get($row->product_id);
            $returns = Decimal::of((string) ($returnRow->returns ?? 0), 2);
            $cogsReversal = Decimal::of((string) ($returnRow->cogs_reversal ?? 0), 2);
            $netSales = Decimal::of((string) $row->net_sales, 2)->sub($returns);
            $cogs = Decimal::of((string) $row->cogs, 2)->sub($cogsReversal);

            return [
                'product_id' => (int) $row->product_id,
                'product_name_snapshot' => $row->product_name_snapshot,
                'sku_snapshot' => $row->sku_snapshot,
                'quantity' => (string) $row->quantity,
                'gross_sales' => Decimal::of((string) $row->gross_sales, 2)->toString(),
                'discounts' => Decimal::of((string) $row->discounts, 2)->toString(),
                'returns' => $returns->toString(),
                'net_sales' => $netSales->toString(),
                'cogs' => $cogs->toString(),
                'gross_profit' => $netSales->sub($cogs)->toString(),
                'return_quantity' => (string) ($returnRow->return_quantity ?? '0.000'),
            ];
        })->sortByDesc('net_sales')->values();

        $page = max(1, (int) request()->input('page', 1));
        $data = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return ['period' => $period, 'data' => $data];
    }

    public function inventoryValuation(?string $asOf = null, int $perPage = 15): array
    {
        $date = $asOf ? Carbon::createFromFormat('Y-m-d', $asOf, self::TIMEZONE)->endOfDay() : Carbon::now(self::TIMEZONE);
        $products = Product::with(['category:id,name', 'unit:id,symbol', 'inventoryBalance'])->orderBy('name')->paginate($perPage);
        $rows = $products->getCollection()->map(function (Product $product) use ($date) {
            $current = $product->inventoryBalance;
            $historicalMovement = StockMovement::where('product_id', $product->id)
                ->where('occurred_at', '<=', $date)
                ->orderByDesc('occurred_at')->orderByDesc('id')->first();
            $historicalQty = $historicalMovement?->balance_after ?? '0.000';
            $historicalCost = $this->historicalCostAt($product->id, $date);

            return [
                'product' => $product,
                'current_quantity' => $current?->quantity_on_hand ?? '0.000',
                'current_cost' => $current?->average_cost ?? '0.00',
                'current_value' => Decimal::of($current?->quantity_on_hand ?? '0', 3)->mul($current?->average_cost ?? '0', 2)->toString(),
                'historical_quantity' => $historicalQty,
                'historical_cost' => $historicalCost->toString(),
                'historical_value' => Decimal::of($historicalQty, 3)->mul($historicalCost, 2)->toString(),
                'as_of' => $date->toIso8601String(),
            ];
        });
        $currentTotal = $products->getCollection()->sum(fn ($p) => (float) (Decimal::of($p->inventoryBalance?->quantity_on_hand ?? '0', 3)->mul($p->inventoryBalance?->average_cost ?? '0', 2)->toString()));
        $historicalTotal = $rows->sum(fn ($row) => (float) $row['historical_value']);

        $paginated = new LengthAwarePaginator($rows, $products->total(), $products->perPage(), $products->currentPage(), ['path' => request()->url(), 'query' => request()->query()]);

        return [
            'as_of' => $date->toDateString(),
            'period' => ['from_date' => $date->toDateString(), 'to_date' => $date->toDateString()],
            'summary' => ['current_value' => Decimal::of((string) $currentTotal, 2)->toString(), 'historical_value' => Decimal::of((string) $historicalTotal, 2)->toString()],
            'data' => $paginated,
        ];
    }

    public function historicalCostAt(int $productId, Carbon $asOf): Decimal
    {
        $movement = StockMovement::where('product_id', $productId)->where('occurred_at', '<=', $asOf)->orderByDesc('occurred_at')->orderByDesc('id')->first();
        if ($movement) {
            return Decimal::of($movement->unit_cost, 2);
        }
        $cost = ProductCostHistory::where('product_id', $productId)->where('effective_at', '<=', $asOf)->orderByDesc('effective_at')->orderByDesc('id')->first();

        return Decimal::of($cost?->cost_after ?? '0', 2);
    }

    public function availableReportRows(User $user, string $type, array $filters = []): array
    {
        $limit = max(1, (int) config('reports.row_limit', 5000));
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $period = $this->period($from, $to);

        return match ($type) {
            'sales' => $this->salesRows($user, $period, $limit),
            'purchases' => $this->purchaseRows($period, $limit),
            'returns' => $this->returnRows($period, $limit),
            'expenses' => $this->expenseRows($period, $limit),
            'inventory' => $this->inventoryRows($period, $limit),
            'inventory-valuation' => $this->inventoryValuationRows($period, $limit),
            'profit-loss' => $this->profitLossExport($period),
            'cash-flow' => $this->cashFlowRows($period, $limit),
            'stock-opnames' => $this->stockOpnameRows($period, $limit),
            'product-performance' => $this->productPerformanceRows($period, $limit),
            default => throw new \InvalidArgumentException('Jenis laporan tidak mendukung ekspor.'),
        };
    }

    private function salesRows(User $user, array $period, int $limit): array
    {
        $report = $this->salesReport($user, $period['from_date'], $period['to_date'], $limit);
        $report['columns'] = [
            ['key' => 'transaction_number', 'label' => 'No Transaksi'], ['key' => 'cashier', 'label' => 'Kasir'],
            ['key' => 'sale_status', 'label' => 'Status'], ['key' => 'grand_total', 'label' => 'Total'],
            ['key' => 'completed_at', 'label' => 'Tanggal'],
        ];
        $report['summary_labels'] = ['transactions' => 'Transaksi', 'gross' => 'Gross Sales', 'item_discount' => 'Diskon Item', 'transaction_discount' => 'Diskon Transaksi', 'net' => 'Net Sales'];
        $report['rows'] = $report['data']->getCollection()->map(fn ($sale) => [
            'transaction_number' => $sale->transaction_number, 'cashier' => $sale->cashier?->name, 'sale_status' => $sale->sale_status,
            'grand_total' => $sale->grand_total, 'completed_at' => $sale->completed_at?->toDateTimeString(),
        ]);

        return $report;
    }

    private function purchaseRows(array $period, int $limit): array
    {
        $report = $this->purchaseReport($period['from_date'], $period['to_date'], $limit);
        $report['columns'] = [['key' => 'purchase_number', 'label' => 'No PO'], ['key' => 'supplier', 'label' => 'Supplier'], ['key' => 'status', 'label' => 'Status'], ['key' => 'payable_total', 'label' => 'Payable'], ['key' => 'order_date', 'label' => 'Tanggal']];
        $report['summary_labels'] = ['count' => 'Jumlah PO', 'payable' => 'Nilai PO', 'paid' => 'Dibayar', 'outstanding' => 'Outstanding'];
        $report['rows'] = $report['data']->getCollection()->map(fn ($p) => ['purchase_number' => $p->purchase_number, 'supplier' => $p->supplier?->name, 'status' => $p->status, 'payable_total' => $p->payable_total, 'order_date' => $p->order_date?->toDateString()]);

        return $report;
    }

    private function returnRows(array $period, int $limit): array
    {
        $report = $this->returnReport($period['from_date'], $period['to_date'], $limit);
        $report['columns'] = [['key' => 'return_number', 'label' => 'No Retur'], ['key' => 'sale', 'label' => 'Penjualan'], ['key' => 'status', 'label' => 'Status'], ['key' => 'return_total', 'label' => 'Nilai'], ['key' => 'approved_at', 'label' => 'Approved']];
        $report['summary_labels'] = ['count' => 'Jumlah Retur', 'total' => 'Total Retur', 'cogs_reversal' => 'COGS Reversal', 'damaged_loss_informational' => 'Damaged Loss (informasi)'];
        $report['rows'] = $report['data']->getCollection()->map(fn ($r) => ['return_number' => $r->return_number, 'sale' => $r->sale?->transaction_number, 'status' => $r->status, 'return_total' => $r->return_total, 'approved_at' => $r->approved_at?->toDateTimeString()]);

        return $report;
    }

    private function expenseRows(array $period, int $limit): array
    {
        $report = $this->expenseReport($period['from_date'], $period['to_date'], $limit);
        $report['columns'] = [['key' => 'expense_number', 'label' => 'No Expense'], ['key' => 'category', 'label' => 'Kategori'], ['key' => 'status', 'label' => 'Status'], ['key' => 'amount', 'label' => 'Jumlah'], ['key' => 'expense_date', 'label' => 'Tanggal Expense']];
        $report['summary_labels'] = ['gross' => 'Gross', 'void_reversal' => 'Void', 'net' => 'Neto'];
        $report['rows'] = $report['data']->getCollection()->map(fn ($e) => ['expense_number' => $e->expense_number, 'category' => $e->category?->name, 'status' => $e->status, 'amount' => $e->amount, 'expense_date' => $e->expense_date?->toDateString()]);

        return $report;
    }

    private function inventoryRows(array $period, int $limit): array
    {
        $report = $this->inventoryReport($period['from_date'], $period['to_date'], $limit);
        $report['columns'] = [['key' => 'name', 'label' => 'Produk'], ['key' => 'sku', 'label' => 'SKU'], ['key' => 'category', 'label' => 'Kategori'], ['key' => 'quantity_on_hand', 'label' => 'On Hand'], ['key' => 'average_cost', 'label' => 'HPP'], ['key' => 'stock_value', 'label' => 'Nilai Stok']];
        $report['summary_labels'] = ['current_stock_value' => 'Nilai Stok Saat Ini', 'movement_count' => 'Jumlah Movement'];
        $report['rows'] = $report['data']->getCollection()->map(fn ($p) => ['name' => $p->name, 'sku' => $p->sku, 'category' => $p->category?->name, 'quantity_on_hand' => $p->quantity_on_hand, 'average_cost' => $p->average_cost, 'stock_value' => (float) $p->quantity_on_hand * (float) $p->average_cost]);

        return $report;
    }

    private function inventoryValuationRows(array $period, int $limit): array
    {
        $report = $this->inventoryValuation($period['to_date'], $limit);
        $report['columns'] = [
            ['key' => 'product', 'label' => 'Produk'], ['key' => 'sku', 'label' => 'SKU'],
            ['key' => 'current_quantity', 'label' => 'Qty Current'], ['key' => 'current_cost', 'label' => 'HPP Current'],
            ['key' => 'current_value', 'label' => 'Nilai Current'],
            ['key' => 'historical_quantity', 'label' => 'Qty Historis'], ['key' => 'historical_cost', 'label' => 'HPP Historis'],
            ['key' => 'historical_value', 'label' => 'Nilai Historis'],
        ];
        $report['summary_labels'] = ['current_value' => 'Nilai Current', 'historical_value' => 'Nilai Historis'];
        $report['rows'] = $report['data']->getCollection()->map(fn ($row) => [
            'product' => $row['product']->name, 'sku' => $row['product']->sku,
            'current_quantity' => $row['current_quantity'], 'current_cost' => $row['current_cost'], 'current_value' => $row['current_value'],
            'historical_quantity' => $row['historical_quantity'], 'historical_cost' => $row['historical_cost'], 'historical_value' => $row['historical_value'],
        ]);

        return $report;
    }

    private function profitLossExport(array $period): array
    {
        $report = $this->profitLoss($period['from_date'], $period['to_date']);
        $labels = ['gross_sales' => 'Gross Sales', 'sales_discounts' => 'Sales Discounts', 'sales_returns' => 'Sales Returns', 'net_sales' => 'Net Sales', 'gross_cogs' => 'Gross COGS', 'cogs_reversal' => 'COGS Reversal', 'net_cogs' => 'Net COGS', 'gross_profit' => 'Gross Profit', 'operating_expenses' => 'Operating Expenses', 'shrinkage_loss' => 'Shrinkage Loss', 'operating_result' => 'Simplified Operating Result', 'operating_margin' => 'Operating Margin'];

        return [
            'period' => $period, 'summary' => $report['current']['summary'], 'summary_labels' => $labels,
            'columns' => [['key' => 'label', 'label' => 'Komponen'], ['key' => 'value', 'label' => 'Nilai']],
            'rows' => collect($labels)->map(fn ($label, $key) => ['label' => $label, 'value' => $report['current']['summary'][$key] ?? '0.00']),
        ];
    }

    private function cashFlowRows(array $period, int $limit): array
    {
        $report = $this->cashFlow($period['from_date'], $period['to_date'], $limit);
        $report['columns'] = [['key' => 'occurred_at', 'label' => 'Waktu'], ['key' => 'account', 'label' => 'Akun'], ['key' => 'movement_type', 'label' => 'Tipe'], ['key' => 'direction', 'label' => 'Arah'], ['key' => 'amount', 'label' => 'Jumlah']];
        $report['summary_labels'] = ['opening' => 'Saldo Awal', 'inflow' => 'Kas Masuk', 'outflow' => 'Kas Keluar', 'closing' => 'Saldo Akhir'];
        $report['rows'] = $report['data']->getCollection()->map(fn ($m) => ['occurred_at' => $m->occurred_at?->toDateTimeString(), 'account' => $m->cashAccount?->name, 'movement_type' => $m->movement_type, 'direction' => $m->direction, 'amount' => $m->amount]);

        return $report;
    }

    private function stockOpnameRows(array $period, int $limit): array
    {
        $report = $this->stockOpnameReport($period['from_date'], $period['to_date'], $limit);
        $report['columns'] = [['key' => 'opname_number', 'label' => 'No'], ['key' => 'items_count', 'label' => 'Jumlah Item'], ['key' => 'status', 'label' => 'Status'], ['key' => 'created_at', 'label' => 'Dibuat']];
        $report['summary_labels'] = ['count' => 'Jumlah Sesi', 'posted' => 'Posted'];
        $report['rows'] = $report['data']->getCollection()->map(fn ($o) => ['opname_number' => $o->opname_number, 'items_count' => $o->items_count, 'status' => $o->status, 'created_at' => $o->created_at?->toDateTimeString()]);

        return $report;
    }

    private function productPerformanceRows(array $period, int $limit): array
    {
        $report = $this->productPerformance($period['from_date'], $period['to_date'], $limit);
        $report['columns'] = [['key' => 'product_name_snapshot', 'label' => 'Produk'], ['key' => 'quantity', 'label' => 'Qty'], ['key' => 'gross_sales', 'label' => 'Gross Sales'], ['key' => 'discounts', 'label' => 'Diskon'], ['key' => 'returns', 'label' => 'Retur'], ['key' => 'net_sales', 'label' => 'Net Sales'], ['key' => 'cogs', 'label' => 'COGS'], ['key' => 'gross_profit', 'label' => 'Gross Profit']];
        $report['summary_labels'] = [];
        $report['rows'] = $report['data']->getCollection()->map(fn ($p) => [
            'product_name_snapshot' => $p['product_name_snapshot'], 'quantity' => $p['quantity'], 'gross_sales' => $p['gross_sales'],
            'discounts' => $p['discounts'], 'returns' => $p['returns'], 'net_sales' => $p['net_sales'], 'cogs' => $p['cogs'], 'gross_profit' => $p['gross_profit'],
        ]);

        return $report;
    }
}
