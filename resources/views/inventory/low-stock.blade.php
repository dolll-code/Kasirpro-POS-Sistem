@extends('layouts.app')

@section('title', 'Peringatan Stok Habis & Menipis')

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
                    <li class="text-slate-800 font-medium">Stok Habis & Menipis</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Peringatan Kebutuhan Restock</h1>
            <p class="text-sm text-slate-500 mt-0.5">Daftar produk yang telah mencapai batas stok minimum atau telah habis (out-of-stock).</p>
        </div>

        <div>
            <a href="{{ route('inventory.index') }}" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-2xs">
                Lihat Seluruh Persediaan
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/75 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                        <th class="py-3 px-4">Produk / SKU</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4 text-right">Stok Fisik</th>
                        <th class="py-3 px-4 text-right">Batas Minimum</th>
                        <th class="py-3 px-4 text-right">Tersedia</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($products as $product)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-900">{{ $product->name }}</div>
                                <div class="text-xs text-slate-500 font-mono flex items-center gap-2 mt-0.5">
                                    <span>SKU: {{ $product->sku }}</span>
                                    @if($product->barcode)
                                        <span>•</span>
                                        <span>Bar: {{ $product->barcode }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <span class="inline-block bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-xs">
                                    {{ $product->category?->name ?? '-' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-slate-900">
                                {{ \App\Support\MoneyCalculator::formatQuantity($product->quantity_on_hand, $product->unit?->decimal_precision ?? 0) }}
                                <span class="text-xs text-slate-500 font-normal">{{ $product->unit?->symbol }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-slate-600">
                                {{ \App\Support\MoneyCalculator::formatQuantity($product->minimum_stock, $product->unit?->decimal_precision ?? 0) }}
                                <span class="text-xs text-slate-500 font-normal">{{ $product->unit?->symbol }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-bold {{ (float)$product->available_quantity > 0 ? 'text-amber-700' : 'text-rose-700' }}">
                                {{ \App\Support\MoneyCalculator::formatQuantity($product->available_quantity, $product->unit?->decimal_precision ?? 0) }}
                                <span class="text-xs text-slate-500 font-normal">{{ $product->unit?->symbol }}</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($product->stock_status === 'out_of_stock')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        Habis
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Menipis
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('inventory.card', $product) }}" 
                                   class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-lg text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition shadow-2xs">
                                    Kartu Stok
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-emerald-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-base font-semibold text-slate-700">Semua stok aman!</p>
                                <p class="text-xs text-slate-400 mt-1">Tidak ada produk yang sedang habis atau di bawah batas minimum.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
