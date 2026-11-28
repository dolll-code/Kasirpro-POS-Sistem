@extends('layouts.app')

@section('title', 'Adjustment ' . $cashAdjustment->adjustment_number)

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div class="flex items-center justify-between"><div><a href="{{ route('cash-adjustments.index') }}" class="text-sm text-sky-400">&larr; Daftar adjustment</a><h1 class="mt-2 font-mono text-2xl font-bold text-white">{{ $cashAdjustment->adjustment_number }}</h1></div><span class="rounded bg-slate-700 px-3 py-1 text-sm text-white">{{ $cashAdjustment->status }}</span></div>
    @if($errors->any())<div class="rounded-lg border border-rose-700 bg-rose-950/40 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="rounded-lg border border-emerald-700 bg-emerald-950/40 p-4 text-sm text-emerald-200">{{ session('success') }}</div>@endif
    <section class="grid gap-4 rounded-xl border border-slate-700 bg-slate-800 p-5 text-sm sm:grid-cols-2">
        <div><div class="text-xs text-slate-400">Akun</div><div class="text-white">{{ $cashAdjustment->cashAccount?->name }}</div></div>
        <div><div class="text-xs text-slate-400">Arah / Kategori</div><div class="text-white">{{ $cashAdjustment->direction === 'in' ? 'Masuk' : 'Keluar' }} / {{ $cashAdjustment->category }}</div></div>
        <div><div class="text-xs text-slate-400">Nominal</div><div class="font-semibold text-white">Rp {{ number_format((float) $cashAdjustment->amount, 2, ',', '.') }}</div></div>
        <div><div class="text-xs text-slate-400">Pembuat</div><div class="text-white">{{ $cashAdjustment->requester?->name }}</div></div>
        <div class="sm:col-span-2"><div class="text-xs text-slate-400">Alasan</div><div class="text-white">{{ $cashAdjustment->reason }}</div></div>
        @if($cashAdjustment->rejection_reason)<div class="sm:col-span-2 text-rose-300">Catatan: {{ $cashAdjustment->rejection_reason }}</div>@endif
        @if($cashAdjustment->cash_movement_id)<div class="sm:col-span-2 text-emerald-300">Cash movement #{{ $cashAdjustment->cash_movement_id }} tercatat saat approval.</div>@endif
    </section>
    @if($cashAdjustment->status === 'pending_approval' && auth()->user()->hasPermission('expenses.post'))
        <section class="rounded-xl border border-amber-800 bg-amber-950/20 p-5">
            <h2 class="mb-3 font-semibold text-white">Approval</h2>
            <form method="POST" action="{{ route('cash-adjustments.decide', $cashAdjustment) }}" class="space-y-3">@csrf
                <label class="block text-sm text-slate-300">Catatan/ alasan penolakan<textarea name="approval_note" rows="2" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"></textarea></label>
                @if(auth()->id() === $cashAdjustment->requested_by && auth()->user()->hasRole('admin'))
                    <label class="block text-sm text-amber-200">Alasan Admin override wajib<input name="admin_override_reason" required class="mt-1 w-full rounded-lg border-amber-700 bg-slate-900 text-white"></label>
                @endif
                <div class="flex gap-3"><button name="decision" value="approved" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white">Setujui & Posting</button><button name="decision" value="rejected" class="rounded-lg bg-rose-700 px-4 py-2 font-semibold text-white">Tolak</button></div>
            </form>
        </section>
    @endif
    @if($cashAdjustment->status === 'pending_approval' && auth()->user()->hasPermission('expenses.create'))
        <form method="POST" action="{{ route('cash-adjustments.cancel', $cashAdjustment) }}" class="flex gap-3 rounded-xl border border-slate-700 bg-slate-800 p-5">@csrf<input name="reason" required placeholder="Alasan pembatalan" class="flex-1 rounded-lg border-slate-600 bg-slate-900 text-white"><button class="rounded-lg bg-rose-700 px-4 py-2 font-semibold text-white">Batalkan</button></form>
    @endif
</div>
@endsection
