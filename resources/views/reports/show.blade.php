@extends('layouts.app')

@section('title', $title)

@php
    $period = $payload['period'] ?? null;
    $summary = $payload['summary'] ?? [];
    $data = $payload['data'] ?? null;
    $money = fn ($value) => 'Rp '.number_format((float) ($value ?? 0), 2, ',', '.');
    $summaryLabels = [
        'transactions' => 'Transaksi', 'gross' => 'Gross', 'item_discount' => 'Diskon Item', 'transaction_discount' => 'Diskon Transaksi', 'net' => 'Neto',
        'count' => 'Jumlah', 'payable' => 'Nilai PO', 'paid' => 'Dibayar', 'outstanding' => 'Outstanding',
        'total' => 'Total Retur', 'cogs_reversal' => 'COGS Reversal', 'damaged_loss_informational' => 'Damaged Loss (informasi)', 'void_reversal' => 'Void', 'net_expense' => 'Neto',
        'current_stock_value' => 'Nilai Stok Saat Ini', 'movement_count' => 'Jumlah Movement',
        'opening' => 'Saldo Awal', 'inflow' => 'Kas Masuk', 'outflow' => 'Kas Keluar', 'closing' => 'Saldo Akhir',
        'posted' => 'Posted',
    ];
    $plLabels = [
        'gross_sales' => 'Gross Sales', 'sales_discounts' => 'Sales Discounts', 'sales_returns' => 'Sales Returns', 'net_sales' => 'Net Sales',
        'gross_cogs' => 'Gross COGS', 'cogs_reversal' => 'COGS Reversal', 'net_cogs' => 'Net COGS', 'gross_profit' => 'Gross Profit',
        'operating_expenses' => 'Operating Expenses', 'shrinkage_loss' => 'Shrinkage Loss', 'operating_result' => 'Simplified Operating Result', 'operating_margin' => 'Operating Margin',
    ];
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm text-sky-400">&larr; Dashboard</a>
            <h1 class="mt-2 text-2xl font-bold text-white">{{ $title }}</h1>
            @if($period)<p class="text-sm text-slate-400">Periode {{ \Carbon\Carbon::parse($period['from_date'])->format('d/m/Y') }} &mdash; {{ \Carbon\Carbon::parse($period['to_date'])->format('d/m/Y') }} (Asia/Jakarta, inklusif)</p>@endif
        </div>
        <form class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-700 bg-slate-800 p-3 text-sm">
            @if($type === 'profit-loss')
                <label class="text-slate-300">Dari<input type="date" name="from" value="{{ request('from') }}" class="ml-1 rounded border-slate-600 bg-slate-900 text-white"></label>
                <label class="text-slate-300">Sampai<input type="date" name="to" value="{{ request('to') }}" class="ml-1 rounded border-slate-600 bg-slate-900 text-white"></label>
                <label class="text-slate-300">Banding dari<input type="date" name="compare_from" value="{{ request('compare_from') }}" class="ml-1 rounded border-slate-600 bg-slate-900 text-white"></label>
                <label class="text-slate-300">Banding sampai<input type="date" name="compare_to" value="{{ request('compare_to') }}" class="ml-1 rounded border-slate-600 bg-slate-900 text-white"></label>
            @else
                <label class="text-slate-300">Dari<input type="date" name="from" value="{{ request('from') }}" class="ml-1 rounded border-slate-600 bg-slate-900 text-white"></label>
                <label class="text-slate-300">Sampai<input type="date" name="to" value="{{ request('to') }}" class="ml-1 rounded border-slate-600 bg-slate-900 text-white"></label>
            @endif
            <button class="rounded-lg bg-sky-600 px-3 py-2 font-semibold text-white">Terapkan</button>
        </form>
    </div>

    @if($errors->any())<div class="rounded-lg border border-rose-700 bg-rose-950/40 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>@endif

    @if($type === 'profit-loss')
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <div class="mb-2 flex items-center justify-between"><h2 class="font-semibold text-white">{{ $payload['label'] }}</h2><span class="text-xs text-slate-400">Bukan laporan akuntansi formal</span></div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-700">
                @foreach($plLabels as $key => $label)
                    @php($value = $payload['current']['summary'][$key] ?? null)
                    <tr><td class="py-2 {{ in_array($key, ['net_sales', 'gross_profit', 'operating_result'], true) ? 'font-semibold text-white' : 'text-slate-300' }}">{{ $label }}</td><td class="py-2 text-right {{ in_array($key, ['net_sales', 'gross_profit', 'operating_result'], true) ? 'font-semibold text-white' : 'text-slate-200' }}">@if($key === 'operating_margin'){{ $value === null ? '—' : $value.'%' }}@else{{ $money($value) }}@endif</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($summary as $key => $value)
                <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                    <div class="text-xs uppercase text-slate-400">{{ $summaryLabels[$key] ?? str_replace('_', ' ', $key) }}</div>
                    <div class="mt-1 text-lg font-bold text-white">{{ is_numeric($value) && ! in_array($key, ['transactions', 'count', 'movement_count', 'posted'], true) ? $money($value) : $value }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if($data)
        <section class="overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-700 text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400">
                    @switch($type)
                        @case('sales')
                            <tr><th class="px-4 py-3">No</th><th class="px-4 py-3">Kasir</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Tanggal</th></tr>
                            @break
                        @case('purchases')
                            <tr><th class="px-4 py-3">No PO</th><th class="px-4 py-3">Supplier</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Payable</th><th class="px-4 py-3">Tanggal</th></tr>
                            @break
                        @case('returns')
                            <tr><th class="px-4 py-3">No Retur</th><th class="px-4 py-3">Penjualan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Nilai</th><th class="px-4 py-3">Approved</th></tr>
                            @break
                        @case('expenses')
                            <tr><th class="px-4 py-3">No Expense</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Jumlah</th><th class="px-4 py-3">Tanggal</th></tr>
                            @break
                        @case('inventory')
                            <tr><th class="px-4 py-3">Produk</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3">On Hand</th><th class="px-4 py-3">HPP</th><th class="px-4 py-3">Nilai</th></tr>
                            @break
                        @case('cash-flow')
                            <tr><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Akun</th><th class="px-4 py-3">Tipe</th><th class="px-4 py-3">Arah</th><th class="px-4 py-3">Jumlah</th></tr>
                            @break
                        @case('stock-opnames')
                            <tr><th class="px-4 py-3">No</th><th class="px-4 py-3">Produk</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Dibuat</th></tr>
                            @break
                        @case('product-performance')
                            <tr><th class="px-4 py-3">Produk</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">Net Sales</th><th class="px-4 py-3">Diskon</th><th class="px-4 py-3">COGS</th></tr>
                            @break
                    @endswitch
                    </thead>
                    <tbody class="divide-y divide-slate-700 text-slate-200">
                    @foreach($data as $row)
                        @switch($type)
                            @case('sales')
                                <tr><td class="px-4 py-3 font-mono"><a href="{{ route('sales.show', $row) }}" class="text-sky-400">{{ $row->transaction_number }}</a></td><td class="px-4 py-3">{{ $row->cashier?->name }}</td><td class="px-4 py-3">{{ $row->sale_status }}</td><td class="px-4 py-3">{{ $money($row->grand_total) }}</td><td class="px-4 py-3">{{ $row->completed_at?->format('d/m/Y H:i') }}</td></tr>
                                @break
                            @case('purchases')
                                <tr><td class="px-4 py-3 font-mono">{{ $row->purchase_number }}</td><td class="px-4 py-3">{{ $row->supplier?->name }}</td><td class="px-4 py-3">{{ $row->status }}</td><td class="px-4 py-3">{{ $money($row->payable_total) }}</td><td class="px-4 py-3">{{ $row->order_date?->format('d/m/Y') }}</td></tr>
                                @break
                            @case('returns')
                                <tr><td class="px-4 py-3 font-mono">{{ $row->return_number }}</td><td class="px-4 py-3">{{ $row->sale?->transaction_number }}</td><td class="px-4 py-3">{{ $row->status }}</td><td class="px-4 py-3">{{ $money($row->return_total) }}</td><td class="px-4 py-3">{{ $row->approved_at?->format('d/m/Y H:i') }}</td></tr>
                                @break
                            @case('expenses')
                                <tr><td class="px-4 py-3 font-mono">{{ $row->expense_number }}</td><td class="px-4 py-3">{{ $row->category?->name }}</td><td class="px-4 py-3">{{ $row->status }}</td><td class="px-4 py-3">{{ $money($row->amount) }}</td><td class="px-4 py-3">{{ $row->expense_date?->format('d/m/Y') }}</td></tr>
                                @break
                            @case('inventory')
                                <tr><td class="px-4 py-3">{{ $row->name }}<div class="text-xs text-slate-500 font-mono">{{ $row->sku }}</div></td><td class="px-4 py-3">{{ $row->category?->name }}</td><td class="px-4 py-3">{{ $row->quantity_on_hand }} {{ $row->unit?->symbol }}</td><td class="px-4 py-3">{{ $money($row->average_cost) }}</td><td class="px-4 py-3">{{ $money((float) $row->quantity_on_hand * (float) $row->average_cost) }}</td></tr>
                                @break
                            @case('cash-flow')
                                <tr><td class="px-4 py-3">{{ $row->occurred_at?->format('d/m/Y H:i') }}</td><td class="px-4 py-3">{{ $row->cashAccount?->name }}</td><td class="px-4 py-3">{{ $row->movement_type }}</td><td class="px-4 py-3">{{ $row->direction }}</td><td class="px-4 py-3 {{ $row->direction === 'in' ? 'text-emerald-300' : 'text-rose-300' }}">{{ $money($row->amount) }}</td></tr>
                                @break
                            @case('stock-opnames')
                                <tr><td class="px-4 py-3 font-mono">{{ $row->opname_number }}</td><td class="px-4 py-3">{{ $row->items_count }}</td><td class="px-4 py-3">{{ $row->status }}</td><td class="px-4 py-3">{{ $row->created_at?->format('d/m/Y H:i') }}</td></tr>
                                @break
                            @case('product-performance')
                                <tr><td class="px-4 py-3">{{ $row->product_name_snapshot }}</td><td class="px-4 py-3">{{ $row->quantity }}</td><td class="px-4 py-3">{{ $money($row->net_sales) }}</td><td class="px-4 py-3">{{ $money($row->discounts) }}</td><td class="px-4 py-3">{{ $money($row->cogs) }}</td></tr>
                                @break
                        @endswitch
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($data->count() === 0)<div class="px-4 py-10 text-center text-sm text-slate-400">Tidak ada data pada filter ini.</div>@endif
        </section>
        {{ $data->withQueryString()->links() }}
    @endif

    @if($type === 'profit-loss' && ! empty($payload['comparison']))
        <section class="rounded-xl border border-slate-700 bg-slate-800 p-5 text-sm">
            <h2 class="mb-2 font-semibold text-white">Perbandingan Periode</h2>
            <p class="text-slate-300">Net Sales {{ $payload['comparison']['period']['from_date'] }} - {{ $payload['comparison']['period']['to_date'] }}</p>
            <p class="mt-1 text-slate-300">Perubahan Net Sales: {{ $payload['comparison']['summary']['net_sales_change_pct'] === null ? '—' : $payload['comparison']['summary']['net_sales_change_pct'].'%' }} &middot; Gross Profit: {{ $payload['comparison']['summary']['gross_profit_change_pct'] === null ? '—' : $payload['comparison']['summary']['gross_profit_change_pct'].'%' }} &middot; Operating Result: {{ $payload['comparison']['summary']['operating_result_change_pct'] === null ? '—' : $payload['comparison']['summary']['operating_result_change_pct'].'%' }}</p>
        </section>
    @endif
</div>
@endsection
