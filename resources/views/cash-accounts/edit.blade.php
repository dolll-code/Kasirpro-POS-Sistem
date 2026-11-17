@extends('layouts.app', [
    'title' => 'Edit Akun Kas/Bank: ' . $cashAccount->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Akun Kas & Bank' => route('cash-accounts.index'),
        'Edit' => null
    ]
])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Ubah Data Akun Kas / Bank</h1>
            <p class="text-xs text-slate-500 mt-1">Perbarui kode, nama, tipe, atau status akun keuangan.</p>
        </div>
        <a href="{{ route('cash-accounts.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke daftar
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('cash-accounts.update', $cashAccount) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="code" value="Kode Akun" required />
                    <x-form.input id="code" name="code" value="{{ old('code', $cashAccount->code) }}" required autofocus class="uppercase font-mono" />
                    @error('code')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="name" value="Nama Akun Kas / Bank" required />
                    <x-form.input id="name" name="name" value="{{ old('name', $cashAccount->name) }}" required />
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="account_type" value="Tipe Akun Keuangan" required />
                    <x-form.select id="account_type" name="account_type" required>
                        <option value="cash" {{ old('account_type', $cashAccount->account_type) === 'cash' ? 'selected' : '' }}>Kas Fisik (Laci Kasir / Uang Tunai)</option>
                        <option value="bank" {{ old('account_type', $cashAccount->account_type) === 'bank' ? 'selected' : '' }}>Rekening Bank (Transfer / Giro)</option>
                    </x-form.select>
                    @error('account_type')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="opening_balance_info" value="Saldo Awal Terkunci (PRD DEC-17)" />
                    <div class="py-2.5 px-3 rounded-lg bg-slate-50 border border-slate-200 font-mono text-sm text-slate-600">
                        Rp {{ number_format($cashAccount->opening_balance, 2, ',', '.') }} ({{ $cashAccount->opening_balance_at ? $cashAccount->opening_balance_at->format('d/m/Y') : '-' }})
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Saldo awal tidak dapat diubah setelah dibuat untuk menjaga audit ledger.</p>
                </div>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $cashAccount->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Akun aktif</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('cash-accounts.index') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
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
