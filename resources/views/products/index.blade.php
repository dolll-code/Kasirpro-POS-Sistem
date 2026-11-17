@extends('layouts.app', [
    'title' => 'Katalog Produk',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Produk' => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Katalog Produk</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola master produk, nomor SKU, barcode scanner, harga jual, dan batas minimum stok.</p>
        </div>
        <div class="shrink-0 flex gap-2">
            @can('products.create')
                <a href="{{ route('products.create') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Produk Baru
                </a>
            @endcan
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4">
        <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <x-form.label for="search" value="Cari SKU / Barcode / Nama" />
                <x-form.input id="search" name="search" value="{{ request('search') }}" placeholder="Scan barcode atau ketik SKU / nama..." autofocus />
            </div>

            <div>
                <x-form.label for="category_id" value="Kategori" />
                <x-form.select id="category_id" name="category_id">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </x-form.select>
            </div>

            <div>
                <x-form.label for="status" value="Status" />
                <x-form.select id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif (Dapat Dijual)</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </x-form.select>
            </div>

            <div class="sm:col-span-4 flex justify-end gap-2 pt-2 border-t border-slate-100">
                <a href="{{ route('products.index') }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50">
                    Reset Filter
                </a>
                <x-button type="submit" variant="primary" size="sm">
                    Terapkan Filter
                </x-button>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/75 text-slate-600 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">SKU / Barcode</th>
                        <th class="py-3 px-4">Nama Produk</th>
                        <th class="py-3 px-4">Kategori & Satuan</th>
                        <th class="py-3 px-4 text-right">Harga Jual (Rp)</th>
                        <th class="py-3 px-4 text-center">Min. Stok</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($products as $product)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-900 block text-xs">{{ $product->sku }}</span>
                                @if($product->barcode)
                                    <span class="font-mono text-[11px] text-slate-500 flex items-center gap-1 mt-0.5">
                                        <svg class="h-3 w-3 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                        </svg>
                                        {{ $product->barcode }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <a href="{{ route('products.show', $product) }}" class="font-semibold text-slate-900 hover:text-blue-600 block">
                                    {{ $product->name }}
                                </a>
                                @if($product->description)
                                    <span class="text-xs text-slate-500 block truncate max-w-xs mt-0.5">{{ $product->description }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                    {{ $product->category?->name ?? '-' }}
                                </span>
                                <span class="text-xs text-slate-500 ml-1">/ {{ $product->unit?->symbol ?? '-' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                Rp {{ number_format($product->selling_price, 2, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono text-xs text-slate-600 whitespace-nowrap">
                                {{ rtrim(rtrim(number_format($product->minimum_stock, 3, '.', ''), '0'), '.') }} {{ $product->unit?->symbol }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($product->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                <a href="{{ route('products.show', $product) }}" class="text-xs font-medium text-slate-700 hover:text-slate-900" title="Detail & Riwayat Harga">
                                    Detail
                                </a>

                                @can('products.update')
                                    <a href="{{ route('products.edit', $product) }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">
                                        Edit
                                    </a>
                                @endcan

                                @can('products.deactivate')
                                    <form action="{{ route('products.toggle_status', $product) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-xs font-medium {{ $product->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                            {{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>

                                    <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-700">
                                            Hapus
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                Tidak ada data produk yang sesuai kriteria pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
