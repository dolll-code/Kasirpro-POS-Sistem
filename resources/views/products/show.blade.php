@extends('layouts.app', [
    'title' => 'Detail Produk: ' . $product->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Produk' => route('products.index'),
        $product->name => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header with Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $product->name }}</h1>
                @if($product->is_active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                        Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                        Nonaktif
                    </span>
                @endif

                @if(!$product->isSellable() && $product->is_active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800" title="Kategori atau satuan produk sedang dinonaktifkan">
                        Tertahan (Kategori/Satuan Nonaktif)
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mt-1">SKU: <span class="font-mono font-bold text-slate-700">{{ $product->sku }}</span> | Barcode: <span class="font-mono text-slate-700">{{ $product->barcode ?? '-' }}</span></p>
        </div>

        <div class="flex items-center gap-2">
            @can('products.update')
                <a href="{{ route('products.edit', $product) }}" class="inline-flex items-center px-3.5 py-2 rounded-md bg-slate-800 text-white text-xs font-semibold hover:bg-slate-700 transition gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    Ubah Data Produk
                </a>
            @endcan

            @can('products.deactivate')
                <form action="{{ route('products.toggle_status', $product) }}" method="POST" class="inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center px-3 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold {{ $product->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition">
                        {{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <!-- Product Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Price Card -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Harga Jual Saat Ini</span>
            <span class="text-2xl font-bold font-mono text-slate-900 mt-2 block">
                Rp {{ number_format($product->selling_price, 2, ',', '.') }}
            </span>
            <span class="text-xs text-slate-500 mt-1 block">per {{ $product->unit?->symbol ?? 'unit' }}</span>
        </div>

        <!-- Minimum Stock Card -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Batas Minimum Stok</span>
            <span class="text-2xl font-bold font-mono text-slate-900 mt-2 block">
                {{ rtrim(rtrim(number_format($product->minimum_stock, 3, '.', ''), '0'), '.') }}
            </span>
            <span class="text-xs text-slate-500 mt-1 block">{{ $product->unit?->name }} ({{ $product->unit?->symbol }})</span>
        </div>

        <!-- Category Card -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Kategori Produk</span>
            <span class="text-lg font-bold text-slate-900 mt-2 block">
                {{ $product->category?->name ?? '-' }}
            </span>
            <span class="text-xs {{ $product->category?->is_active ? 'text-emerald-600' : 'text-rose-600' }} mt-1 block">
                {{ $product->category?->is_active ? 'Kategori Aktif' : 'Kategori Nonaktif' }}
            </span>
        </div>

        <!-- Precision Card -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Toleransi Satuan</span>
            <span class="text-lg font-bold text-slate-900 mt-2 block">
                {{ $product->unit?->symbol }} ({{ $product->unit?->decimal_precision }} desimal)
            </span>
            <span class="text-xs text-slate-500 mt-1 block">
                {{ $product->unit?->decimal_precision === 0 ? 'Hanya bilangan bulat' : 'Mendukung pecahan desimal' }}
            </span>
        </div>
    </div>

    @if($product->description)
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
            <h2 class="text-xs font-semibold uppercase text-slate-400 tracking-wider">Deskripsi / Catatan Produk</h2>
            <p class="text-sm text-slate-700 mt-2 leading-relaxed">{{ $product->description }}</p>
        </div>
    @endif

    <!-- Price History Section (FR-MST-04) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Riwayat Perubahan Harga Jual (Audit Trail FR-MST-04)</h2>
                <p class="text-xs text-slate-500 mt-0.5">Catatan jejak historis setiap kali harga jual produk diubah pada sistem.</p>
            </div>
            <span class="text-xs font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                {{ $product->priceHistories->count() }} catatan
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/75 text-slate-600 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">Waktu Efektif</th>
                        <th class="py-3 px-4 text-right">Harga Sebelum</th>
                        <th class="py-3 px-4 text-right">Harga Baru</th>
                        <th class="py-3 px-4 text-right">Selisih</th>
                        <th class="py-3 px-4">Diubah Oleh</th>
                        <th class="py-3 px-4">Alasan Perubahan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($product->priceHistories as $history)
                        @php
                            $diff = (float)$history->new_price - (float)$history->old_price;
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-600 font-mono">
                                {{ $history->effective_at ? $history->effective_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') : '-' }} WIB
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-xs text-slate-500 whitespace-nowrap">
                                Rp {{ number_format($history->old_price, 2, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                Rp {{ number_format($history->new_price, 2, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-xs font-semibold whitespace-nowrap {{ $diff > 0 ? 'text-emerald-600' : ($diff < 0 ? 'text-rose-600' : 'text-slate-500') }}">
                                {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-700">
                                {{ $history->changer?->name ?? 'Sistem / Inisialisasi' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 max-w-sm truncate">
                                {{ $history->reason ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">
                                Belum ada riwayat perubahan harga untuk produk ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
