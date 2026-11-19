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
                    Waktu Checkout: <strong class="text-slate-200">{{ $sale->created_at ? $sale->created_at->format('d F Y, H:i:s') : '-' }}</strong> &bull;
                    Kasir: <strong class="text-slate-200">{{ $sale->cashier?->name }}</strong>
                    @if($sale->completed_at)
                        &bull; Selesai pada: <strong class="text-emerald-400">{{ $sale->completed_at->format('d F Y, H:i:s') }}</strong>
                    @endif
                </p>
            </div>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            @if($sale->isCompleted())
                <a href="{{ route('sales.receipt', ['sale' => $sale, 'reprint' => 1]) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Cetak Struk [Reprint]
                </a>
            @endif
        </div>
    </div>

    <!-- Status Timeline (PRD §7.6) -->
    <div class="bg-slate-800 rounded-xl p-4 border border-slate-700/60">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Timeline Status Transaksi</h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-3 rounded-lg border {{ $sale->created_at ? 'bg-emerald-950/30 border-emerald-800/60 text-emerald-300' : 'bg-slate-850 border-slate-700 text-slate-400' }}">
                <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full bg-emerald-500 text-slate-900 font-bold flex items-center justify-center text-xs">1</span>
                    <strong class="text-xs">Checkout Kasir</strong>
                </div>
                <div class="text-[11px] mt-1 font-mono text-slate-300">
                    {{ $sale->created_at ? $sale->created_at->format('d/m/Y H:i') : '-' }}
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">
                    Metode: {{ $sale->payment?->payment_method === 'bank_transfer' ? 'Transfer Bank' : 'Tunai' }}
                </div>
            </div>

            <div class="p-3 rounded-lg border {{ $sale->isCompleted() ? 'bg-emerald-950/30 border-emerald-800/60 text-emerald-300' : ($sale->isPendingPayment() ? 'bg-blue-950/40 border-blue-800/60 text-blue-300 ring-1 ring-blue-500/50' : 'bg-slate-850 border-slate-700 text-slate-400') }}">
                <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full {{ $sale->isCompleted() ? 'bg-emerald-500 text-slate-900' : ($sale->isPendingPayment() ? 'bg-blue-500 text-white animate-pulse' : 'bg-slate-700 text-slate-300') }} font-bold flex items-center justify-center text-xs">2</span>
                    <strong class="text-xs">Reservasi Stok (Hold)</strong>
                </div>
                <div class="text-[11px] mt-1 text-slate-300">
                    @if($sale->reservation)
                        Status: <strong class="uppercase font-mono">{{ $sale->reservation->status }}</strong>
                    @else
                        Langsung dipotong fisik (Tunai)
                    @endif
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">
                    {{ $sale->pending_expires_at ? 'Batas: ' . $sale->pending_expires_at->format('H:i:s') : 'Tanpa reservasi' }}
                </div>
            </div>

            <div class="p-3 rounded-lg border {{ $sale->isCompleted() ? 'bg-emerald-950/30 border-emerald-800/60 text-emerald-300' : ($sale->isCancelled() ? 'bg-rose-950/40 border-rose-800/60 text-rose-300' : 'bg-slate-850 border-slate-700 text-slate-400') }}">
                <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full {{ $sale->isCompleted() ? 'bg-emerald-500 text-slate-900' : ($sale->isCancelled() ? 'bg-rose-500 text-white' : 'bg-slate-700 text-slate-300') }} font-bold flex items-center justify-center text-xs">3</span>
                    <strong class="text-xs">Penyelesaian Transaksi</strong>
                </div>
                <div class="text-[11px] mt-1 text-slate-300">
                    @if($sale->isCompleted())
                        <span class="text-emerald-400 font-bold">Terverifikasi & Sah</span>
                    @elseif($sale->isCancelled())
                        <span class="text-rose-400 font-bold">{{ $sale->payment_status === 'expired' ? 'Kedaluwarsa' : 'Ditolak / Dibatalkan' }}</span>
                    @else
                        Menunggu Pengesahan
                    @endif
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">
                    {{ $sale->completed_at ? $sale->completed_at->format('d/m/Y H:i') : ($sale->cancelled_at ? $sale->cancelled_at->format('d/m/Y H:i') : '-') }}
                </div>
            </div>
        </div>
    </div>

    @if($sale->isPendingPayment())
        @php
            $expiresAtTimestamp = $sale->pending_expires_at ? $sale->pending_expires_at->timestamp : now()->timestamp;
            $isSelf = auth()->id() === $sale->cashier_id;
            $isAdmin = auth()->user()->hasRole('admin');
            $canVerify = auth()->user()->hasPermission('payments.verify_transfer');
        @endphp

        <!-- Pending Transfer Verification Panel (PRD §5.5, §6.4, §7.3) -->
        <div class="bg-slate-800 rounded-xl p-5 border-2 border-blue-600/60 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center shrink-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Menunggu Verifikasi Pembayaran Transfer</h3>
                        <p class="text-xs text-slate-400">Periksa mutasi rekening bank dan cocokkan nomor referensi sebelum mengesahkan.</p>
                    </div>
                </div>

                <!-- Live Countdown Timer -->
                <div x-data="countdownTimer({{ $expiresAtTimestamp }})" class="p-2.5 rounded-lg bg-slate-900 border border-blue-700 flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-300">Sisa Reservasi:</span>
                    <span class="font-mono text-base font-extrabold text-blue-400" x-text="displayText"></span>
                </div>
            </div>

            <!-- Transfer Account & Reference Info -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs bg-slate-900 p-3 rounded-lg border border-slate-700/60">
                <div>
                    <span class="text-slate-400 block mb-0.5">Rekening Bank Tujuan:</span>
                    <strong class="text-white">{{ $sale->payment?->cashAccount?->name ?? 'Bank Toko' }}</strong>
                    <span class="text-slate-400 block font-mono text-[10px]">Kode: {{ $sale->payment?->cashAccount?->code }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block mb-0.5">Nomor Referensi / Bukti:</span>
                    <strong class="text-emerald-400 font-mono text-sm">{{ $sale->payment?->reference_number ?: '(Tidak dicantumkan)' }}</strong>
                </div>
                <div>
                    <span class="text-slate-400 block mb-0.5">Total Tagihan Transfer:</span>
                    <strong class="text-white font-mono text-sm">Rp {{ number_format((float) $sale->grand_total, 0, ',', '.') }}</strong>
                </div>
            </div>

            @if($canVerify)
                <!-- Verification Form -->
                <form method="POST" action="{{ route('sales.verify_transfer', $sale) }}" class="space-y-3 pt-2">
                    @csrf

                    @if($isSelf && ! $isAdmin)
                        <!-- Maker-Checker Block Alert -->
                        <div class="p-3 bg-amber-950/60 border border-amber-800/80 rounded-lg text-xs text-amber-200 flex items-start gap-2">
                            <svg class="h-4 w-4 text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div>
                                <strong class="text-white">Prinsip Maker-Checker (PRD §4.2, §6.4):</strong>
                                Anda adalah kasir pembuat transaksi ini dan dilarang memverifikasi transaksi milik sendiri. Minta supervisor atau administrator lain untuk melakukan verifikasi.
                            </div>
                        </div>
                    @else
                        @if($isSelf && $isAdmin)
                            <!-- Admin Override Notice -->
                            <div class="p-3 bg-amber-950/40 border border-amber-800/60 rounded-lg text-xs text-amber-300 space-y-1.5">
                                <strong class="text-white">Admin Override (Maker-Checker):</strong>
                                Anda adalah pembuat transaksi ini sekaligus Administrator. Pengesahan mandiri diizinkan dengan mewajibkan alasan tertulis di bawah demi jejak audit.
                                <div>
                                    <label class="block text-slate-300 font-semibold mb-1">Alasan Wajib Admin Override:</label>
                                    <input type="text" name="admin_override_reason" required placeholder="Contoh: Kasir tunggal bertugas, mutasi BCA telah dicek mandiri" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Catatan Verifikasi / Alasan Penolakan (Opsional jika sah):</label>
                            <input type="text" name="verification_note" placeholder="Contoh: Mutasi rekening telah cocok dan dana masuk" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white">
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="submit" name="decision" value="rejected" onclick="return confirm('Apakah Anda yakin ingin MENOLAK transfer ini? Reservasi stok akan langsung dibatalkan.')" class="px-4 py-2 bg-rose-700 hover:bg-rose-600 text-white rounded-lg text-xs font-bold transition-colors">
                                Tolak Transfer
                            </button>
                            <button type="submit" name="decision" value="verified" onclick="return confirm('Apakah Anda yakin ingin MENGESAHKAN transfer ini? Stok akan dipotong fisik dan kas masuk akan dicatat.')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition-colors">
                                Verifikasi & Sahkan Pembayaran
                            </button>
                        </div>
                    @endif
                </form>
            @endif

            <!-- Cancel by Cashier / Customer -->
            <div class="pt-2 border-t border-slate-700 flex justify-end">
                <form method="POST" action="{{ route('sales.cancel', $sale) }}" onsubmit="return confirm('Batalkan transaksi pending ini dan rilis seluruh reservasi barang?')">
                    @csrf
                    <input type="hidden" name="reason" value="Dibatalkan oleh kasir / pelanggan sebelum verifikasi">
                    <button type="submit" class="text-xs text-slate-400 hover:text-rose-400 underline">
                        Batalkan Transaksi Ini
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if($sale->isCancelled())
        <div class="bg-rose-950/40 border border-rose-800/80 rounded-xl p-4 flex items-start gap-3">
            <svg class="h-5 w-5 text-rose-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-xs text-rose-200">
                <strong class="text-white">Transaksi Dibatalkan / Ditolak:</strong>
                <p class="mt-0.5">{{ $sale->cancellation_reason ?: 'Tidak ada alasan khusus dicatat.' }}</p>
                @if($sale->payment?->verification_note && $sale->payment->verification_note !== $sale->cancellation_reason)
                    <p class="mt-0.5 text-slate-400">Catatan pengawas: "{{ $sale->payment->verification_note }}"</p>
                @endif
                @if($sale->payment_status === 'expired')
                    <p class="mt-1 font-semibold text-rose-300">Stok yang sempat ditahan telah dirilis kembali secara otomatis.</p>
                @endif
            </div>
        </div>
    @endif

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

@push('scripts')
<script>
function countdownTimer(targetTimestamp) {
    return {
        target: targetTimestamp * 1000,
        displayText: '',
        isExpired: false,
        timer: null,

        init() {
            this.update();
            this.timer = setInterval(() => this.update(), 1000);
        },

        update() {
            const now = new Date().getTime();
            const diff = this.target - now;

            if (diff <= 0) {
                this.displayText = 'Kedaluwarsa';
                this.isExpired = true;
                if (this.timer) clearInterval(this.timer);
                return;
            }

            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            this.displayText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    };
}
</script>
@endpush
@endsection
