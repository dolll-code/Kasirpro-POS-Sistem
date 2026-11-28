<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\InventoryBalance;
use App\Models\Product;
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
use Illuminate\Support\Collection;

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

        return ['from' => $start, 'to' => $end, 'from_date' => $start->toDateString(), 'to_date' => $end->toDateString()];
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

        return [
            'can_view_all' => $canViewAll,
            'sales' => $sales['summary'],
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
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
        $data = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.sale_status', ['completed', 'partially_returned', 'fully_returned'])
            ->whereBetween('sales.completed_at', [$period['from'], $period['to']])
            ->selectRaw('sale_items.product_id, sale_items.product_name_snapshot, SUM(sale_items.quantity) as quantity, SUM(sale_items.net_line_total) as net_sales, SUM(sale_items.line_discount + sale_items.allocated_transaction_discount) as discounts, SUM(sale_items.cost_total_snapshot) as cogs')
            ->groupBy('sale_items.product_id', 'sale_items.product_name_snapshot')
            ->orderByDesc('net_sales')
            ->paginate($perPage);

        return ['period' => $period, 'data' => $data];
    }

    public function collectionForExport(string $type, array $filters): Collection
    {
        return match ($type) {
            'sales' => Sale::whereBetween('completed_at', [$filters['from'], $filters['to']])->with('cashier')->get(),
            default => collect(),
        };
    }
}
