@extends('layouts.app', [
    'title' => 'Edit Satuan: ' . $unit->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Satuan' => route('units.index'),
        'Edit' => null
    ]
])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Ubah Data Satuan</h1>
            <p class="text-xs text-slate-500 mt-1">Perbarui nama, simbol, presisi pecahan, atau status satuan.</p>
        </div>
        <a href="{{ route('units.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke daftar
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('units.update', $unit) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="name" value="Nama Satuan" required />
                    <x-form.input id="name" name="name" value="{{ old('name', $unit->name) }}" required autofocus />
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="symbol" value="Simbol Satuan" required />
                    <x-form.input id="symbol" name="symbol" value="{{ old('symbol', $unit->symbol) }}" required />
                    @error('symbol')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <x-form.label for="decimal_precision" value="Presisi Desimal (Digit Pecahan)" required />
                <x-form.select id="decimal_precision" name="decimal_precision" required>
                    <option value="0" {{ old('decimal_precision', $unit->decimal_precision) == 0 ? 'selected' : '' }}>0 Digit - Hanya Bilangan Bulat</option>
                    <option value="1" {{ old('decimal_precision', $unit->decimal_precision) == 1 ? 'selected' : '' }}>1 Digit Desimal (0.1)</option>
                    <option value="2" {{ old('decimal_precision', $unit->decimal_precision) == 2 ? 'selected' : '' }}>2 Digit Desimal (0.01)</option>
                    <option value="3" {{ old('decimal_precision', $unit->decimal_precision) == 3 ? 'selected' : '' }}>3 Digit Desimal (0.001 - Timbangan Kg presisi)</option>
                </x-form.select>
                @error('decimal_precision')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $unit->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Satuan aktif</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('units.index') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
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
