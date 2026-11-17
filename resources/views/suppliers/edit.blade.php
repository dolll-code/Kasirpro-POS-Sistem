@extends('layouts.app', [
    'title' => 'Edit Pemasok: ' . $supplier->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Pemasok' => route('suppliers.index'),
        $supplier->name => route('suppliers.show', $supplier),
        'Edit' => null
    ]
])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Ubah Data Pemasok</h1>
            <p class="text-xs text-slate-500 mt-1">Perbarui kode, nama, kontak, alamat, atau status pemasok.</p>
        </div>
        <a href="{{ route('suppliers.show', $supplier) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke detail
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="supplier_code" value="Kode Pemasok" required />
                    <x-form.input id="supplier_code" name="supplier_code" value="{{ old('supplier_code', $supplier->supplier_code) }}" required autofocus class="uppercase font-mono" />
                    @error('supplier_code')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="name" value="Nama Perusahaan / Pemasok" required />
                    <x-form.input id="name" name="name" value="{{ old('name', $supplier->name) }}" required />
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-form.label for="contact_person" value="Kontak Person" />
                    <x-form.input id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" />
                    @error('contact_person')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="phone" value="Nomor Telepon / WA" />
                    <x-form.input id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" />
                    @error('phone')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="email" value="Alamat Email" />
                    <x-form.input id="email" type="email" name="email" value="{{ old('email', $supplier->email) }}" />
                    @error('email')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <x-form.label for="address" value="Alamat Gudang / Kantor" />
                <textarea id="address" name="address" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800">{{ old('address', $supplier->address) }}</textarea>
                @error('address')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-form.label for="notes" value="Catatan Tambahan (Opsional)" />
                <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800">{{ old('notes', $supplier->notes) }}</textarea>
                @error('notes')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Pemasok aktif (dapat digunakan untuk pembuatan Purchase Order baru)</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('suppliers.show', $supplier) }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
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
