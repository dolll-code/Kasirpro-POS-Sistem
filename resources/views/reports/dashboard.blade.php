@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Dashboard Operasional</h1>
        <p class="text-sm text-slate-400">Ringkasan hari ini, {{ now('Asia/Jakarta')->format('d F Y') }} (Asia/Jakarta).</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <div class="text-xs uppercase text-slate-400">Transaksi Hari Ini</div>
            <div class="mt-2 text-2xl font-bold text-white">{{ $sales['transactions'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <div class="text-xs uppercase text-slate-400">Penjualan Netto</div>
            <div class="mt-2 text-2xl font-bold text-emerald-300">Rp {{ number_format((float) $sales['net'], 2, ',', '.') }}</div>
        </div>
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <div class="text-xs uppercase text-slate-400">Stok Menipis</div>
            <div class="mt-2 text-2xl font-bold text-amber-300">{{ $low_stock }}</div>
        </div>
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <div class="text-xs uppercase text-slate-400">Stok Habis</div>
            <div class="mt-2 text-2xl font-bold text-rose-300">{{ $out_of_stock }}</div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-700 bg-slate-800">
            <div class="border-b border-slate-700 px-5 py-4"><h2 class="font-semibold text-white">Transaksi Terbaru</h2><p class="text-xs text-slate-400">{{ $can_view_all ? 'Seluruh kasir' : 'Hanya transaksi Anda' }}</p></div>
            <div class="divide-y divide-slate-700">
                @forelse($recent_sales as $sale)
                    <div class="flex items-center justify-between px-5 py-3 text-sm">
                        <div><a href="{{ route('sales.show', $sale) }}" class="font-mono text-sky-400">{{ $sale->transaction_number }}</a><div class="text-xs text-slate-400">{{ $sale->cashier?->name }} &middot; {{ $sale->created_at?->format('d/m/H H:i') }}</div></div>
                        <div class="text-right"><div class="text-white">Rp {{ number_format((float) $sale->grand_total, 2, ',', '.') }}</div><div class="text-xs text-slate-400">{{ $sale->sale_status }}</div></div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-sm text-slate-400">Belum ada transaksi.</div>
                @endforelse
            </div>
        </section>
        <section class="rounded-xl border border-slate-700 bg-slate-800">
            <div class="border-b border-slate-700 px-5 py-4"><h2 class="font-semibold text-white">Aktivitas Kas Terbaru</h2></div>
            <div class="divide-y divide-slate-700">
                @forelse($recent_cash as $movement)
                    <div class="flex items-center justify-between px-5 py-3 text-sm">
                        <div><div class="text-white">{{ $movement->cashAccount?->name }}</div><div class="text-xs text-slate-400">{{ $movement->movement_type }} &middot; {{ $movement->occurred_at?->format('d/m/Y H:i') }}</div></div>
                        <div class="{{ $movement->direction === 'in' ? 'text-emerald-300' : 'text-rose-300' }}">{{ $movement->direction === 'in' ? '+' : '-' }} Rp {{ number_format((float) $movement->amount, 2, ',', '.') }}</div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-sm text-slate-400">Belum ada aktivitas kas.</div>
                @endforelse
            </div>
        </section>
    </div>

    @if(auth()->user()->hasPermission('reports.view_all') || auth()->user()->hasPermission('reports.view_limited'))
        <section class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="mb-3 font-semibold text-white">Laporan</h2>
            <div class="flex flex-wrap gap-2 text-sm">
                @foreach(['sales' => 'Penjualan', 'purchases' => 'Pembelian', 'returns' => 'Retur', 'expenses' => 'Biaya', 'inventory' => 'Persediaan', 'profit-loss' => 'Laba Rugi', 'cash-flow' => 'Arus Kas', 'stock-opnames' => 'Opname', 'product-performance' => 'Performa Produk'] as $key => $label)
                    <a href="{{ route('reports.show', $key) }}" class="rounded-lg border border-slate-600 px-3 py-2 text-slate-200 hover:bg-slate-700">{{ $label }}</a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
