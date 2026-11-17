@extends('layouts.app', [
    'title' => 'Detail Pengguna — ' . $user->name,
    'breadcrumbs' => [
        'Pengguna' => route('users.index'),
        $user->name => null
    ]
])

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="h-14 w-14 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xl uppercase shadow-md shadow-indigo-600/20">
                {{ substr($user->name, 0, 2) }}
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $user->name }}</h1>
                <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $user->email }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center px-3.5 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition shadow-sm gap-1.5">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Ubah Profil
            </a>

            @if(auth()->id() !== $user->id)
                <form method="POST" action="{{ route('users.toggle_status', $user) }}" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif akun ini?');">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit" variant="{{ $user->is_active ? 'danger' : 'success' }}" size="sm">
                        {{ $user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                    </x-button>
                </form>
            @endif
        </div>
    </div>

    <!-- Metadata Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Peran (Role)</span>
            <div class="mt-1 flex items-center gap-2">
                <span class="text-base font-bold text-slate-900">{{ $user->role?->name }}</span>
                <span class="text-xs font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
                    {{ $user->role?->code }}
                </span>
            </div>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Status Akses</span>
            <div class="mt-1">
                @if($user->is_active)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Akun Aktif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        Akun Nonaktif
                    </span>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Login Terakhir</span>
            <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ $user->last_login_at ? $user->last_login_at->translatedFormat('d F Y, H:i') : 'Belum pernah login' }}
            </div>
        </div>
    </div>

    <!-- Granted Permissions Matrix -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Hak Akses Efektif ({{ $user->role?->permissions->count() ?? 0 }} Izin)</h2>
            <p class="text-xs text-slate-500 mt-0.5">Diturunkan secara otomatis berdasarkan peran <strong class="text-slate-700">{{ $user->role?->name }}</strong>.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 pt-2">
            @forelse($user->role?->permissions ?? [] as $perm)
                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                    <div class="font-semibold text-slate-800">{{ $perm->name }}</div>
                    <div class="text-[11px] font-mono text-indigo-600 mt-0.5">{{ $perm->code }}</div>
                </div>
            @empty
                <div class="col-span-3 text-xs text-slate-400 py-3">Tidak ada hak akses khusus yang ditetapkan.</div>
            @endforelse
        </div>
    </div>

    <!-- Recent Audit Logs for User -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50">
            <h2 class="text-base font-semibold text-slate-900">Jejak Aktivitas Audit Terkini</h2>
            <p class="text-xs text-slate-500 mt-0.5">10 tindakan audit terakhir yang melibatkan atau dilakukan oleh pengguna ini.</p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($recentAudits as $log)
                <div class="px-6 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 hover:bg-slate-50/80 transition text-xs">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-semibold text-indigo-600 uppercase">{{ $log->action }}</span>
                            <span class="text-slate-400">•</span>
                            <span class="text-slate-600">Modul: {{ $log->module }}</span>
                        </div>
                        @if($log->new_values)
                            <div class="text-[11px] font-mono text-slate-500 mt-1 truncate max-w-xl">
                                Payload: {{ json_encode($log->new_values) }}
                            </div>
                        @endif
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-slate-600 font-medium">{{ $log->created_at->translatedFormat('d M Y, H:i:s') }}</div>
                        <div class="text-[11px] font-mono text-slate-400">IP: {{ $log->ip_address ?? '127.0.0.1' }}</div>
                    </div>
                </div>
            @empty
                <div class="py-6 text-center text-xs text-slate-400">
                    Belum ada catatan aktivitas audit yang terekam untuk pengguna ini.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
