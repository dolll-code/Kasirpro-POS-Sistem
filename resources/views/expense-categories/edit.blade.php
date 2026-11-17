@extends('layouts.app', [
    'title' => 'Edit Kategori Biaya: ' . $expenseCategory->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Kategori Biaya' => route('expense-categories.index'),
        'Edit' => null
    ]
])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Ubah Kategori Biaya</h1>
            <p class="text-xs text-slate-500 mt-1">Perbarui nama pos pengeluaran atau status aktif.</p>
        </div>
        <a href="{{ route('expense-categories.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke daftar
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('expense-categories.update', $expenseCategory) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-form.label for="name" value="Nama Kategori Biaya" required />
                <x-form.input id="name" name="name" value="{{ old('name', $expenseCategory->name) }}" required autofocus />
                @error('name')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-form.label for="description" value="Deskripsi / Keterangan (Opsional)" />
                <textarea id="description" name="description" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800">{{ old('description', $expenseCategory->description) }}</textarea>
                @error('description')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $expenseCategory->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Kategori aktif</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('expense-categories.index') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
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
