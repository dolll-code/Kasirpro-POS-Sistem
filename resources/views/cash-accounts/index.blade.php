@extends('layouts.app', [
    'title' => 'Akun Kas & Bank',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Akun Kas & Bank' => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Buku Kas & Rekening Bank</h1>
            <p class="text-xs text-slate-500 mt-1">Daftar buku kas laci toko dan rekening bank operasional untuk pencatatan penerimaan, pengeluaran, dan rekonsiliasi dana.</p>
        </div>
        <div class="shrink-0">
            @can('store_settings.manage')
                <a href="{{ route('cash-accounts.create') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition gap-2 shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Akun Kas/Bank
                </a>
            @endcan
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Total Kas Fisik (Laci)</p>
                <p class="text-2xl font-bold text-slate-900 font-mono mt-1">
                    {{ \App\Support\MoneyCalculator::formatIdr($totalCashBalance ?? '0.00') }}
                </p>
            </div>
            <div class="p-3 bg-emerald-50 rounded-lg text-emerald-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-blue-600 uppercase tracking-wider">Total Rekening Bank</p>
                <p class="text-2xl font-bold text-slate-900 font-mono mt-1">
                    {{ \App\Support\MoneyCalculator::formatIdr($totalBankBalance ?? '0.00') }}
                </p>
            </div>
            <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-600 uppercase tracking-wider">Total Likuiditas Toko</p>
                <p class="text-2xl font-bold text-slate-900 font-mono mt-1">
                    {{ \App\Support\MoneyCalculator::formatIdr($totalLiquidity ?? '0.00') }}
                </p>
            </div>
            <div class="p-3 bg-slate-100 rounded-lg text-slate-700">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4">
        <form method="GET" action="{{ route('cash-accounts.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <x-form.label for="search" value="Cari Kode / Nama" />
                <x-form.input id="search" name="search" value="{{ request('search') }}" placeholder="Ketik kata kunci pencarian..." />
            </div>

            <div>
                <x-form.label for="account_type" value="Tipe Akun" />
                <x-form.select id="account_type" name="account_type">
                    <option value="">Semua Tipe</option>
                    <option value="cash" {{ request('account_type') === 'cash' ? 'selected' : '' }}>Kas Fisik (Laci Kasir)</option>
                    <option value="bank" {{ request('account_type') === 'bank' ? 'selected' : '' }}>Rekening Bank</option>
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

            <div class="flex items-end gap-2">
                <x-button type="submit" variant="primary" size="md" class="w-full justify-center">
                    Filter
                </x-button>
                <a href="{{ route('cash-accounts.index') }}" class="inline-flex items-center justify-center px-3 py-2 rounded-md border border-slate-300 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50">
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
                        <th class="py-3 px-4">Kode Akun</th>
                        <th class="py-3 px-4">Nama Akun</th>
                        <th class="py-3 px-4 text-center">Tipe Akun</th>
                        <th class="py-3 px-4 text-right">Saldo Saat Ini</th>
                        <th class="py-3 px-4 text-right">Saldo Awal</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($cashAccounts as $account)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-900 text-xs">
                                {{ $account->code }}
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                {{ $account->name }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($account->account_type === 'cash')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Kas Fisik
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                        Rekening Bank
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                {{ \App\Support\MoneyCalculator::formatIdr($account->current_balance ?? '0.00') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-xs text-slate-500 whitespace-nowrap">
                                {{ \App\Support\MoneyCalculator::formatIdr($account->opening_balance) }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($account->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center space-x-1 whitespace-nowrap">
                                <a href="{{ route('cash-accounts.show', $account) }}" class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-lg text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition shadow-2xs">
                                    <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    Buku Kas
                                </a>

                                @can('store_settings.manage')
                                    <a href="{{ route('cash-accounts.edit', $account) }}" class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-700">
                                        Edit
                                    </a>

                                    <form action="{{ route('cash-accounts.toggle_status', $account) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-xs font-medium {{ $account->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                            {{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>

                                    <form action="{{ route('cash-accounts.destroy', $account) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?')">
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
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                Tidak ada data akun kas/bank yang sesuai kriteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cashAccounts->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $cashAccounts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
