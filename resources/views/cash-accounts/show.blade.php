@extends('layouts.app', [
    'title' => 'Buku Kas: ' . $cashAccount->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Akun Kas & Bank' => route('cash-accounts.index'),
        'Buku Kas: ' . $cashAccount->name => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto" x-data="{ reversalModalOpen: false, selectedMovementId: null, selectedMovementDesc: '' }">
    <!-- Header & Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Buku Kas & Riwayat Mutasi</h1>
            <p class="text-sm text-slate-500 mt-0.5">Jejak arus kas masuk, pengeluaran, saldo berjalan, dan rekonsiliasi audit ledger.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('cash-accounts.index') }}" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-2xs">
                ← Kembali ke Daftar Akun
            </a>
        </div>
    </div>

    <!-- Account Details & Reconciliation Status -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Account Info Card -->
        <div class="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <span class="inline-block px-2.5 py-0.5 rounded text-xs font-semibold {{ $cashAccount->account_type === 'cash' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }} mb-1">
                        {{ $cashAccount->account_type === 'cash' ? 'Kas Fisik (Laci Kasir)' : 'Rekening Bank Operasional' }}
                    </span>
                    <h2 class="text-xl font-bold text-slate-900">{{ $cashAccount->name }}</h2>
                    <div class="text-xs text-slate-500 font-mono flex items-center gap-3 mt-1">
                        <span>Kode: <strong class="text-slate-700">{{ $cashAccount->code }}</strong></span>
                        <span>•</span>
                        <span>Saldo Pembukaan: <strong class="text-slate-700">{{ \App\Support\MoneyCalculator::formatIdr($cashAccount->opening_balance) }}</strong></span>
                        @if($cashAccount->opening_balance_at)
                            <span>•</span>
                            <span>Tanggal Pembukaan: <strong class="text-slate-700">{{ $cashAccount->opening_balance_at->format('d/m/Y') }}</strong></span>
                        @endif
                    </div>
                </div>

                <div>
                    @if($cashAccount->is_active)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            ● Aktif
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                            ✕ Nonaktif
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-slate-100 text-center">
                <div class="bg-slate-50/80 p-3 rounded-lg">
                    <p class="text-[11px] text-slate-500 font-medium uppercase">Saldo Awal</p>
                    <p class="text-lg font-mono font-bold text-slate-700 mt-0.5">
                        {{ \App\Support\MoneyCalculator::formatIdr($cashAccount->opening_balance) }}
                    </p>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-lg">
                    <p class="text-[11px] text-emerald-600 font-medium uppercase">Total Masuk (Ledger)</p>
                    <p class="text-lg font-mono font-bold text-emerald-700 mt-0.5">
                        +{{ \App\Support\MoneyCalculator::formatIdr($reconciliation['total_in']) }}
                    </p>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-lg">
                    <p class="text-[11px] text-slate-900 font-medium uppercase">Saldo Aktif Saat Ini</p>
                    <p class="text-xl font-mono font-bold text-slate-900 mt-0.5">
                        {{ \App\Support\MoneyCalculator::formatIdr($cashAccount->current_balance ?? '0.00') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Reconciliation Audit Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-bold text-slate-900">Rekonsiliasi Buku Kas</h3>
                    @if($reconciliation['is_consistent'])
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                            100% Konsisten
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800">
                            Selisih Terdeteksi!
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mb-3">Formula: Saldo Akhir = Total Masuk (Inflow + Opening) - Total Keluar (Outflow).</p>

                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Saldo Akun Tercatat:</span>
                        <strong class="font-mono text-slate-800">{{ \App\Support\MoneyCalculator::formatIdr($reconciliation['current_balance']) }}</strong>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Total Masuk (Inflows):</span>
                        <span class="font-mono text-emerald-700">+{{ \App\Support\MoneyCalculator::formatIdr($reconciliation['total_in']) }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Total Keluar (Outflows):</span>
                        <span class="font-mono text-rose-700">-{{ \App\Support\MoneyCalculator::formatIdr($reconciliation['total_out']) }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Saldo Net Ledger:</span>
                        <strong class="font-mono text-slate-800">{{ \App\Support\MoneyCalculator::formatIdr($reconciliation['ledger_balance']) }}</strong>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-500">Selisih (Difference):</span>
                        <strong class="font-mono {{ $reconciliation['is_consistent'] ? 'text-slate-700' : 'text-rose-700' }}">
                            {{ \App\Support\MoneyCalculator::formatIdr($reconciliation['difference']) }}
                        </strong>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 mt-2">
                <p class="text-[11px] text-slate-400 italic">Buku besar kas dilindungi oleh prinsip append-only dan larangan saldo negatif.</p>
            </div>
        </div>
    </div>

    <!-- Filters Toolbar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <form method="GET" action="{{ route('cash-accounts.show', $cashAccount) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="from_date" class="block text-xs font-medium text-slate-700 mb-1">Dari Tanggal</label>
                <input type="date" 
                       name="from_date" 
                       id="from_date" 
                       value="{{ request('from_date') }}" 
                       class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
            </div>

            <div>
                <label for="to_date" class="block text-xs font-medium text-slate-700 mb-1">Sampai Tanggal</label>
                <input type="date" 
                       name="to_date" 
                       id="to_date" 
                       value="{{ request('to_date') }}" 
                       class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
            </div>

            <div>
                <label for="direction" class="block text-xs font-medium text-slate-700 mb-1">Arah Arus Kas</label>
                <select name="direction" id="direction" class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
                    <option value="">Semua Arah</option>
                    <option value="in" {{ request('direction') === 'in' ? 'selected' : '' }}>Kas Masuk (+)</option>
                    <option value="out" {{ request('direction') === 'out' ? 'selected' : '' }}>Kas Keluar (-)</option>
                </select>
            </div>

            <div>
                <label for="movement_type" class="block text-xs font-medium text-slate-700 mb-1">Jenis Mutasi</label>
                <select name="movement_type" id="movement_type" class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs">
                    <option value="">Semua Jenis Mutasi</option>
                    <option value="OPENING_BALANCE" {{ request('movement_type') === 'OPENING_BALANCE' ? 'selected' : '' }}>Saldo Awal (OPENING_BALANCE)</option>
                    <option value="SALE_PAYMENT" {{ request('movement_type') === 'SALE_PAYMENT' ? 'selected' : '' }}>Penjualan (SALE_PAYMENT)</option>
                    <option value="EXPENSE_PAYMENT" {{ request('movement_type') === 'EXPENSE_PAYMENT' ? 'selected' : '' }}>Beban Operasional (EXPENSE_PAYMENT)</option>
                    <option value="SUPPLIER_PAYMENT" {{ request('movement_type') === 'SUPPLIER_PAYMENT' ? 'selected' : '' }}>Pembayaran Supplier (SUPPLIER_PAYMENT)</option>
                    <option value="REFUND_PAYMENT" {{ request('movement_type') === 'REFUND_PAYMENT' ? 'selected' : '' }}>Pengembalian Dana (REFUND_PAYMENT)</option>
                    <option value="MANUAL_INFLOW" {{ request('movement_type') === 'MANUAL_INFLOW' ? 'selected' : '' }}>Kas Masuk Manual (MANUAL_INFLOW)</option>
                    <option value="MANUAL_OUTFLOW" {{ request('movement_type') === 'MANUAL_OUTFLOW' ? 'selected' : '' }}>Kas Keluar Manual (MANUAL_OUTFLOW)</option>
                    <option value="REVERSAL" {{ request('movement_type') === 'REVERSAL' ? 'selected' : '' }}>Pembalikan (REVERSAL)</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-xs">
                    Filter
                </button>
                @if(request()->hasAny(['from_date', 'to_date', 'direction', 'movement_type']))
                    <a href="{{ route('cash-accounts.show', $cashAccount) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Table of Cash Movements -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/75 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Jenis Mutasi</th>
                        <th class="py-3 px-4">No. Dokumen / Referensi</th>
                        <th class="py-3 px-4 text-right">Masuk (+)</th>
                        <th class="py-3 px-4 text-right">Keluar (-)</th>
                        <th class="py-3 px-4 text-right">Sebelum</th>
                        <th class="py-3 px-4 text-right">Sesudah</th>
                        <th class="py-3 px-4">Petugas</th>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $mov)
                        <tr class="hover:bg-slate-50/50 transition {{ $mov->isReversed() ? 'opacity-60 bg-slate-50/30' : '' }}">
                            <td class="py-3 px-4 text-xs font-mono text-slate-700 whitespace-nowrap">
                                {{ $mov->occurred_at ? $mov->occurred_at->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($mov->movement_type === 'REVERSAL')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200">
                                        {{ $mov->movement_type_label }}
                                    </span>
                                @elseif($mov->direction === 'in')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        {{ $mov->movement_type_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-50 text-rose-800 border border-rose-200">
                                        {{ $mov->movement_type_label }}
                                    </span>
                                @endif

                                @if($mov->isReversed())
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200 text-slate-700 ml-1">
                                        Telah Dibalikkan
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-slate-700 whitespace-nowrap">
                                {{ $mov->source_number ?? ($mov->source_type . ' #' . $mov->source_id) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-medium text-emerald-700 whitespace-nowrap">
                                @if($mov->direction === 'in')
                                    +{{ \App\Support\MoneyCalculator::formatIdr($mov->amount) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-medium text-rose-700 whitespace-nowrap">
                                @if($mov->direction === 'out')
                                    -{{ \App\Support\MoneyCalculator::formatIdr($mov->amount) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs text-slate-500 whitespace-nowrap">
                                {{ \App\Support\MoneyCalculator::formatIdr($mov->balance_before) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs font-bold text-slate-900 whitespace-nowrap">
                                {{ \App\Support\MoneyCalculator::formatIdr($mov->balance_after) }}
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-600 whitespace-nowrap">
                                {{ $mov->creator?->name ?? 'System' }}
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-500 max-w-xs truncate" title="{{ $mov->description }}">
                                {{ $mov->description ?: '-' }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @can('store_settings.manage')
                                    @if(! $mov->isReversed() && $mov->movement_type !== 'REVERSAL' && $mov->movement_type !== 'OPENING_BALANCE')
                                        <button type="button" 
                                                @click="selectedMovementId = {{ $mov->id }}; selectedMovementDesc = '{{ addslashes($mov->movement_type_label . ' (' . $mov->source_number . ') sebesar ' . \App\Support\MoneyCalculator::formatIdr($mov->amount)) }}'; reversalModalOpen = true"
                                                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition">
                                            Reversal
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-10 text-center text-slate-500">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm font-semibold text-slate-700">Belum ada catatan mutasi kas pada akun ini</p>
                                <p class="text-xs text-slate-400 mt-0.5">Seluruh arus masuk dan keluar kas/bank akan tercatat di buku kas ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($movements->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $movements->links() }}
            </div>
        @endif
    </div>

    <!-- Reversal Confirmation Modal -->
    <div x-show="reversalModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 flex items-center justify-center p-4">
        <div @click.away="reversalModalOpen = false" 
             class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Konfirmasi Pembalikan Kas (Reversal)</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Tindakan ini akan membuat baris mutasi kompensasi baru di buku besar kas.</p>
                </div>
                <button type="button" @click="reversalModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    ✕
                </button>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                <p class="font-semibold mb-1">Transaksi yang akan dibalikkan:</p>
                <p x-text="selectedMovementDesc"></p>
            </div>

            <form :action="'{{ url('cash-accounts/' . $cashAccount->id . '/reverse') }}/' + selectedMovementId" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label for="reason" class="block text-xs font-semibold text-slate-700 mb-1">Alasan Pembalikan (Wajib) <span class="text-rose-500">*</span></label>
                    <textarea name="reason" 
                              id="reason" 
                              rows="3" 
                              required 
                              placeholder="Masukkan alasan pembalikan transaksi secara jelas..."
                              class="w-full text-sm rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 shadow-xs"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="reversalModalOpen = false" class="px-4 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-xs transition">
                        Posting Pembalikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
