@extends('layouts.app', [
    'title' => 'Satuan Presisi Kuantitas',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Satuan' => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Satuan Kuantitas Produk</h1>
            <p class="text-xs text-slate-500 mt-1">Definisi satuan unit barang dan batas presisi desimal pecahan (0 hingga 3 digit).</p>
        </div>
        <div class="shrink-0">
            <a href="{{ route('units.create') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Satuan
            </a>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4">
        <form method="GET" action="{{ route('units.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <x-form.label for="search" value="Cari Nama / Simbol" />
                <x-form.input id="search" name="search" value="{{ request('search') }}" placeholder="Contoh: Kilogram, kg, pcs..." />
            </div>

            <div>
                <x-form.label for="status" value="Filter Status" />
                <x-form.select id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </x-form.select>
            </div>

            <div class="flex items-end gap-2">
                <x-button type="submit" variant="primary" size="md" class="w-full justify-center">
                    Filter
                </x-button>
                <a href="{{ route('units.index') }}" class="inline-flex items-center justify-center px-3 py-2 rounded-md border border-slate-300 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50">
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
                        <th class="py-3 px-4">Nama Satuan</th>
                        <th class="py-3 px-4">Simbol</th>
                        <th class="py-3 px-4 text-center">Presisi Desimal</th>
                        <th class="py-3 px-4 text-center">Jumlah Produk</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($units as $unit)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                {{ $unit->name }}
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono font-bold text-slate-700">
                                {{ $unit->symbol }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium {{ $unit->decimal_precision === 0 ? 'bg-slate-100 text-slate-700' : 'bg-blue-50 text-blue-700' }}">
                                    {{ $unit->decimal_precision }} digit ({{ $unit->decimal_precision === 0 ? 'Bilangan bulat' : ($unit->decimal_precision === 3 ? 'Gram/Kg presisi tinggi' : 'Pecahan') }})
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                    {{ $unit->products_count }} item
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($unit->is_active)
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
                                <a href="{{ route('units.edit', $unit) }}" class="inline-flex items-center text-xs font-medium text-slate-700 hover:text-slate-900">
                                    Edit
                                </a>

                                <form action="{{ route('units.toggle_status', $unit) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs font-medium {{ $unit->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                        {{ $unit->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>

                                <form action="{{ route('units.destroy', $unit) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus satuan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-700" {{ $unit->products_count > 0 ? 'title=Satuan terpakai tidak dapat dihapus' : '' }}>
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">
                                Tidak ada data satuan yang sesuai kriteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($units->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $units->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
