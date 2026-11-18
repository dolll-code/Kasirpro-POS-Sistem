@extends('layouts.app')

@section('title', 'Pembayaran Pemasok PO #' . $purchase->purchase_number)

@section('content')
<div class="space-y-6 max-w-2xl mx-auto">
    <!-- Header -->
    <div>
        <nav class="flex text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1">
                <li><a href="{{ route('dashboard') }}" class="hover:text-slate-700">Beranda</a></li>
                <li><span class="mx-1">/</span></li>
                <li><a href="{{ route('purchases.index') }}" class="hover:text-slate-700">Pembelian</a></li>
                <li><span class="mx-1">/</span></li>
                <li><a href="{{ route('purchases.show', $purchase) }}" class="hover:text-slate-700">{{ $purchase->purchase_number }}</a></li>
                <li><span class="mx-1">/</span></li>
                <li class="text-slate-800 font-medium">Pembayaran Pemasok</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Catat Pembayaran Pemasok</h1>
        <p class="text-sm text-slate-500 mt-0.5">Pembayaran utang dagang atas barang diterima untuk PO #{{ $purchase->purchase_number }}.</p>
    </div>

    <!-- Outstanding Notice Card -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-700">Pemasok Penerima</p>
            <p class="text-base font-bold text-slate-900 mt-0.5">{{ $purchase->supplier?->name }} ({{ $purchase->supplier?->supplier_code }})</p>
        </div>
        <div class="text-right font-mono">
            <p class="text-xs font-sans font-semibold uppercase tracking-wider text-indigo-700">Sisa Utang Diterima</p>
            <p class="text-xl font-bold text-indigo-900 mt-0.5">Rp {{ number_format((float)$outstanding->toString(), 2, ',', '.') }}</p>
        </div>
    </div>

    <!-- Payment Form Card -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-xs">
        <form method="POST" action="{{ route('purchases.payments.store', $purchase) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Pilih Akun Kas / Bank Pengeluaran <span class="text-rose-500">*</span></label>
                <select name="cash_account_id" required class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                    <option value="">Pilih Sumber Dana Kas/Bank...</option>
                    @foreach($cashAccounts as $acc)
                        <option value="{{ $acc->id }}" {{ old('cash_account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->name }} ({{ $acc->code }}) &bull; Saldo: Rp {{ number_format((float)$acc->current_balance, 2, ',', '.') }}
                        </option>
                    @endforeach
                </select>
                @error('cash_account_id')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Metode Pembayaran <span class="text-rose-500">*</span></label>
                    <select name="method" required class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                        <option value="cash" {{ old('method') === 'cash' ? 'selected' : '' }}>Tunai / Kas Fisik</option>
                        <option value="bank_transfer" {{ old('method') === 'bank_transfer' ? 'selected' : '' }}>Transfer Bank</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Nominal Pembayaran (Rp) <span class="text-rose-500">*</span></label>
                    <input type="number" step="any" min="0.01" max="{{ $outstanding->toString() }}" name="amount" value="{{ old('amount', $outstanding->toString()) }}" required class="w-full py-2 px-3 text-sm font-mono border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500 @error('amount') border-rose-500 @enderror">
                    <p class="text-[11px] text-slate-400 mt-0.5">Maksimal: Rp {{ number_format((float)$outstanding->toString(), 2, ',', '.') }}</p>
                    @error('amount')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Nomor Referensi / Bukti Transfer</label>
                    <input type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="Contoh: TRF-BCA-981029..." class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Waktu Pembayaran</label>
                    <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Catatan</label>
                <textarea name="notes" rows="2" placeholder="Catatan opsional pembayaran..." class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">{{ old('notes') }}</textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('purchases.show', $purchase) }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition">
                    Simpan & Keluarkan Kas
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
