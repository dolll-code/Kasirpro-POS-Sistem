@extends('layouts.app')

@section('title', 'Detail Purchase Order #' . $purchase->purchase_number)

@section('content')
<div class="space-y-6" x-data="{ cancelModalOpen: false, cancelReason: '' }">
    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1">
                    <li><a href="{{ route('dashboard') }}" class="hover:text-slate-700">Beranda</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li><a href="{{ route('purchases.index') }}" class="hover:text-slate-700">Pembelian</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li class="text-slate-800 font-medium">{{ $purchase->purchase_number }}</li>
                </ol>
            </nav>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 font-mono">{{ $purchase->purchase_number }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $purchase->status_badge_class }}">
                    {{ $purchase->status_label }}
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $purchase->payment_status_badge_class }}">
                    {{ $purchase->payment_status_label }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Dibuat oleh <span class="font-medium text-slate-700">{{ $purchase->creator?->name ?? 'Sistem' }}</span> pada {{ $purchase->created_at->format('d/m/Y H:i') }} WIB</p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('purchases.index') }}" class="px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                Kembali
            </a>

            @if($purchase->canEdit() && auth()->user()->hasPermission('purchases.update_draft'))
                <a href="{{ route('purchases.edit', $purchase) }}" class="px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                    Edit Draft
                </a>
            @endif

            @if($purchase->canConfirm() && auth()->user()->hasPermission('purchases.confirm'))
                <form method="POST" action="{{ route('purchases.confirm', $purchase) }}" onsubmit="return confirm('Konfirmasi Purchase Order ini? Dokumen akan dikunci dan berstatus dipesan (ordered).');">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition">
                        Konfirmasi Pesanan (Confirm)
                    </button>
                </form>
            @endif

            @if($purchase->canReceive() && auth()->user()->hasPermission('receipts.create'))
                <a href="{{ route('purchases.receipts.create', $purchase) }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Terima Barang Fisik
                </a>
            @endif

            @if($outstandingPayable->isGreaterThan(\App\Support\Decimal::zero(2)) && auth()->user()->hasPermission('supplier_payments.create'))
                <a href="{{ route('purchases.payments.create', $purchase) }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Bayar Pemasok
                </a>
            @endif

            @if($purchase->canCancel() && auth()->user()->hasPermission('purchases.cancel'))
                <button type="button" @click="cancelModalOpen = true" class="px-3 py-2 text-sm font-medium text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100 transition">
                    Batalkan / Tutup PO
                </button>
            @endif
        </div>
    </div>

    <!-- Metadata Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Supplier Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Informasi Pemasok</h2>
            <div>
                <p class="text-lg font-bold text-slate-900">{{ $purchase->supplier?->name ?? '—' }}</p>
                <p class="text-xs font-mono text-slate-500">Kode: {{ $purchase->supplier?->supplier_code }}</p>
            </div>
            <div class="text-xs text-slate-600 space-y-1 pt-2 border-t border-slate-100">
                <p><span class="text-slate-400">Kontak Person:</span> {{ $purchase->supplier?->contact_person ?? '—' }}</p>
                <p><span class="text-slate-400">Telepon:</span> {{ $purchase->supplier?->phone ?? '—' }}</p>
                <p><span class="text-slate-400">Alamat:</span> {{ $purchase->supplier?->address ?? '—' }}</p>
            </div>
        </div>

        <!-- Schedule & Timeline Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Jadwal & Waktu</h2>
            <div class="text-xs text-slate-700 space-y-2">
                <div class="flex justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-400">Tanggal Pesanan:</span>
                    <span class="font-medium text-slate-900">{{ $purchase->order_date->format('d F Y') }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-400">Estimasi Tiba:</span>
                    <span class="font-medium text-slate-900">{{ $purchase->expected_date ? $purchase->expected_date->format('d F Y') : '—' }}</span>
                </div>
                @if($purchase->confirmed_at)
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-400">Dikonfirmasi Pada:</span>
                        <span class="font-medium text-slate-900">{{ $purchase->confirmed_at->format('d/m/Y H:i') }} ({{ $purchase->confirmer?->name }})</span>
                    </div>
                @endif
                @if($purchase->cancelled_at)
                    <div class="flex justify-between py-1 text-rose-600">
                        <span>Dibatalkan Pada:</span>
                        <span class="font-medium">{{ $purchase->cancelled_at->format('d/m/Y H:i') }} ({{ $purchase->canceller?->name }})</span>
                    </div>
                    @if($purchase->cancel_reason)
                        <p class="text-[11px] bg-rose-50 text-rose-700 p-2 rounded border border-rose-200">
                            <strong>Alasan:</strong> {{ $purchase->cancel_reason }}
                        </p>
                    @endif
                @endif
            </div>
        </div>

        <!-- Financial Overview Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-2 font-mono text-xs">
            <h2 class="text-xs font-sans font-semibold uppercase tracking-wider text-slate-400 mb-2">Ringkasan Finansial Tagihan</h2>
            <div class="flex justify-between text-slate-600">
                <span class="font-sans">Estimasi Total PO:</span>
                <span class="font-semibold text-slate-800">Rp {{ number_format((float)$purchase->payable_total, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
                <span class="font-sans">Tagihan Barang Diterima (Accepted):</span>
                <span class="font-semibold text-blue-700">Rp {{ number_format((float)$totalAcceptedPayable->toString(), 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
                <span class="font-sans">Sudah Dibayar ke Pemasok:</span>
                <span class="font-semibold text-emerald-600">Rp {{ number_format((float)$purchase->paid_amount, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between pt-2 border-t border-slate-200 text-sm font-bold">
                <span class="font-sans text-slate-900">Sisa Utang Belum Lunas:</span>
                <span class="{{ $outstandingPayable->isGreaterThan(\App\Support\Decimal::zero(2)) ? 'text-rose-600' : 'text-slate-400' }}">
                    Rp {{ number_format((float)$outstandingPayable->toString(), 2, ',', '.') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Items Table Card with Progress -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">Rincian Barang & Progres Penerimaan</h2>
            <span class="text-xs text-slate-400 font-mono">{{ $purchase->items->count() }} item</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Dipesan</th>
                        <th class="px-4 py-3 text-right">Diterima (Acc)</th>
                        <th class="px-4 py-3 text-right">Ditolak (Rej)</th>
                        <th class="px-4 py-3 text-right">Dibatalkan</th>
                        <th class="px-4 py-3 text-right">Sisa Belum Tiba</th>
                        <th class="px-4 py-3 text-right">Harga Beli Satuan</th>
                        <th class="px-4 py-3 text-right">Diskon Alokasi</th>
                        <th class="px-4 py-3 text-right">Subtotal Baris</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-xs font-mono">
                    @foreach($purchase->items as $idx => $it)
                        <tr class="hover:bg-slate-50/75 transition">
                            <td class="px-4 py-3 text-slate-400">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-sans">
                                <span class="font-semibold text-slate-900">{{ $it->product_name_snapshot }}</span>
                                <span class="block text-[11px] font-mono text-slate-400">{{ $it->sku_snapshot }} &bull; Satuan: {{ $it->unit_snapshot }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                {{ number_format((float)$it->ordered_qty, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-emerald-600 font-semibold">
                                {{ number_format((float)$it->accepted_qty_total, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-rose-600">
                                {{ number_format((float)$it->rejected_qty_total, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-slate-400">
                                {{ number_format((float)$it->cancelled_qty, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold {{ $it->remainingQty()->isGreaterThan(\App\Support\Decimal::zero(3)) ? 'text-amber-600' : 'text-slate-400' }}">
                                {{ number_format((float)$it->remainingQty()->toString(), 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-slate-800">
                                Rp {{ number_format((float)$it->unit_cost, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-rose-600">
                                Rp {{ number_format((float)$it->allocated_document_discount + (float)$it->line_discount, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">
                                Rp {{ number_format((float)$it->line_total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Receipts History Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Riwayat Dokumen Penerimaan Barang (Goods Receipts)</h2>
                <p class="text-xs text-slate-400">Setiap penerimaan fisik diterima (accepted) menambah stok fisik dan memperbarui HPP rata-rata.</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 font-mono">
                {{ $purchase->receipts->count() }} Penerimaan
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-2.5">No. Penerimaan</th>
                        <th class="px-4 py-2.5">Waktu Terima</th>
                        <th class="px-4 py-2.5">Penerima</th>
                        <th class="px-4 py-2.5 text-right">Biaya Tambahan Landed</th>
                        <th class="px-4 py-2.5 text-center">Status</th>
                        <th class="px-4 py-2.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($purchase->receipts as $rcv)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900">
                                <a href="{{ route('purchases.receipts.show', [$purchase, $rcv]) }}" class="hover:text-blue-600">
                                    {{ $rcv->receipt_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $rcv->received_at->format('d/m/Y H:i') }} WIB</td>
                            <td class="px-4 py-3">{{ $rcv->receiver?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">
                                Rp {{ number_format((float)$rcv->capitalizable_additional_cost, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-800">
                                    Diposting (Stok Bertambah)
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('purchases.receipts.show', [$purchase, $rcv]) }}" class="px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded transition">
                                    Detail RCV
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                Belum ada riwayat penerimaan barang fisik untuk PO ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payments History Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Riwayat Pembayaran Pemasok (Supplier Payments)</h2>
                <p class="text-xs text-slate-400">Catatan pembayaran manual yang mengurangi saldo kas dan memotong sisa utang barang diterima.</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 font-mono">
                {{ $purchase->payments->count() }} Pembayaran
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-2.5">No. Pembayaran</th>
                        <th class="px-4 py-2.5">Tanggal Bayar</th>
                        <th class="px-4 py-2.5">Akun Kas / Bank</th>
                        <th class="px-4 py-2.5">Metode</th>
                        <th class="px-4 py-2.5 text-right">Nominal (Rp)</th>
                        <th class="px-4 py-2.5 text-center">Status</th>
                        <th class="px-4 py-2.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($purchase->payments as $pay)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900">
                                {{ $pay->payment_number }}
                                @if($pay->reference_number)
                                    <span class="block text-[11px] text-slate-400 font-sans">Ref: {{ $pay->reference_number }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $pay->paid_at->format('d/m/Y H:i') }} WIB</td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-slate-800">{{ $pay->cashAccount?->name }}</span>
                                <span class="block text-[11px] font-mono text-slate-400">{{ $pay->cashAccount?->code }}</span>
                            </td>
                            <td class="px-4 py-3 uppercase">{{ $pay->method }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-slate-900">
                                Rp {{ number_format((float)$pay->amount, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($pay->isReversed())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-rose-100 text-rose-800" title="{{ $pay->reversal_reason }}">
                                        Dibalikkan (Reversed)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-800">
                                        Sah / Terbayar
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(!$pay->isReversed() && auth()->user()->hasPermission('supplier_payments.reverse'))
                                    <form method="POST" action="{{ route('purchases.payments.reverse', [$purchase, $pay]) }}" onsubmit="return confirm('Apakah Anda yakin ingin membalikkan (reversal) pembayaran ini? Saldo kas akan dipulihkan.');">
                                        @csrf
                                        <input type="hidden" name="reason" value="Pembalikan transaksi pembayaran oleh pengguna">
                                        <button type="submit" class="px-2.5 py-1 text-xs font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 rounded transition">
                                            Reversal
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                Belum ada riwayat pembayaran supplier untuk PO ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Cancellation Modal -->
    <div x-show="cancelModalOpen" x-cloak class="relative z-50" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 lg:p-8 flex items-center justify-center">
            <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:w-full sm:max-w-md p-6">
                <h3 class="text-lg font-bold text-slate-900 mb-2">Batalkan / Tutup Sisa Purchase Order</h3>
                <p class="text-xs text-slate-500 mb-4">
                    Barang yang sudah diterima secara sah (accepted) akan tetap berada di inventori. Sisa kuantitas barang yang belum tiba akan ditutup permanen.
                </p>

                <form method="POST" action="{{ route('purchases.cancel', $purchase) }}">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Alasan Pembatalan / Penutupan Sisa PO <span class="text-rose-500">*</span></label>
                        <textarea name="reason" rows="3" required placeholder="Contoh: Supplier kehabisan stok, harga tidak sesuai..." class="w-full text-sm border border-slate-300 rounded-lg p-2.5 focus:ring-1 focus:ring-slate-500 focus:border-slate-500"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="cancelModalOpen = false" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                            Tutup
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-sm transition">
                            Konfirmasi Pembatalan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
