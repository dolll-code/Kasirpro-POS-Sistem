@extends('layouts.app')

@section('title', 'Dokumen Penerimaan Barang #' . $receipt->receipt_number)

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1">
                    <li><a href="{{ route('dashboard') }}" class="hover:text-slate-700">Beranda</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li><a href="{{ route('purchases.index') }}" class="hover:text-slate-700">Pembelian</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li><a href="{{ route('purchases.show', $purchase) }}" class="hover:text-slate-700">{{ $purchase->purchase_number }}</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li class="text-slate-800 font-medium font-mono">{{ $receipt->receipt_number }}</li>
                </ol>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 font-mono">{{ $receipt->receipt_number }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-300">
                    Diposting (Stok Bertambah)
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Penerimaan barang fisik dari pemasok <strong>{{ $purchase->supplier?->name }}</strong>.</p>
        </div>

        <div>
            <a href="{{ route('purchases.show', $purchase) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali ke Detail PO
            </a>
        </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs space-y-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Waktu & Petugas</p>
            <p class="text-sm font-medium text-slate-900">{{ $receipt->received_at->format('d F Y, H:i') }} WIB</p>
            <p class="text-xs text-slate-500">Penerima: {{ $receipt->receiver?->name ?? '—' }}</p>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs space-y-1 font-mono">
            <p class="text-xs font-sans font-semibold uppercase tracking-wider text-slate-400">Biaya Tambahan Landed</p>
            <p class="text-sm font-bold text-slate-900">Rp {{ number_format((float)$receipt->capitalizable_additional_cost, 2, ',', '.') }}</p>
            <p class="text-[11px] font-sans text-slate-500">Dialokasikan ke HPP barang diterima.</p>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs space-y-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Catatan Dokumen</p>
            <p class="text-sm text-slate-700">{{ $receipt->notes ?: 'Tidak ada catatan tambahan.' }}</p>
        </div>
    </div>

    <!-- Items Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="text-base font-semibold text-slate-900">Rincian Barang yang Diterima</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Produk & Satuan</th>
                        <th class="px-4 py-3 text-right">Fisik Datang</th>
                        <th class="px-4 py-3 text-right">Diterima Sah (Acc)</th>
                        <th class="px-4 py-3 text-right">Ditolak (Rej)</th>
                        <th class="px-4 py-3 text-right">HPP Landed Satuan</th>
                        <th class="px-4 py-3 text-right">Total Nilai Landed</th>
                        <th class="px-4 py-3">Alasan Penolakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-xs font-mono">
                    @foreach($receipt->items as $idx => $it)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 text-slate-400">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-sans">
                                <span class="font-semibold text-slate-900">{{ $it->product?->name }}</span>
                                <span class="block text-[11px] font-mono text-slate-400">{{ $it->product?->sku }} &bull; Satuan: {{ $it->product?->unit?->symbol }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-800">
                                {{ number_format((float)$it->physically_received_qty, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600">
                                {{ number_format((float)$it->accepted_qty, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-rose-600 font-medium">
                                {{ number_format((float)$it->rejected_qty, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-slate-900 font-semibold">
                                Rp {{ number_format((float)$it->landed_unit_cost, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-blue-700">
                                Rp {{ number_format((float)$it->landed_total_cost, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 font-sans text-slate-500">
                                {{ $it->rejection_reason ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
