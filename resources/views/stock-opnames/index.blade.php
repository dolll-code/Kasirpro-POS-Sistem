@extends('layouts.app')

@section('title', 'Stok Opname')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Stok Opname</h1>
            <p class="text-sm text-slate-400">Sesi hitung fisik, variance, approval, dan posting adjustment.</p>
        </div>
        @if(auth()->user()->hasPermission('stock_opname.create'))
            <a href="{{ route('stock-opnames.create') }}" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Mulai Sesi Opname</a>
        @endif
    </div>

    <form class="flex flex-wrap gap-3 rounded-xl border border-slate-700 bg-slate-800 p-4">
        <select name="status" class="rounded-lg border-slate-600 bg-slate-900 text-white">
            <option value="">Semua status</option>
            @foreach(['draft', 'counting', 'pending_approval', 'rejected', 'approved', 'posted', 'cancelled'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-slate-700 px-4 py-2 text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
        <table class="min-w-full divide-y divide-slate-700 text-sm">
            <thead class="text-left text-xs uppercase text-slate-400">
                <tr>
                    <th class="px-4 py-3">Nomor</th>
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Dibuat</th>
                    <th class="px-4 py-3">Approver / Poster</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700 text-slate-200">
                @forelse($opnames as $opname)
                    <tr>
                        <td class="px-4 py-3 font-mono">{{ $opname->opname_number }}</td>
                        <td class="px-4 py-3">{{ $opname->items_count }} produk</td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', $opname->status) }}</td>
                        <td class="px-4 py-3">{{ $opname->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $opname->approver?->name ?? '-' }} / {{ $opname->poster?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('stock-opnames.show', $opname) }}" class="text-sky-400 hover:text-sky-300">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Belum ada sesi stok opname.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $opnames->links() }}
</div>
@endsection
