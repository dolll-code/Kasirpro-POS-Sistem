@extends('layouts.app')

@section('title', 'Daftar Purchase Order (PO)')

@section('content')
<div class="space-y-6">
    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1">
                    <li><a href="{{ route('dashboard') }}" class="hover:text-slate-700">Beranda</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li class="text-slate-800 font-medium">Pembelian & Pengadaan</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Purchase Order & Pengadaan</h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola pesanan pembelian barang ke pemasok, penerimaan fisik, dan pembayaran utang dagang.</p>
        </div>

        @if(auth()->user()->hasPermission('purchases.create'))
            <div>
                <a href="{{ route('purchases.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Buat PO Baru
                </a>
            </div>
        @endif
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total PO</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalPurchases, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-gray-600 uppercase tracking-wider">Draft</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ number_format($totalDraft, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-blue-600 uppercase tracking-wider">Dipesan (Ordered)</p>
            <p class="text-2xl font-bold text-blue-700 mt-1">{{ number_format($totalOrdered, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Parsial Diterima</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ number_format($totalPartiallyReceived, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Diterima Penuh</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($totalReceived, 0, ',', '.') }}</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <form method="GET" action="{{ route('purchases.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Cari No. PO / Supplier</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Contoh: PO-2026..., PT Sumber..." class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status PO</label>
                <select name="status" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                    <option value="">Semua Status</option>
                    <option value="draft" {{ ($status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="ordered" {{ ($status ?? '') === 'ordered' ? 'selected' : '' }}>Dipesan (Ordered)</option>
                    <option value="partially_received" {{ ($status ?? '') === 'partially_received' ? 'selected' : '' }}>Diterima Sebagian</option>
                    <option value="received" {{ ($status ?? '') === 'received' ? 'selected' : '' }}>Diterima Penuh</option>
                    <option value="cancelled" {{ ($status ?? '') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status Bayar</label>
                <select name="payment_status" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                    <option value="">Semua Status Bayar</option>
                    <option value="unpaid" {{ ($paymentStatus ?? '') === 'unpaid' ? 'selected' : '' }}>Belum Dibayar</option>
                    <option value="partially_paid" {{ ($paymentStatus ?? '') === 'partially_paid' ? 'selected' : '' }}>Dibayar Sebagian</option>
                    <option value="paid" {{ ($paymentStatus ?? '') === 'paid' ? 'selected' : '' }}>Lunas</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Pemasok</label>
                <select name="supplier_id" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                    <option value="">Semua Pemasok</option>
                    @foreach($activeSuppliers as $sup)
                        <option value="{{ $sup->id }}" {{ (string)($supplierId ?? '') === (string)$sup->id ? 'selected' : '' }}>
                            {{ $sup->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition">
                    Filter
                </button>
                @if(!empty($search) || !empty($status) || !empty($paymentStatus) || !empty($supplierId) || !empty($fromDate) || !empty($toDate))
                    <a href="{{ route('purchases.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg transition" title="Reset filter">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- PO Table Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">No. Purchase Order</th>
                        <th class="px-4 py-3">Tanggal Pesan</th>
                        <th class="px-4 py-3">Pemasok</th>
                        <th class="px-4 py-3 text-center">Item</th>
                        <th class="px-4 py-3 text-right">Estimasi Tagihan</th>
                        <th class="px-4 py-3 text-center">Status PO</th>
                        <th class="px-4 py-3 text-center">Status Bayar</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($purchases as $po)
                        <tr class="hover:bg-slate-50/75 transition">
                            <td class="px-4 py-3.5">
                                <a href="{{ route('purchases.show', $po) }}" class="font-mono font-semibold text-slate-900 hover:text-blue-600">
                                    {{ $po->purchase_number }}
                                </a>
                                @if($po->notes)
                                    <p class="text-xs text-slate-400 truncate max-w-xs">{{ $po->notes }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-xs">
                                {{ $po->order_date->format('d/m/Y') }}
                                @if($po->expected_date)
                                    <span class="block text-slate-400 text-[11px]">Est: {{ $po->expected_date->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="font-medium text-slate-900">{{ $po->supplier?->name ?? '—' }}</span>
                                <span class="block text-xs text-slate-400 font-mono">{{ $po->supplier?->supplier_code }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800">
                                    {{ $po->items_count }} item
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap font-mono">
                                <span class="font-semibold text-slate-900">Rp {{ number_format((float)$po->payable_total, 2, ',', '.') }}</span>
                                @if((float)$po->paid_amount > 0)
                                    <span class="block text-[11px] text-emerald-600">Dibayar: Rp {{ number_format((float)$po->paid_amount, 2, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $po->status_badge_class }}">
                                    {{ $po->status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $po->payment_status_badge_class }}">
                                    {{ $po->payment_status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('purchases.show', $po) }}" class="px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded transition">
                                        Detail
                                    </a>
                                    @if($po->canEdit() && auth()->user()->hasPermission('purchases.update_draft'))
                                        <a href="{{ route('purchases.edit', $po) }}" class="px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded transition">
                                            Edit
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <p class="text-base font-semibold text-slate-700">Belum ada Purchase Order</p>
                                <p class="text-xs text-slate-400 mt-1">Gunakan tombol "Buat PO Baru" untuk membuat pesanan pembelian barang ke pemasok.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($purchases->hasPages())
            <div class="px-4 py-3 border-t border-slate-200">
                {{ $purchases->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
