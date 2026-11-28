@extends('layouts.app')

@section('title', 'Status Ekspor')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('reports.export_history') }}" class="text-sm text-sky-400">&larr; Riwayat ekspor</a>
            <h1 class="mt-2 text-2xl font-bold text-white">Status Ekspor #{{ $export->id }}</h1>
            <p class="text-sm text-slate-400">{{ ucfirst($export->report_type) }} &middot; {{ strtoupper($export->format) }}</p>
        </div>
        <span class="rounded px-3 py-1 text-sm {{ $export->status === 'completed' ? 'bg-emerald-900 text-emerald-200' : ($export->status === 'failed' ? 'bg-rose-900 text-rose-200' : 'bg-amber-900 text-amber-200') }}">{{ $export->status }}</span>
    </div>
    @if(session('success'))<div class="rounded-lg border border-emerald-700 bg-emerald-950/40 p-4 text-sm text-emerald-200">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-lg border border-rose-700 bg-rose-950/40 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>@endif

    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5 text-sm text-slate-300">
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt class="text-xs text-slate-400">Diminta</dt><dd>{{ $export->requested_at?->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-xs text-slate-400">Selesai</dt><dd>{{ $export->completed_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
            <div><dt class="text-xs text-slate-400">Kedaluwarsa</dt><dd>{{ $export->expires_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
            <div><dt class="text-xs text-slate-400">Percobaan</dt><dd>{{ $export->attempts }}</dd></div>
        </dl>
        @if($export->error_message)<div class="mt-4 rounded bg-rose-950/40 p-3 text-rose-200">{{ $export->error_code }}: {{ $export->error_message }}</div>@endif
    </div>

    @if($export->status === 'completed' && ! $export->isExpired())
        <div class="flex gap-3">
            <a href="{{ route('reports.export_download', $export) }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white">Unduh {{ strtoupper($export->format) }}</a>
            <a href="{{ route('reports.export_history') }}" class="rounded-lg border border-slate-600 px-4 py-2 text-slate-200">Riwayat</a>
        </div>
    @elseif(in_array($export->status, ['requested', 'processing']))
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <p class="mb-3 text-sm text-slate-300">Ekspor sedang diproses. Halaman ini dapat dimuat ulang.</p>
            <a href="{{ route('reports.export_status', $export) }}" class="rounded-lg bg-sky-600 px-4 py-2 font-semibold text-white">Periksa Status</a>
        </div>
        <script>setTimeout(() => window.location.reload(), 5000);</script>
    @elseif($export->status === 'failed')
        <form method="POST" action="{{ route('reports.export', $export->report_type) }}">
            @csrf
            <input type="hidden" name="format" value="{{ $export->format }}">
            <input type="hidden" name="from" value="{{ $export->filters['from'] ?? '' }}">
            <input type="hidden" name="to" value="{{ $export->filters['to'] ?? '' }}">
            <button class="rounded-lg bg-sky-600 px-4 py-2 font-semibold text-white">Coba Lagi</button>
        </form>
    @endif
</div>
@endsection
