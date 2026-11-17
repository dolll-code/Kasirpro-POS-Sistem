@extends('layouts.app', [
    'title' => 'Tambah Kategori Biaya Baru',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Kategori Biaya' => route('expense-categories.index'),
        'Tambah' => null
    ]
])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Tambah Kategori Biaya</h1>
            <p class="text-xs text-slate-500 mt-1">Buat pos jenis pengeluaran operasional baru.</p>
        </div>
        <a href="{{ route('expense-categories.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke daftar
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('expense-categories.store') }}" class="space-y-4">
            @csrf

            <div>
                <x-form.label for="name" value="Nama Kategori Biaya" required />
                <x-form.input id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: Tagihan Listrik & Air" required autofocus />
                @error('name')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-form.label for="description" value="Deskripsi / Keterangan (Opsional)" />
                <textarea id="description" name="description" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Keterangan alokasi pengeluaran...">{{ old('description') }}</textarea>
                @error('description')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Aktifkan kategori biaya ini segera</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('expense-categories.index') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <x-button type="submit" variant="primary" size="md">
                    Simpan Kategori
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
