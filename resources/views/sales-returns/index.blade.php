@extends('layouts.app')

@section('title', 'Retur Penjualan')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold text-white">Retur Penjualan</h1><p class="text-sm text-slate-400">Pengajuan, keputusan, dan pembayaran refund.</p></div>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
        <table class="min-w-full divide-y divide-slate-700 text-sm">
            <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="px-4 py-3">Nomor Retur</th><th class="px-4 py-3">Penjualan</th><th class="px-4 py-3">Pemohon</th><th class="px-4 py-3">Nilai</th><th class="px-4 py-3">Status / Refund</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-700 text-slate-200">
            @forelse($returns as $return)
                <tr><td class="px-4 py-3 font-mono">{{ $return->return_number }}</td><td class="px-4 py-3">{{ $return->sale?->transaction_number }}</td><td class="px-4 py-3">{{ $return->requester?->name }}</td><td class="px-4 py-3">Rp {{ number_format((float) $return->return_total, 2, ',', '.') }}</td><td class="px-4 py-3"><span class="rounded bg-slate-700 px-2 py-1">{{ str_replace('_', ' ', $return->status) }}</span><span class="ml-1 text-xs text-slate-400">refund {{ $return->refund_status }}</span></td><td class="px-4 py-3 text-right"><a class="text-sky-400 hover:text-sky-300" href="{{ route('sales-returns.show', $return) }}">Detail</a></td></tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Belum ada pengajuan retur.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $returns->links() }}
</div>
@endsection
