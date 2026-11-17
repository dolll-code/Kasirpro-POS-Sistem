@extends('layouts.app', [
    'title' => 'Tambah Pemasok Baru',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Pemasok' => route('suppliers.index'),
        'Tambah' => null
    ]
])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Tambah Pemasok Baru</h1>
            <p class="text-xs text-slate-500 mt-1">Daftarkan mitra distributor atau pemasok barang dagangan.</p>
        </div>
        <a href="{{ route('suppliers.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke daftar
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('suppliers.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="supplier_code" value="Kode Pemasok (Unik)" required />
                    <x-form.input id="supplier_code" name="supplier_code" value="{{ old('supplier_code') }}" placeholder="Contoh: SUP-001" required autofocus class="uppercase font-mono" />
                    @error('supplier_code')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="name" value="Nama Perusahaan / Pemasok" required />
                    <x-form.input id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: PT Sumber Pangan Sejahtera" required />
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-form.label for="contact_person" value="Kontak Person" />
                    <x-form.input id="contact_person" name="contact_person" value="{{ old('contact_person') }}" placeholder="Nama PIC" />
                    @error('contact_person')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="phone" value="Nomor Telepon / WA" />
                    <x-form.input id="phone" name="phone" value="{{ old('phone') }}" placeholder="08123456789" />
                    @error('phone')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="email" value="Alamat Email" />
                    <x-form.input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="email@supplier.com" />
                    @error('email')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <x-form.label for="address" value="Alamat Gudang / Kantor" />
                <textarea id="address" name="address" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Alamat lengkap supplier...">{{ old('address') }}</textarea>
                @error('address')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-form.label for="notes" value="Catatan Tambahan (Opsional)" />
                <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Termin pembayaran, syarat retur, dsb...">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Aktifkan pemasok (dapat digunakan untuk pembuatan Purchase Order baru)</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('suppliers.index') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <x-button type="submit" variant="primary" size="md">
                    Simpan Pemasok
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
