@extends('layouts.app')

@section('title', 'Persediaan & Saldo Stok')

@section('content')
<div class="space-y-6">
    <!-- Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1">
                    <li><a href="{{ route('dashboard') }}" class="hover:text-slate-700">Beranda</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li class="text-slate-800 font-medium">Persediaan & Stok</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Saldo Stok & Persediaan Barang</h1>
            <p class="text-sm text-slate-500 mt-0.5">Monitoring saldo fisik (on-hand), cadangan transaksi (reserved), stok tersedia, dan nilai valuasi persediaan.</p>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Produk</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalProducts, 0, ',', '.') }}</p>
            </div>
            <div class="p-3 bg-slate-100 rounded-lg text-slate-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Stok Tersedia</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($availableCount, 0, ',', '.') }}</p>
            </div>
            <div class="p-3 bg-emerald-50 rounded-lg text-emerald-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Stok Menipis (<= Min)</p>
                <p class="text-2xl font-bold text-amber-700 mt-1">{{ number_format($lowStockCount, 0, ',', '.') }}</p>
            </div>
            <div class="p-3 bg-amber-50 rounded-lg text-amber-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-rose-600 uppercase tracking-wider">Stok Habis (0)</p>
                <p class="text-2xl font-bold text-rose-700 mt-1">{{ number_format($outOfStockCount, 0, ',', '.') }}</p>
            </div>
            <div class="p-3 bg-rose-50 rounded-lg text-rose-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <form method="GET" action="{{ route('inventory.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="search" class="block text-xs font-medium text-slate-700 mb-1">Cari Produk</label>
                <input type="text" 
                       name="search" 
                       id="search" 
                       value="{{ request('search') }}" 
                       placeholder="Nama, SKU, atau Barcode..."
                       class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
            </div>

            <div>
                <label for="category_id" class="block text-xs font-medium text-slate-700 mb-1">Kategori</label>
                <select name="category_id" id="category_id" class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="block text-xs font-medium text-slate-700 mb-1">Status Stok</label>
                <select name="status" id="status" class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
                    <option value="">Semua Status</option>
                    <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>Tersedia (> Min)</option>
                    <option value="low_stock" {{ request('status') === 'low_stock' ? 'selected' : '' }}>Menipis (<= Min)</option>
                    <option value="out_of_stock" {{ request('status') === 'out_of_stock' ? 'selected' : '' }}>Habis (0)</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-xs">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'category_id', 'status']))
                    <a href="{{ route('inventory.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Table of Inventory Balances -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/75 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                        <th class="py-3 px-4">Produk / SKU</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4 text-right">Fisik (On-Hand)</th>
                        <th class="py-3 px-4 text-right">Cadangan (Reserved)</th>
                        <th class="py-3 px-4 text-right">Tersedia (Available)</th>
                        <th class="py-3 px-4 text-right">HPP Rata-Rata</th>
                        <th class="py-3 px-4 text-right">Total Valuasi</th>
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
                            <td class="py-3 px-4 text-right text-amber-700">
                                {{ \App\Support\MoneyCalculator::formatQuantity($product->quantity_reserved, $product->unit?->decimal_precision ?? 0) }}
                                <span class="text-xs text-slate-500 font-normal">{{ $product->unit?->symbol }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-bold {{ (float) $product->available_quantity > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ \App\Support\MoneyCalculator::formatQuantity($product->available_quantity, $product->unit?->decimal_precision ?? 0) }}
                                <span class="text-xs text-slate-500 font-normal">{{ $product->unit?->symbol }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-700">
                                {{ \App\Support\MoneyCalculator::formatIdr($product->average_cost) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-medium text-slate-900">
                                {{ \App\Support\MoneyCalculator::formatIdr($product->inventoryBalance?->valuation ?? '0.00') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($product->stock_status === 'available')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Tersedia
                                    </span>
                                @elseif($product->stock_status === 'low_stock')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Menipis
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        Habis
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('inventory.card', $product) }}" 
                                   class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-lg text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition shadow-2xs">
                                    <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    Kartu Stok
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <p class="text-base font-semibold text-slate-700">Tidak ada produk ditemukan</p>
                                <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau filter status stok.</p>
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
