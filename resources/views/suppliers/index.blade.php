@extends('layouts.app', [
    'title' => 'Pemasok / Supplier',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Pemasok' => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Data Pemasok (Supplier)</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola kontak distributor dan mitra pemasok untuk modul pengadaan barang (PO).</p>
        </div>
        <div class="shrink-0">
            @can('suppliers.manage')
                <a href="{{ route('suppliers.create') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Pemasok Baru
                </a>
            @endcan
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4">
        <form method="GET" action="{{ route('suppliers.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <x-form.label for="search" value="Cari Kode / Nama / Telepon" />
                <x-form.input id="search" name="search" value="{{ request('search') }}" placeholder="Ketik kata kunci pencarian..." />
            </div>

            <div>
                <x-form.label for="status" value="Filter Status" />
                <x-form.select id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif (Dapat Menerima PO)</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </x-form.select>
            </div>

            <div class="flex items-end gap-2">
                <x-button type="submit" variant="primary" size="md" class="w-full justify-center">
                    Filter
                </x-button>
                <a href="{{ route('suppliers.index') }}" class="inline-flex items-center justify-center px-3 py-2 rounded-md border border-slate-300 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/75 text-slate-600 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">Kode Pemasok</th>
                        <th class="py-3 px-4">Nama Perusahaan / Pemasok</th>
                        <th class="py-3 px-4">Kontak Person</th>
                        <th class="py-3 px-4">Telepon / Email</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($suppliers as $supplier)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-900 text-xs">
                                {{ $supplier->supplier_code }}
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="hover:text-blue-600">
                                    {{ $supplier->name }}
                                </a>
                                @if($supplier->address)
                                    <span class="text-xs text-slate-500 block truncate max-w-xs mt-0.5">{{ $supplier->address }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-700">
                                {{ $supplier->contact_person ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 whitespace-nowrap">
                                <div>{{ $supplier->phone ?? '-' }}</div>
                                @if($supplier->email)
                                    <div class="text-[11px] text-slate-400">{{ $supplier->email }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($supplier->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="text-xs font-medium text-slate-700 hover:text-slate-900">
                                    Detail
                                </a>

                                @can('suppliers.manage')
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">
                                        Edit
                                    </a>

                                    <form action="{{ route('suppliers.toggle_status', $supplier) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-xs font-medium {{ $supplier->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                            {{ $supplier->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>

                                    <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pemasok ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-700">
                                            Hapus
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">
                                Tidak ada data pemasok yang sesuai kriteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
