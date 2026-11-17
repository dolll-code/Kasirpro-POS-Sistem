@extends('layouts.app', [
    'title' => 'Tambah Akun Kas/Bank Baru',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Akun Kas & Bank' => route('cash-accounts.index'),
        'Tambah' => null
    ]
])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Tambah Akun Kas / Bank Baru</h1>
            <p class="text-xs text-slate-500 mt-1">Daftarkan akun kas laci atau rekening bank untuk transaksi keuangan toko.</p>
        </div>
        <a href="{{ route('cash-accounts.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke daftar
        </a>
    </div>

    <!-- DEC-17 / Integrity Banner -->
    <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900">
        <div class="flex gap-2">
            <svg class="h-4 w-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <strong class="font-semibold">Aturan Saldo Pembukaan (PRD DEC-17):</strong>
                Saldo awal kas/bank hanya diinput saat pembuatan akun baru dan dilarang diedit langsung di kemudian hari untuk menjaga keutuhan buku besar arus kas (*append-only ledger*). Koreksi saldo di masa depan wajib dilakukan melalui penyesuaian kas resmi (*Cash Adjustment*).
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('cash-accounts.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="code" value="Kode Akun (Unik)" required />
                    <x-form.input id="code" name="code" value="{{ old('code') }}" placeholder="Contoh: KAS-01 / BNK-BCA" required autofocus class="uppercase font-mono" />
                    @error('code')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="name" value="Nama Akun Kas / Bank" required />
                    <x-form.input id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: Kas Utama Toko" required />
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="account_type" value="Tipe Akun Keuangan" required />
                    <x-form.select id="account_type" name="account_type" required>
                        <option value="cash" {{ old('account_type') === 'cash' ? 'selected' : '' }}>Kas Fisik (Laci Kasir / Uang Tunai)</option>
                        <option value="bank" {{ old('account_type') === 'bank' ? 'selected' : '' }}>Rekening Bank (Transfer / Giro)</option>
                    </x-form.select>
                    @error('account_type')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-form.label for="opening_balance_at" value="Tanggal Saldo Pembukaan" />
                    <x-form.input type="date" id="opening_balance_at" name="opening_balance_at" value="{{ old('opening_balance_at', date('Y-m-d')) }}" />
                    @error('opening_balance_at')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <x-form.label for="opening_balance" value="Saldo Pembukaan Awal (Rp)" required />
                <div class="relative rounded-md shadow-xs">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <span class="text-slate-500 text-xs font-semibold">Rp</span>
                    </div>
                    <input type="number" step="0.01" min="0" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', '0.00') }}" required class="block w-full rounded-lg border-slate-300 pl-10 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" />
                </div>
                <p class="text-[11px] text-slate-400 mt-0.5">Saldo uang fisik saat sistem mulai digunakan (tidak boleh bernilai negatif).</p>
                @error('opening_balance')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Aktifkan akun kas/bank ini</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('cash-accounts.index') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <x-button type="submit" variant="primary" size="md">
                    Simpan Akun
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
