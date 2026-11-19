@extends('layouts.app')

@section('title', 'Riwayat Transaksi Penjualan')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Riwayat Transaksi Penjualan</h1>
            <p class="mt-1 text-sm text-slate-400">Daftar transaksi kasir, rincian pembayaran tunai, dan cetak ulang bukti belanja.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            @if(auth()->user()->hasPermission('sales.create'))
                <a href="{{ route('pos.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 transition-colors">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Buka Mesin Kasir (POS)
                </a>
            @endif
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Omset Penjualan Hari Ini</span>
            <p class="text-2xl font-extrabold text-emerald-400 font-mono mt-1">
                Rp {{ number_format((float) ($todayStats->total_revenue ?? 0), 0, ',', '.') }}
            </p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Transaksi Selesai Hari Ini</span>
            <p class="text-2xl font-extrabold text-blue-400 font-mono mt-1">
                {{ number_format((int) ($todayStats->trx_count ?? 0)) }} <span class="text-xs font-normal text-slate-400">struk</span>
            </p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Diskon Toko Hari Ini</span>
            <p class="text-2xl font-extrabold text-rose-400 font-mono mt-1">
                Rp {{ number_format((float) ($todayStats->total_discount ?? 0), 0, ',', '.') }}
            </p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60">
        <form method="GET" action="{{ route('sales.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Cari Nomor Transaksi</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="TRX-YYYYMMDD-..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-500 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" value="{{ request('start_date') }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:ring-emerald-500">
            </div>

            @if($canViewAll)
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Kasir</label>
                    <select name="cashier_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:ring-emerald-500">
                        <option value="">Semua Kasir</option>
                        @foreach($cashiers as $c)
                            <option value="{{ $c->id }}" {{ request('cashier_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold transition-colors">
                    Terapkan
                </button>
                <a href="{{ route('sales.index') }}" class="py-2 px-3 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg text-xs font-medium transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Sales Table -->
    <div class="bg-slate-800 rounded-xl border border-slate-700/60 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-700">
                <thead class="bg-slate-850 text-slate-400 text-[11px] font-semibold uppercase tracking-wider text-left">
                    <tr>
                        <th class="px-4 py-3">Nomor Transaksi</th>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Kasir</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        <th class="px-4 py-3 text-right">Diskon</th>
                        <th class="px-4 py-3 text-right">Grand Total</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60 text-xs text-slate-300">
                    @forelse($sales as $sale)
                        <tr class="hover:bg-slate-750 transition-colors">
                            <td class="px-4 py-3 font-mono font-bold text-emerald-400">
                                <a href="{{ route('sales.show', $sale) }}" class="hover:underline">
                                    {{ $sale->transaction_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $sale->completed_at ? $sale->completed_at->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-4 py-3 font-medium text-white">
                                {{ $sale->cashier?->name ?? 'Kasir #' . $sale->cashier_id }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                Rp {{ number_format((float) $sale->gross_total, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-rose-400">
                                Rp {{ number_format((float) $sale->totalDiscount(), 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-white">
                                Rp {{ number_format((float) $sale->grand_total, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {!! $sale->sale_status_badge !!}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center text-xs text-slate-300 hover:text-white font-medium">
                                    Detail
                                </a>
                                <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="inline-flex items-center text-xs text-emerald-400 hover:text-emerald-300 font-semibold">
                                    Cetak Struk
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                                Belum ada riwayat transaksi penjualan yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sales->hasPages())
            <div class="px-4 py-3 bg-slate-850 border-t border-slate-700">
                {{ $sales->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
