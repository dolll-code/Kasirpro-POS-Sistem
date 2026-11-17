@extends('layouts.app', [
    'title' => 'Tambah Produk Baru',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Produk' => route('products.index'),
        'Tambah' => null
    ]
])

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Tambah Produk Baru</h1>
            <p class="text-xs text-slate-500 mt-1">Daftarkan item barang baru ke dalam katalog penjualan kasir.</p>
        </div>
        <a href="{{ route('products.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke daftar
        </a>
    </div>

    <!-- Informational Banner regarding BR-CAT-03 -->
    <div class="rounded-lg bg-blue-50/80 border border-blue-200 p-4 text-xs text-blue-900">
        <div class="flex gap-2">
            <svg class="h-4 w-4 text-blue-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <strong class="font-semibold">Aturan Integritas Persediaan & HPP (PRD §6.2):</strong>
                Kuantitas saldo fisik (*on-hand stock*) dan HPP rata-rata bergerak (*moving average cost*) tidak dapat dimasukkan langsung dari formulir master produk. Stok dan HPP awal terbentuk otomatis melalui dokumen **Penerimaan Barang (PO)** atau **Stok Opname Awal**.
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('products.store') }}" class="space-y-5">
            @csrf

            <!-- Identifiers Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="sku" value="Nomor SKU (Stock Keeping Unit)" required />
                    <x-form.input id="sku" name="sku" value="{{ old('sku') }}" placeholder="Contoh: BRG-001" required autofocus class="uppercase font-mono" />
                    <p class="text-[11px] text-slate-400 mt-0.5">Kode unik inventori internal toko (otomatis dikonversi ke huruf kapital).</p>
                    @error('sku')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="barcode" value="Kode Barcode (Opsional)" />
                    <x-form.input id="barcode" name="barcode" value="{{ old('barcode') }}" placeholder="Scan atau ketik kode barcode..." class="font-mono" />
                    <p class="text-[11px] text-slate-400 mt-0.5">Mendukung scanner USB keyboard-wedge (angka nol di depan akan dipertahankan).</p>
                    @error('barcode')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Product Name -->
            <div>
                <x-form.label for="name" value="Nama Produk Lengkap" required />
                <x-form.input id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: Minyak Goreng Bimoli 2 Liter" required />
                @error('name')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Category & Unit -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="category_id" value="Kategori Produk" required />
                    <x-form.select id="category_id" name="category_id" required>
                        <option value="">Pilih Kategori...</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
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
                        <option value="">Pilih Satuan...</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                {{ $unit->name }} ({{ $unit->symbol }}) - Presisi {{ $unit->decimal_precision }} digit
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
                        <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" value="{{ old('selling_price', '0.00') }}" required class="block w-full rounded-lg border-slate-300 pl-10 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" />
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Harga jual resmi di meja kasir POS (sebelum diskon).</p>
                    @error('selling_price')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="minimum_stock" value="Batas Minimum Stok (Peringatan Menipis)" required />
                    <input type="number" step="0.001" min="0" id="minimum_stock" name="minimum_stock" value="{{ old('minimum_stock', '0.000') }}" required class="block w-full rounded-lg border-slate-300 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" />
                    <p class="text-[11px] text-slate-400 mt-0.5">Sistem akan menandai status 'Menipis' jika saldo stok &le; angka ini.</p>
                    @error('minimum_stock')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Description -->
            <div>
                <x-form.label for="description" value="Deskripsi / Catatan Tambahan (Opsional)" />
                <textarea id="description" name="description" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Keterangan fisik, lokasi rak, atau catatan produk...">{{ old('description') }}</textarea>
                @error('description')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Active Status -->
            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Aktifkan produk (dapat dicari dan dijual di POS Kasir)</span>
                </label>
            </div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <x-button type="submit" variant="primary" size="md">
                    Simpan Produk
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
