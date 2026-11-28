@extends('layouts.app')

@section('title', 'Opname ' . $stockOpname->opname_number)

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('stock-opnames.index') }}" class="text-sm text-sky-400">&larr; Riwayat opname</a>
            <h1 class="mt-2 font-mono text-2xl font-bold text-white">{{ $stockOpname->opname_number }}</h1>
            <p class="text-sm text-slate-400">Dibuat {{ $stockOpname->created_at?->format('d/m/Y H:i') }} oleh {{ $stockOpname->creator?->name }}</p>
        </div>
        <span class="rounded bg-slate-700 px-3 py-1 text-sm text-white">{{ str_replace('_', ' ', $stockOpname->status) }}</span>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-rose-700 bg-rose-950/40 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif
    @if(session('success'))
        <div class="rounded-lg border border-emerald-700 bg-emerald-950/40 p-4 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif

    <section class="grid gap-4 rounded-xl border border-slate-700 bg-slate-800 p-5 text-sm sm:grid-cols-4">
        <div><div class="text-xs text-slate-400">Mulai</div><div class="text-white">{{ $stockOpname->started_at?->format('d/m/Y H:i') ?? '-' }}</div></div>
        <div><div class="text-xs text-slate-400">Submit</div><div class="text-white">{{ $stockOpname->submitted_at?->format('d/m/Y H:i') ?? '-' }}</div></div>
        <div><div class="text-xs text-slate-400">Approver</div><div class="text-white">{{ $stockOpname->approver?->name ?? '-' }} @if($stockOpname->admin_override_reason)<span class="block text-xs text-amber-300">Override: {{ $stockOpname->admin_override_reason }}</span>@endif</div></div>
        <div><div class="text-xs text-slate-400">Diposting</div><div class="text-white">{{ $stockOpname->posted_at?->format('d/m/Y H:i') ?? '-' }}</div></div>
    </section>

    @if($stockOpname->rejection_reason)
        <div class="rounded-lg border border-rose-700 bg-rose-950/40 p-4 text-sm text-rose-200">
            <strong>Catatan:</strong> {{ $stockOpname->rejection_reason }}
        </div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 px-5 py-4">
            <h2 class="font-semibold text-white">Daftar Produk & Variance</h2>
            <span class="text-xs text-slate-400">{{ $stockOpname->items->whereNotNull('counted_quantity')->count() }}/{{ $stockOpname->items->count() }} item dihitung</span>
        </div>

        @if($stockOpname->status === 'counting' || $stockOpname->status === 'rejected')
            <form method="POST" action="{{ route('stock-opnames.count', $stockOpname) }}" class="space-y-0">
                @csrf
                @method('PUT')
                @if($stockOpname->status === 'rejected')
                    <div class="border-b border-slate-700 bg-amber-950/20 px-5 py-4">
                        <label class="block text-sm text-amber-200">Alasan buka kembali sesi (re-open)
                            <input name="reopen_reason" required maxlength="2000" class="mt-1 w-full rounded-lg border-amber-700 bg-slate-900 text-white">
                        </label>
                    </div>
                @endif
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-700 text-sm">
                        <thead class="text-left text-xs uppercase text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Produk</th>
                                <th class="px-4 py-3">Expected</th>
                                <th class="px-4 py-3">Counted</th>
                                <th class="px-4 py-3">HPP Snapshot</th>
                                <th class="px-4 py-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700 text-slate-200">
                            @foreach($stockOpname->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-white">{{ $item->product?->name }}</div>
                                        <div class="text-xs text-slate-500 font-mono">{{ $item->product?->sku }} &middot; {{ $item->product?->unit?->symbol }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $item->expected_quantity }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.001" min="0" name="items[{{ $item->id }}][counted_quantity]" value="{{ old('items.'.$item->id.'.counted_quantity', $item->counted_quantity) }}" class="w-28 rounded border-slate-600 bg-slate-900 text-right font-mono text-white">
                                    </td>
                                    <td class="px-4 py-3">Rp {{ number_format((float) $item->average_cost_snapshot, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        <input name="items[{{ $item->id }}][notes]" value="{{ old('items.'.$item->id.'.notes', $item->notes) }}" maxlength="2000" class="w-full rounded border-slate-600 bg-slate-900 text-white">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end border-t border-slate-700 px-5 py-4">
                    <button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white hover:bg-sky-500">Simpan Hasil Hitung</button>
                </div>
            </form>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-700 text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3">Expected</th>
                            <th class="px-4 py-3">Counted</th>
                            <th class="px-4 py-3">Variance</th>
                            <th class="px-4 py-3">Cost Basis</th>
                            <th class="px-4 py-3">Variance Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700 text-slate-200">
                        @foreach($stockOpname->items as $item)
                            <tr>
                                <td class="px-4 py-3"><div class="text-white">{{ $item->product?->name }}</div><div class="text-xs text-slate-500 font-mono">{{ $item->product?->sku }}</div></td>
                                <td class="px-4 py-3">{{ $item->expected_quantity }}</td>
                                <td class="px-4 py-3">{{ $item->counted_quantity ?? '-' }}</td>
                                <td class="px-4 py-3 {{ (float) ($item->variance_quantity ?? 0) < 0 ? 'text-rose-300' : ((float) ($item->variance_quantity ?? 0) > 0 ? 'text-emerald-300' : 'text-slate-300') }}">{{ $item->variance_quantity ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @php($costBasis = (float) ($item->active_cost_snapshot ?? 0) > 0 ? $item->active_cost_snapshot : $item->positive_unit_cost)
                                    Rp {{ number_format((float) ($costBasis ?? 0), 2, ',', '.') }}
                                    @if($item->positive_cost_reason)<div class="text-xs text-amber-300">{{ $item->positive_cost_reason }}</div>@endif
                                </td>
                                <td class="px-4 py-3">Rp {{ number_format((float) ($item->variance_value ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if($stockOpname->status === 'counting' && auth()->user()->hasPermission('stock_opname.count'))
        <section class="flex justify-end">
            <form method="POST" action="{{ route('stock-opnames.submit', $stockOpname) }}">
                @csrf
                <button class="rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white hover:bg-indigo-500">Submit untuk Approval</button>
            </form>
        </section>
    @endif

    @if($stockOpname->status === 'pending_approval' && auth()->user()->hasPermission('stock_opname.approve'))
        <section class="rounded-xl border border-amber-800 bg-amber-950/20 p-5">
            <h2 class="mb-3 font-semibold text-white">Approval Sesi</h2>
            <form method="POST" action="{{ route('stock-opnames.decide', $stockOpname) }}" class="space-y-4">
                @csrf
                @php($needsCost = $stockOpname->items->filter(fn ($item) => (float) ($item->variance_quantity ?? 0) > 0 && (float) ($item->active_cost_snapshot ?? 0) <= 0))
                @if($needsCost->isNotEmpty())
                    <div class="rounded-lg border border-amber-700 bg-slate-900/60 p-4">
                        <p class="mb-2 text-sm text-amber-200">Variance positif berikut tidak memiliki cost basis. Isi unit cost dan alasan sebelum approval.</p>
                        @foreach($needsCost as $item)
                            <div class="mb-3 grid gap-2 sm:grid-cols-3">
                                <div class="text-sm text-white">{{ $item->product?->name }}<div class="text-xs text-slate-400">Variance {{ $item->variance_quantity }}</div></div>
                                <input name="items[{{ $item->id }}][positive_unit_cost]" type="number" min="0.01" step="0.01" placeholder="Unit cost" class="rounded border-slate-600 bg-slate-900 text-white">
                                <input name="items[{{ $item->id }}][positive_cost_reason]" placeholder="Alasan cost basis" class="rounded border-slate-600 bg-slate-900 text-white">
                            </div>
                        @endforeach
                    </div>
                @endif
                <label class="block text-sm text-slate-300">Catatan approval / alasan rejection
                    <textarea name="note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"></textarea>
                </label>
                @if(in_array(auth()->id(), $stockOpname->items->pluck('counted_by')->filter()->all()) || auth()->id() === $stockOpname->created_by)
                    @if(auth()->user()->hasRole('admin'))
                        <label class="block text-sm text-amber-200">Alasan Admin override wajib (Anda pembuat/penghitung sesi)
                            <input name="admin_override_reason" required maxlength="2000" class="mt-1 w-full rounded-lg border-amber-700 bg-slate-900 text-white">
                        </label>
                    @endif
                @endif
                <div class="flex gap-3">
                    <button name="decision" value="approved" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white">Setujui</button>
                    <button name="decision" value="rejected" class="rounded-lg bg-rose-700 px-4 py-2 font-semibold text-white">Tolak</button>
                </div>
            </form>
        </section>
    @endif

    @if($stockOpname->status === 'approved' && auth()->user()->hasPermission('stock_opname.post'))
        <section class="rounded-xl border border-emerald-800 bg-emerald-950/20 p-5">
            <h2 class="mb-2 font-semibold text-white">Posting Penyesuaian</h2>
            <p class="mb-3 text-sm text-slate-300">Posting hanya membuat movement untuk variance tidak nol, lalu melepas semua lock. Tindakan ini tidak dapat diulang.</p>
            <form method="POST" action="{{ route('stock-opnames.post', $stockOpname) }}">
                @csrf
                <button class="rounded-lg bg-emerald-600 px-5 py-2 font-semibold text-white hover:bg-emerald-500">Posting Sekarang</button>
            </form>
        </section>
    @endif

    @if(in_array($stockOpname->status, ['counting', 'rejected', 'pending_approval']) && auth()->user()->hasPermission('stock_opname.create'))
        <section class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="mb-2 font-semibold text-white">Batalkan Sesi</h2>
            <p class="mb-3 text-sm text-slate-400">Pembatalan melepas seluruh lock produk tanpa membuat movement stok.</p>
            <form method="POST" action="{{ route('stock-opnames.cancel', $stockOpname) }}" class="flex flex-col gap-3 sm:flex-row">
                @csrf
                <input name="reason" required maxlength="2000" placeholder="Alasan pembatalan" class="flex-1 rounded-lg border-slate-600 bg-slate-900 text-white">
                <button class="rounded-lg bg-rose-700 px-4 py-2 font-semibold text-white">Batalkan & Lepas Lock</button>
            </form>
        </section>
    @endif

    <section class="rounded-xl border border-slate-700 bg-slate-800 p-5 text-sm text-slate-300">
        <h2 class="mb-2 font-semibold text-white">Riwayat & Audit</h2>
        <ul class="space-y-1">
            <li>Sesi dibuat {{ $stockOpname->created_at?->format('d/m/Y H:i') }} oleh {{ $stockOpname->creator?->name }}; mulai {{ $stockOpname->started_at?->format('d/m/Y H:i') ?? '-' }} oleh {{ $stockOpname->starter?->name ?? '-' }}.</li>
            <li>Submit {{ $stockOpname->submitted_at?->format('d/m/Y H:i') ?? '-' }} oleh {{ $stockOpname->submitter?->name ?? '-' }}.</li>
            <li>Approval {{ $stockOpname->approved_at?->format('d/m/Y H:i') ?? '-' }} oleh {{ $stockOpname->approver?->name ?? '-' }}.</li>
            <li>Posting {{ $stockOpname->posted_at?->format('d/m/Y H:i') ?? '-' }} oleh {{ $stockOpname->poster?->name ?? '-' }}.</li>
            @if($stockOpname->reopen_reason)<li>Reopen: {{ $stockOpname->reopen_reason }}</li>@endif
        </ul>
    </section>
</div>
@endsection
