@extends('layouts.app')

@section('title', 'Ajukan Adjustment Kas')

@section('content')
<div class="mx-auto max-w-2xl space-y-5">
    <div><a href="{{ route('cash-adjustments.index') }}" class="text-sm text-sky-400">&larr; Daftar adjustment</a><h1 class="mt-2 text-2xl font-bold text-white">Ajukan Adjustment Kas</h1><p class="text-sm text-slate-400">Adjustment menunggu approval pengguna lain. Tidak masuk omzet maupun expense.</p></div>
    @if($errors->any())<div class="rounded-lg border border-rose-700 bg-rose-950/40 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('cash-adjustments.store') }}" class="space-y-4 rounded-xl border border-slate-700 bg-slate-800 p-6">
        @csrf
        <label class="block text-sm text-slate-300">Akun kas/bank<select name="cash_account_id" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"><option value="">Pilih akun</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }} &middot; Rp {{ number_format((float) $account->current_balance, 2, ',', '.') }}</option>@endforeach</select></label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm text-slate-300">Arah<select name="direction" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"><option value="in">Kas masuk</option><option value="out">Kas keluar</option></select></label>
            <label class="block text-sm text-slate-300">Kategori<select name="category" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white">@foreach($categories as $category)<option value="{{ $category }}">{{ str_replace('_', ' ', $category) }}</option>@endforeach</select></label>
        </div>
        <label class="block text-sm text-slate-300">Nominal (Rp)<input type="number" name="amount" min="0.01" step="0.01" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"></label>
        <label class="block text-sm text-slate-300">Alasan wajib<textarea name="reason" rows="3" required maxlength="2000" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"></textarea></label>
        <div class="flex justify-end"><button class="rounded-lg bg-sky-600 px-5 py-2 font-semibold text-white">Kirim Pengajuan</button></div>
    </form>
</div>
@endsection
