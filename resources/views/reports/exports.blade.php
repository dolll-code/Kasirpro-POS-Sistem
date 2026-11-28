@extends('layouts.app')

@section('title', 'Riwayat Ekspor')

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('dashboard') }}" class="text-sm text-sky-400">&larr; Dashboard</a>
        <h1 class="mt-2 text-2xl font-bold text-white">Riwayat Ekspor</h1>
        <p class="text-sm text-slate-400">File disimpan privat dan kedaluwarsa otomatis.</p>
    </div>
    @if($monitoring)
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-7 text-center text-sm">
            @foreach(['requested' => 'Requested', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed', 'expired' => 'Expired', 'pending_jobs' => 'Queue Pending', 'failed_jobs' => 'Failed Jobs'] as $key => $label)
                <div class="rounded-xl border border-slate-700 bg-slate-800 p-3"><div class="text-[11px] uppercase text-slate-400">{{ $label }}</div><div class="mt-1 text-xl font-bold text-white">{{ $monitoring[$key] ?? 0 }}</div></div>
            @endforeach
        </div>
    @endif
    <div class="overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
        <table class="min-w-full divide-y divide-slate-700 text-sm">
            <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="px-4 py-3">ID</th><th class="px-4 py-3">Laporan</th><th class="px-4 py-3">Format</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Diminta</th><th class="px-4 py-3">Kedaluwarsa</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-700 text-slate-200">
            @forelse($exports as $export)
                <tr>
                    <td class="px-4 py-3 font-mono">#{{ $export->id }}</td>
                    <td class="px-4 py-3">{{ $export->report_type }}</td>
                    <td class="px-4 py-3">{{ strtoupper($export->format) }}</td>
                    <td class="px-4 py-3">{{ $export->status }}</td>
                    <td class="px-4 py-3">{{ $export->requested_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3">{{ $export->expires_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('reports.export_status', $export) }}" class="text-sky-400">Status</a>@if($export->status === 'completed' && ! $export->isExpired()) <a href="{{ route('reports.export_download', $export) }}" class="ml-2 text-emerald-400">Unduh</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Belum ada riwayat ekspor.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $exports->links() }}
</div>
@endsection
