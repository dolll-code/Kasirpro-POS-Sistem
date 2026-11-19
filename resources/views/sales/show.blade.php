@extends('layouts.app')

@section('title', 'Detail Transaksi ' . $sale->transaction_number)

@section('content')
<div class="space-y-6">
    <!-- Top Bar -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('sales.index') }}" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-lg transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-white font-mono">{{ $sale->transaction_number }}</h1>
                    {!! $sale->sale_status_badge !!}
                    {!! $sale->payment_status_badge !!}
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    Selesai pada: <strong class="text-slate-200">{{ $sale->completed_at ? $sale->completed_at->format('d F Y, H:i:s') : '-' }}</strong> &bull;
                    Kasir: <strong class="text-slate-200">{{ $sale->cashier?->name }}</strong>
                </p>
            </div>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <a href="{{ route('sales.receipt', ['sale' => $sale, 'reprint' => 1]) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Cetak Struk [Reprint]
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60">
            <span class="text-xs font-semibold text-slate-400 uppercase">Grand Total Belanja</span>
            <p class="text-xl font-bold font-mono text-emerald-400 mt-1">
                Rp {{ number_format((float) $sale->grand_total, 0, ',', '.') }}
            </p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60">
            <span class="text-xs font-semibold text-slate-400 uppercase">Uang Diterima (Tender)</span>
            <p class="text-xl font-bold font-mono text-white mt-1">
                Rp {{ number_format((float) ($sale->payment?->cash_tendered ?? 0), 0, ',', '.') }}
            </p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60">
            <span class="text-xs font-semibold text-slate-400 uppercase">Kembalian (Change)</span>
            <p class="text-xl font-bold font-mono text-blue-400 mt-1">
                Rp {{ number_format((float) ($sale->payment?->change_amount ?? 0), 0, ',', '.') }}
            </p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60">
            <span class="text-xs font-semibold text-slate-400 uppercase">Total Diskon Diberikan</span>
            <p class="text-xl font-bold font-mono text-rose-400 mt-1">
                Rp {{ number_format((float) $sale->totalDiscount(), 0, ',', '.') }}
            </p>
        </div>
    </div>

    @if($sale->discount_approved_by)
        <div class="bg-amber-950/40 border border-amber-800/60 rounded-xl p-4 flex items-center gap-3">
            <svg class="h-5 w-5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <div class="text-xs text-amber-200">
                Diskon khusus disetujui oleh Supervisor: <strong class="text-white">{{ $sale->discountApprover?->name }}</strong>.
                @if($sale->discount_override_reason)
                    <span class="text-amber-300">Alasan: "{{ $sale->discount_override_reason }}"</span>
                @endif
            </div>
        </div>
    @endif

    <!-- Sale Items Breakdown -->
    <div class="bg-slate-800 rounded-xl border border-slate-700/60 overflow-hidden shadow-sm">
        <div class="p-4 bg-slate-850 border-b border-slate-700 flex justify-between items-center">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider">Rincian Barang Belanjaan</h3>
            <span class="text-xs text-slate-400 font-mono">{{ $sale->items->count() }} jenis barang</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-700 text-xs text-slate-300">
                <thead class="bg-slate-900 text-slate-400 font-semibold uppercase tracking-wider text-left">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Kuantitas</th>
                        <th class="px-4 py-3 text-right">Harga Satuan</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        <th class="px-4 py-3 text-right">Diskon Baris</th>
                        <th class="px-4 py-3 text-right">Alokasi Diskon Nota</th>
                        <th class="px-4 py-3 text-right">Total Bersih</th>
                        @if(auth()->user()->hasPermission('sales.view_all'))
                            <th class="px-4 py-3 text-right bg-slate-950/40 text-slate-400">HPP Snapshot</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60">
                    @foreach($sale->items as $item)
                        <tr class="hover:bg-slate-750">
                            <td class="px-4 py-3">
                                <div class="font-bold text-white">{{ $item->product_name_snapshot }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ $item->sku_snapshot }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-white">
                                {{ number_format((float) $item->quantity, 3) }} {{ $item->unit_name_snapshot }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                Rp {{ number_format((float) $item->unit_price_snapshot, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-200">
                                Rp {{ number_format((float) $item->gross_line_total, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-rose-400">
                                {{ (float) $item->line_discount > 0 ? '- Rp ' . number_format((float) $item->line_discount, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-rose-400">
                                {{ (float) $item->allocated_transaction_discount > 0 ? '- Rp ' . number_format((float) $item->allocated_transaction_discount, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-400">
                                Rp {{ number_format((float) $item->net_line_total, 0, ',', '.') }}
                            </td>
                            @if(auth()->user()->hasPermission('sales.view_all'))
                                <td class="px-4 py-3 text-right font-mono text-slate-400 bg-slate-950/20">
                                    Rp {{ number_format((float) $item->cost_price_snapshot, 0, ',', '.') }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-850 font-bold text-xs text-white divide-y divide-slate-700 border-t border-slate-700">
                    <tr>
                        <td colspan="3" class="px-4 py-2.5 text-right uppercase text-slate-400">Subtotal Kotor:</td>
                        <td class="px-4 py-2.5 text-right font-mono">Rp {{ number_format((float) $sale->gross_total, 0, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-rose-400">- Rp {{ number_format((float) $sale->item_discount_total, 0, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-rose-400">- Rp {{ number_format((float) $sale->transaction_discount_total, 0, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-emerald-400 text-sm">Rp {{ number_format((float) $sale->grand_total, 0, ',', '.') }}</td>
                        @if(auth()->user()->hasPermission('sales.view_all'))
                            <td class="bg-slate-950/40"></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
