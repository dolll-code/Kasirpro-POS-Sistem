@extends('layouts.app', [
    'title' => 'Kategori Biaya Operasional',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Kategori Biaya' => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Kategori Biaya Operasional</h1>
            <p class="text-xs text-slate-500 mt-1">Klasifikasi beban toko (listrik, air, perlengkapan kasir, pemeliharaan, dsb) untuk laporan laba rugi.</p>
        </div>
        <div class="shrink-0">
            <a href="{{ route('expense-categories.create') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Kategori Biaya
            </a>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4">
        <form method="GET" action="{{ route('expense-categories.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <x-form.label for="search" value="Cari Nama Kategori" />
                <x-form.input id="search" name="search" value="{{ request('search') }}" placeholder="Ketik nama kategori pengeluaran..." />
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
                <a href="{{ route('expense-categories.index') }}" class="inline-flex items-center justify-center px-3 py-2 rounded-md border border-slate-300 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50">
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
                        <th class="py-3 px-4">Nama Kategori Biaya</th>
                        <th class="py-3 px-4">Deskripsi / Peruntukan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($expenseCategories as $category)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                {{ $category->name }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 max-w-sm truncate">
                                {{ $category->description ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($category->is_active)
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
                                <a href="{{ route('expense-categories.edit', $category) }}" class="text-xs font-medium text-slate-700 hover:text-slate-900">
                                    Edit
                                </a>

                                <form action="{{ route('expense-categories.toggle_status', $category) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs font-medium {{ $category->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                        {{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>

                                <form action="{{ route('expense-categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori biaya ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-700">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-500">
                                Tidak ada data kategori biaya yang sesuai kriteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($expenseCategories->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $expenseCategories->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
