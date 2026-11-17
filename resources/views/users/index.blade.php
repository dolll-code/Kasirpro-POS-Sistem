@extends('layouts.app', [
    'title' => 'Manajemen Pengguna',
    'breadcrumbs' => [
        'Pengguna' => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Daftar Pengguna Sistem</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola akun staf toko, penugasan peran (Admin, Supervisor, Kasir), dan status aktif.</p>
        </div>
        <div class="shrink-0">
            <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition shadow-sm gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Pengguna Baru
            </a>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4">
        <form method="GET" action="{{ route('users.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <x-form.label for="search" value="Cari Nama / Email" />
                <x-form.input id="search" name="search" value="{{ request('search') }}" placeholder="Ketik kata kunci pencarian..." />
            </div>

            <div>
                <x-form.label for="role" value="Filter Peran" />
                <x-form.select id="role" name="role">
                    <option value="">Semua Peran</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->code }}" {{ request('role') === $role->code ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </x-form.select>
            </div>

            <div>
                <x-form.label for="status" value="Filter Status" />
                <x-form.select id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </x-form.select>
            </div>

            <div class="sm:col-span-4 flex justify-end gap-2 pt-2 border-t border-slate-100">
                <a href="{{ route('users.index') }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50">
                    Reset Filter
                </a>
                <x-button type="submit" variant="primary" size="sm">
                    Terapkan Filter
                </x-button>
            </div>
        </form>
    </div>

    <!-- Users Table Card -->
    <x-table.card title="Daftar Staf ({{ $users->total() }})">
        <x-table>
            <thead>
                <tr>
                    <x-table.th>Nama & Email</x-table.th>
                    <x-table.th>Peran (Role)</x-table.th>
                    <x-table.th align="center">Status</x-table.th>
                    <x-table.th>Login Terakhir</x-table.th>
                    <x-table.th align="right">Tindakan</x-table.th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($users as $user)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <x-table.td>
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs uppercase border border-slate-200">
                                    {{ substr($user->name, 0, 2) }}
                                </div>
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">{{ $user->email }}</div>
                                </div>
                            </div>
                        </x-table.td>

                        <x-table.td>
                            @php
                                $roleBadge = [
                                    'admin' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                    'supervisor' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'cashier' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                ][$user->role?->code] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $roleBadge }}">
                                {{ $user->role?->name ?? 'Tanpa Peran' }}
                            </span>
                        </x-table.td>

                        <x-table.td align="center">
                            @if($user->is_active)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    Nonaktif
                                </span>
                            @endif
                        </x-table.td>

                        <x-table.td class="text-xs text-slate-500">
                            {{ $user->last_login_at ? $user->last_login_at->translatedFormat('d M Y, H:i') : 'Belum pernah login' }}
                        </x-table.td>

                        <x-table.td align="right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('users.show', $user) }}" class="text-xs font-medium text-slate-600 hover:text-indigo-600 p-1.5 rounded-lg hover:bg-slate-100" title="Detail Pengguna">
                                    Detail
                                </a>
                                <a href="{{ route('users.edit', $user) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 p-1.5 rounded-lg hover:bg-indigo-50" title="Ubah Pengguna">
                                    Ubah
                                </a>

                                @if(auth()->id() !== $user->id)
                                    <form method="POST" action="{{ route('users.toggle_status', $user) }}" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin {{ $user->is_active ? 'menonaktifkan' : 'mengaktifkan' }} akun ini?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-xs font-medium {{ $user->is_active ? 'text-rose-600 hover:text-rose-800 hover:bg-rose-50' : 'text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50' }} p-1.5 rounded-lg">
                                            {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </x-table.td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8">
                            <x-empty-state title="Tidak ada data pengguna" description="Tidak ditemukan pengguna yang sesuai dengan kriteria filter Anda." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>

        @if($users->hasPages())
            <x-slot:footer>
                {{ $users->links() }}
            </x-slot:footer>
        @endif
    </x-table.card>
</div>
@endsection
