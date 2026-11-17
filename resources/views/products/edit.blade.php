@extends('layouts.app', [
    'title' => 'Edit Produk: ' . $product->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Produk' => route('products.index'),
        $product->name => route('products.show', $product),
        'Edit' => null
    ]
])

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Ubah Data Produk</h1>
            <p class="text-xs text-slate-500 mt-1">Perbarui informasi katalog, harga jual, batas stok minimum, atau status produk.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('products.show', $product) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
                Lihat Detail & Riwayat &rarr;
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <!-- Identifiers Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="sku" value="Nomor SKU (Stock Keeping Unit)" required />
                    <x-form.input id="sku" name="sku" value="{{ old('sku', $product->sku) }}" required autofocus class="uppercase font-mono" />
                    @error('sku')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="barcode" value="Kode Barcode (Opsional)" />
                    <x-form.input id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}" class="font-mono" />
                    @error('barcode')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Product Name -->
            <div>
                <x-form.label for="name" value="Nama Produk Lengkap" required />
                <x-form.input id="name" name="name" value="{{ old('name', $product->name) }}" required />
                @error('name')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Category & Unit -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="category_id" value="Kategori Produk" required />
                    <x-form.select id="category_id" name="category_id" required>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }} {{ !$category->is_active ? '(Nonaktif)' : '' }}
                            </option>
                        @endforeach
                    </x-form.select>
                    @error('category_id')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="unit_id" value="Satuan Unit Kuantitas" required />
                    <x-form.select id="unit_id" name="unit_id" required>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" {{ old('unit_id', $product->unit_id) == $unit->id ? 'selected' : '' }}>
                                {{ $unit->name }} ({{ $unit->symbol }}) - Presisi {{ $unit->decimal_precision }} digit {{ !$unit->is_active ? '(Nonaktif)' : '' }}
                            </option>
                        @endforeach
                    </x-form.select>
                    @error('unit_id')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Pricing & Minimum Stock -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="selling_price" value="Harga Jual Satuan (Rp)" required />
                    <div class="relative rounded-md shadow-xs">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-slate-500 text-xs font-semibold">Rp</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}" required class="block w-full rounded-lg border-slate-300 pl-10 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" />
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Setiap perubahan harga akan otomatis tercatat pada riwayat perubahan harga produk.</p>
                    @error('selling_price')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="minimum_stock" value="Batas Minimum Stok" required />
                    <input type="number" step="0.001" min="0" id="minimum_stock" name="minimum_stock" value="{{ old('minimum_stock', $product->minimum_stock) }}" required class="block w-full rounded-lg border-slate-300 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" />
                    @error('minimum_stock')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Price Change Reason (Optional for Audit) -->
            <div>
                <x-form.label for="price_change_reason" value="Alasan Perubahan Harga (Bila Harga Berubah)" />
                <x-form.input id="price_change_reason" name="price_change_reason" value="{{ old('price_change_reason') }}" placeholder="Contoh: Kenaikan harga distributor atau promo diskon musiman..." />
                @error('price_change_reason')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description -->
            <div>
                <x-form.label for="description" value="Deskripsi / Catatan Tambahan (Opsional)" />
                <textarea id="description" name="description" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800">{{ old('description', $product->description) }}</textarea>
                @error('description')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Active Status -->
            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Produk aktif (dapat dicari dan dijual di meja kasir POS)</span>
                </label>
            </div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('products.show', $product) }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <x-button type="submit" variant="primary" size="md">
                    Simpan Perubahan
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
