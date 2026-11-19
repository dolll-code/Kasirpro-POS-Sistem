@extends('layouts.app')

@section('title', 'Verifikasi Transfer Bank Tertunda')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-white">Verifikasi Transfer Bank</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                    {{ $pendingSales->total() }} Menunggu
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-400">Daftar transaksi penjualan metode transfer bank yang memerlukan verifikasi manual dan pengesahan stok persediaan.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <a href="{{ route('sales.index') }}" class="inline-flex items-center px-3.5 py-2 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                Semua Riwayat Penjualan
            </a>
            @if(auth()->user()->hasPermission('sales.create'))
                <a href="{{ route('pos.index') }}" class="inline-flex items-center px-3.5 py-2 border border-transparent rounded-lg shadow-sm text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 transition-colors">
                    Buka POS
                </a>
            @endif
        </div>
    </div>

    <!-- Alert Banner: 15-Minute Reservation Policy -->
    <div class="p-4 rounded-xl bg-slate-800/80 border border-blue-800/50 flex items-start gap-3">
        <svg class="h-5 w-5 text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-xs text-slate-300 leading-relaxed">
            <strong class="text-white">Kebijakan Reservasi Stok (PRD §3.1 DEC-05, §6.4):</strong>
            Setiap transaksi transfer menahan stok barang (status reservasi) selama <strong>15 menit</strong>. Jika bukti mutasi bank sah, klik <em>Verifikasi</em> untuk memotong stok fisik dan mencatat uang masuk. Jika bukti tidak valid atau reservasi kedaluwarsa, stok dilepas kembali ke saldo tersedia toko.
        </div>
    </div>

    <!-- Pending Transfers Table -->
    <div class="bg-slate-800 rounded-xl border border-slate-700/60 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-700 text-xs text-slate-300">
                <thead class="bg-slate-850 text-slate-400 text-[11px] font-semibold uppercase tracking-wider text-left">
                    <tr>
                        <th class="px-4 py-3">No. Transaksi</th>
                        <th class="px-4 py-3">Waktu Checkout</th>
                        <th class="px-4 py-3">Kasir</th>
                        <th class="px-4 py-3">Rekening Bank</th>
                        <th class="px-4 py-3">No. Referensi Bukti</th>
                        <th class="px-4 py-3 text-right">Grand Total</th>
                        <th class="px-4 py-3 text-center">Batas Reservasi (Countdown)</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60">
                    @forelse($pendingSales as $sale)
                        @php
                            $expiresAt = $sale->pending_expires_at ? $sale->pending_expires_at->timestamp : ($sale->created_at ? $sale->created_at->addMinutes(15)->timestamp : now()->timestamp);
                        @endphp
                        <tr class="hover:bg-slate-750 transition-colors">
                            <td class="px-4 py-3 font-mono font-bold text-blue-400">
                                <a href="{{ route('sales.show', $sale) }}" class="hover:underline">
                                    {{ $sale->transaction_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $sale->created_at ? $sale->created_at->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-4 py-3 font-medium text-white">
                                {{ $sale->cashier?->name }}
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ $sale->payment?->cashAccount?->name ?? 'Bank Toko' }}
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-300">
                                {{ $sale->payment?->reference_number ?: '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-400">
                                Rp {{ number_format((float) $sale->grand_total, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div x-data="countdownTimer({{ $expiresAt }})" class="inline-flex items-center gap-1 font-mono text-xs px-2.5 py-1 rounded-full font-bold"
                                     :class="isExpired ? 'bg-rose-950/60 text-rose-400 border border-rose-800/60' : 'bg-blue-950/60 text-blue-300 border border-blue-800/60'">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span x-text="displayText"></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-semibold transition-colors">
                                    Verifikasi / Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                                <svg class="h-10 w-10 text-slate-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm font-medium text-slate-300">Tidak ada transaksi transfer yang menunggu verifikasi saat ini.</p>
                                <p class="text-xs text-slate-500 mt-1">Seluruh pembayaran transfer telah diverifikasi atau kedaluwarsa.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pendingSales->hasPages())
            <div class="px-4 py-3 bg-slate-850 border-t border-slate-700">
                {{ $pendingSales->links() }}
            </div>
        @endif
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
