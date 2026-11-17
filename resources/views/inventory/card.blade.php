@extends('layouts.app')

@section('title', 'Kartu Stok - ' . $product->name)

@section('content')
<div class="space-y-6">
    <!-- Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1">
                    <li><a href="{{ route('dashboard') }}" class="hover:text-slate-700">Beranda</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li><a href="{{ route('inventory.index') }}" class="hover:text-slate-700">Persediaan & Stok</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li class="text-slate-800 font-medium">Kartu Stok: {{ $product->name }}</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Kartu Stok Barang</h1>
            <p class="text-sm text-slate-500 mt-0.5">Jejak kronologis mutasi masuk/keluar, saldo berjalan, dan rekonsiliasi buku besar.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.index') }}" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-2xs">
                ← Kembali ke Daftar Stok
            </a>
        </div>
    </div>

    <!-- Product Summary & Reconciliation Status Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Product Profile Card -->
        <div class="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-block px-2.5 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700 mb-1">
                        {{ $product->category?->name ?? 'Tanpa Kategori' }}
                    </span>
                    <h2 class="text-xl font-bold text-slate-900">{{ $product->name }}</h2>
                    <div class="text-xs text-slate-500 font-mono flex items-center gap-3 mt-1">
                        <span>SKU: <strong class="text-slate-700">{{ $product->sku }}</strong></span>
                        @if($product->barcode)
                            <span>•</span>
                            <span>Barcode: <strong class="text-slate-700">{{ $product->barcode }}</strong></span>
                        @endif
                        <span>•</span>
                        <span>Satuan: <strong class="text-slate-700">{{ $product->unit?->name }} ({{ $product->unit?->symbol }})</strong></span>
                    </div>
                </div>

                <div>
                    @if($product->stock_status === 'available')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            ● Tersedia
                        </span>
                    @elseif($product->stock_status === 'low_stock')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                            ▲ Menipis (<= Min)
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                            ✕ Habis (0)
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-slate-100 text-center">
                <div class="bg-slate-50/80 p-3 rounded-lg">
                    <p class="text-[11px] text-slate-500 font-medium uppercase">Fisik (On-Hand)</p>
                    <p class="text-lg font-bold text-slate-900 mt-0.5">
                        {{ \App\Support\MoneyCalculator::formatQuantity($product->quantity_on_hand, $product->unit?->decimal_precision ?? 0) }}
                    </p>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-lg">
                    <p class="text-[11px] text-slate-500 font-medium uppercase">Cadangan (Reserved)</p>
                    <p class="text-lg font-bold text-amber-700 mt-0.5">
                        {{ \App\Support\MoneyCalculator::formatQuantity($product->quantity_reserved, $product->unit?->decimal_precision ?? 0) }}
                    </p>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-lg">
                    <p class="text-[11px] text-slate-500 font-medium uppercase">Tersedia</p>
                    <p class="text-lg font-bold {{ (float) $product->available_quantity > 0 ? 'text-emerald-700' : 'text-rose-700' }} mt-0.5">
                        {{ \App\Support\MoneyCalculator::formatQuantity($product->available_quantity, $product->unit?->decimal_precision ?? 0) }}
                    </p>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-lg">
                    <p class="text-[11px] text-slate-500 font-medium uppercase">HPP Rata-Rata (MAC)</p>
                    <p class="text-lg font-mono font-bold text-slate-800 mt-0.5">
                        {{ \App\Support\MoneyCalculator::formatIdr($product->average_cost) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Reconciliation Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-bold text-slate-900">Rekonsiliasi Buku Besar</h3>
                    @if($reconciliation['is_consistent'])
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                            100% Konsisten
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800">
                            Selisih Terdeteksi!
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mb-3">Memverifikasi kesesuaian antara saldo proyeksi (balance) dengan akumulasi buku besar audit append-only.</p>

                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Saldo Fisik Aktif:</span>
                        <strong class="font-mono text-slate-800">{{ $reconciliation['balance_on_hand'] }} {{ $product->unit?->symbol }}</strong>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Total Masuk (Ledger):</span>
                        <span class="font-mono text-emerald-700">+{{ $reconciliation['total_in'] }} {{ $product->unit?->symbol }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Total Keluar (Ledger):</span>
                        <span class="font-mono text-rose-700">-{{ $reconciliation['total_out'] }} {{ $product->unit?->symbol }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Saldo Net Ledger:</span>
                        <strong class="font-mono text-slate-800">{{ $reconciliation['ledger_on_hand'] }} {{ $product->unit?->symbol }}</strong>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-500">Selisih (Difference):</span>
                        <strong class="font-mono {{ $reconciliation['is_consistent'] ? 'text-slate-700' : 'text-rose-700' }}">
                            {{ $reconciliation['difference'] }}
                        </strong>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 mt-2">
                <p class="text-[11px] text-slate-400 italic">Audit trail dilindungi oleh aturan append-only (tidak dapat diedit/dihapus).</p>
            </div>
        </div>
    </div>

    <!-- Filters Toolbar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <form method="GET" action="{{ route('inventory.card', $product) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="from_date" class="block text-xs font-medium text-slate-700 mb-1">Dari Tanggal</label>
                <input type="date" 
                       name="from_date" 
                       id="from_date" 
                       value="{{ request('from_date') }}" 
                       class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
            </div>

            <div>
                <label for="to_date" class="block text-xs font-medium text-slate-700 mb-1">Sampai Tanggal</label>
                <input type="date" 
                       name="to_date" 
                       id="to_date" 
                       value="{{ request('to_date') }}" 
                       class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
            </div>

            <div>
                <label for="movement_type" class="block text-xs font-medium text-slate-700 mb-1">Jenis Mutasi</label>
                <select name="movement_type" id="movement_type" class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
                    <option value="">Semua Jenis Mutasi</option>
                    <option value="IN_INITIAL" {{ request('movement_type') === 'IN_INITIAL' ? 'selected' : '' }}>Saldo Awal (IN_INITIAL)</option>
                    <option value="IN_PURCHASE" {{ request('movement_type') === 'IN_PURCHASE' ? 'selected' : '' }}>Penerimaan PO (IN_PURCHASE)</option>
                    <option value="IN_RETURN_RESELLABLE" {{ request('movement_type') === 'IN_RETURN_RESELLABLE' ? 'selected' : '' }}>Retur Penjualan (IN_RETURN_RESELLABLE)</option>
                    <option value="IN_ADJUSTMENT" {{ request('movement_type') === 'IN_ADJUSTMENT' ? 'selected' : '' }}>Penyesuaian Masuk (IN_ADJUSTMENT)</option>
                    <option value="OUT_SALE" {{ request('movement_type') === 'OUT_SALE' ? 'selected' : '' }}>Penjualan Kasir (OUT_SALE)</option>
                    <option value="OUT_RETURN_SUPPLIER" {{ request('movement_type') === 'OUT_RETURN_SUPPLIER' ? 'selected' : '' }}>Retur Pemasok (OUT_RETURN_SUPPLIER)</option>
                    <option value="OUT_ADJUSTMENT" {{ request('movement_type') === 'OUT_ADJUSTMENT' ? 'selected' : '' }}>Penyesuaian Keluar (OUT_ADJUSTMENT)</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-xs">
                    Filter Mutasi
                </button>
                @if(request()->hasAny(['from_date', 'to_date', 'movement_type']))
                    <a href="{{ route('inventory.card', $product) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Stock Movements Table (Kartu Stok) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/75 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                        <th class="py-3 px-4">Waktu Mutasi</th>
                        <th class="py-3 px-4">Jenis Mutasi</th>
                        <th class="py-3 px-4">No. Dokumen / Referensi</th>
                        <th class="py-3 px-4 text-right">Masuk (+)</th>
                        <th class="py-3 px-4 text-right">Keluar (-)</th>
                        <th class="py-3 px-4 text-right">Sebelum</th>
                        <th class="py-3 px-4 text-right">Sesudah</th>
                        <th class="py-3 px-4 text-right">Biaya Satuan</th>
                        <th class="py-3 px-4">Petugas</th>
                        <th class="py-3 px-4">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $mov)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3 px-4 text-xs font-mono text-slate-700 whitespace-nowrap">
                                {{ $mov->occurred_at ? $mov->occurred_at->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if((float)$mov->quantity_in > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        {{ $mov->movement_type_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-50 text-rose-800 border border-rose-200">
                                        {{ $mov->movement_type_label }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-slate-700">
                                {{ $mov->source_number ?? ($mov->source_type . ' #' . $mov->source_id) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-medium text-emerald-700">
                                @if((float)$mov->quantity_in > 0)
                                    +{{ \App\Support\MoneyCalculator::formatQuantity($mov->quantity_in, $product->unit?->decimal_precision ?? 0) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-medium text-rose-700">
                                @if((float)$mov->quantity_out > 0)
                                    -{{ \App\Support\MoneyCalculator::formatQuantity($mov->quantity_out, $product->unit?->decimal_precision ?? 0) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs text-slate-500">
                                {{ \App\Support\MoneyCalculator::formatQuantity($mov->balance_before, $product->unit?->decimal_precision ?? 0) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs font-bold text-slate-900">
                                {{ \App\Support\MoneyCalculator::formatQuantity($mov->balance_after, $product->unit?->decimal_precision ?? 0) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs text-slate-700">
                                {{ \App\Support\MoneyCalculator::formatIdr($mov->unit_cost) }}
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-600">
                                {{ $mov->creator?->name ?? 'System' }}
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-500 max-w-xs truncate" title="{{ $mov->notes }}">
                                {{ $mov->notes ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-10 text-center text-slate-500">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-sm font-semibold text-slate-700">Belum ada riwayat mutasi stok untuk produk ini</p>
                                <p class="text-xs text-slate-400 mt-0.5">Seluruh pergerakan stok dari pembelian, penjualan, retur, atau stok opname akan tercatat otomatis di sini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($movements->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
