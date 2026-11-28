@extends('layouts.app')

@section('title', 'Adjustment Kas')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold text-white">Adjustment Kas Non-operasional</h1><p class="text-sm text-slate-400">Koreksi kas dengan maker-checker; tidak dihitung sebagai omzet atau expense.</p></div>
        @if(auth()->user()->hasPermission('expenses.create'))<a href="{{ route('cash-adjustments.create') }}" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">Ajukan Adjustment</a>@endif
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
        <table class="min-w-full divide-y divide-slate-700 text-sm">
            <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="px-4 py-3">Nomor</th><th class="px-4 py-3">Akun</th><th class="px-4 py-3">Arah</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3">Jumlah</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-700 text-slate-200">
            @forelse($adjustments as $adjustment)
                <tr><td class="px-4 py-3 font-mono">{{ $adjustment->adjustment_number }}</td><td class="px-4 py-3">{{ $adjustment->cashAccount?->name }}</td><td class="px-4 py-3">{{ $adjustment->direction === 'in' ? 'Masuk' : 'Keluar' }}</td><td class="px-4 py-3">{{ $adjustment->category }}</td><td class="px-4 py-3">Rp {{ number_format((float) $adjustment->amount, 2, ',', '.') }}</td><td class="px-4 py-3">{{ $adjustment->status }}</td><td class="px-4 py-3 text-right"><a class="text-sky-400" href="{{ route('cash-adjustments.show', $adjustment) }}">Detail</a></td></tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Belum ada adjustment.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $adjustments->links() }}
</div>
@endsection
