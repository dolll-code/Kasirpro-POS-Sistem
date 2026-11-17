@extends('layouts.app', [
    'title' => 'Dashboard Master Data',
    'breadcrumbs' => [
        'Master Data' => null
    ]
])

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Pusat Data Master Toko</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola katalog produk, kategori, satuan kuantitas, data pemasok, kategori pengeluaran, akun kas, dan profil toko.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('products.create')
                <a href="{{ route('products.create') }}" class="inline-flex items-center px-3.5 py-2 rounded-md bg-slate-800 text-white text-xs font-semibold hover:bg-slate-700 transition gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Produk
                </a>
            @endcan
            @can('store_settings.manage')
                <a href="{{ route('store-profile.edit') }}" class="inline-flex items-center px-3.5 py-2 rounded-md border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50 transition gap-1.5">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Profil Toko
                </a>
            @endcan
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Products Stat -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Katalog Produk</span>
                <span class="p-2 rounded-lg bg-blue-50 text-blue-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900">{{ number_format($stats['products_count']) }}</span>
                <span class="text-xs text-emerald-600 font-medium">({{ number_format($stats['products_active']) }} aktif)</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                <a href="{{ route('products.index') }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 flex items-center gap-1">
                    Lihat katalog produk &rarr;
                </a>
            </div>
        </div>

        <!-- Categories Stat -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kategori Produk</span>
                <span class="p-2 rounded-lg bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900">{{ number_format($stats['categories_count']) }}</span>
                <span class="text-xs text-slate-500">kelompok produk</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                <a href="{{ route('categories.index') }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 flex items-center gap-1">
                    Kelola kategori &rarr;
                </a>
            </div>
        </div>

        <!-- Units Stat -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Satuan Presisi</span>
                <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900">{{ number_format($stats['units_count']) }}</span>
                <span class="text-xs text-slate-500">satuan (presisi 0–3)</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                <a href="{{ route('units.index') }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 flex items-center gap-1">
                    Kelola satuan &rarr;
                </a>
            </div>
        </div>

        <!-- Suppliers Stat -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pemasok Terdaftar</span>
                <span class="p-2 rounded-lg bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900">{{ number_format($stats['suppliers_count']) }}</span>
                <span class="text-xs text-slate-500">mitra supplier</span>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                <a href="{{ route('suppliers.index') }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 flex items-center gap-1">
                    Kelola pemasok &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Secondary Grid: Finance & Store Settings -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Expense Categories -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-lg bg-rose-50 text-rose-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Kategori Biaya Operasional</h3>
                        <p class="text-xs text-slate-500">{{ $stats['expense_categories_count'] }} kategori biaya aktif</p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 mt-3">Klasifikasi pengeluaran toko untuk tagihan listrik, air, perlengkapan kasir, dan konsumsi.</p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                <a href="{{ route('expense-categories.index') }}" class="text-xs font-semibold text-slate-700 hover:text-slate-900 flex items-center gap-1">
                    Buka kategori pengeluaran &rarr;
                </a>
            </div>
        </div>

        <!-- Cash Accounts -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-lg bg-cyan-50 text-cyan-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Akun Kas & Bank</h3>
                        <p class="text-xs text-slate-500">{{ $stats['cash_accounts_count'] }} akun terdaftar</p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 mt-3">Akun laci kasir dan rekening bank untuk pencatatan uang masuk, pembayaran supplier, dan pengeluaran.</p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                <a href="{{ route('cash-accounts.index') }}" class="text-xs font-semibold text-slate-700 hover:text-slate-900 flex items-center gap-1">
                    Buka akun kas/bank &rarr;
                </a>
            </div>
        </div>

        <!-- Store Profile Card -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-lg bg-purple-50 text-purple-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Profil & Struk Toko</h3>
                        <p class="text-xs text-slate-500">{{ $stats['store_profile']->name }}</p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 mt-3">Nama toko, alamat, telepon, NPWP, dan lebar kertas printer thermal ({{ $stats['store_profile']->receipt_paper_width }} mm).</p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                <a href="{{ route('store-profile.edit') }}" class="text-xs font-semibold text-slate-700 hover:text-slate-900 flex items-center gap-1">
                    Konfigurasi profil toko &rarr;
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
