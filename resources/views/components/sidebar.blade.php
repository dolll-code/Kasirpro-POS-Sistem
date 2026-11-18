@php
    $user = auth()->user();
@endphp

<!-- Off-canvas menu for mobile/tablet, show/hide based on off-canvas menu state. -->
<div x-show="sidebarOpen" 
     x-cloak
     class="relative z-50 md:hidden" 
     role="dialog" 
     aria-modal="true">
    <!-- Backdrop -->
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-950/45"></div>

    <div class="fixed inset-0 flex">
        <div x-show="sidebarOpen"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             @click.away="sidebarOpen = false"
             class="relative mr-16 flex w-full max-w-xs flex-1">
            
            <!-- Close button -->
            <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                <button type="button" @click="sidebarOpen = false" class="-m-2.5 p-2.5 text-white">
                    <span class="sr-only">Tutup navigasi</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Sidebar content -->
            <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-[#202b3b] px-6 pb-4">
                <div class="flex h-16 shrink-0 items-center border-b border-white/10 gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-slate-600 text-white font-bold text-lg">K</span>
                    <span class="text-xl font-bold tracking-tight text-white">Kasir<span class="text-slate-300">Pro</span></span>
                </div>
                <nav class="flex flex-1 flex-col">
                    <ul role="list" class="flex flex-1 flex-col gap-y-4">
                        <li>
                            <a href="{{ route('dashboard') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('dashboard') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2.5 text-sm font-medium">
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                Beranda
                            </a>
                        </li>

                        @if($user && $user->hasPermission('users.manage'))
                            <li>
                                <a href="{{ route('users.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('users.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2.5 text-sm font-medium">
                                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    Manajemen Pengguna
                                </a>
                            </li>
                        @endif

                        <li>
                            <a href="{{ route('profile.show') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('profile.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2.5 text-sm font-medium">
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Profil Saya
                            </a>
                        </li>

                        <!-- Master Data Links (Mobile) -->
                        @if($user && ($user->hasPermission('products.view') || $user->hasPermission('catalog.manage') || $user->hasPermission('suppliers.view')))
                            <li class="pt-2 border-t border-white/10">
                                <span class="text-xs font-semibold uppercase text-slate-400">Master Data</span>
                            </li>
                            <li>
                                <a href="{{ route('master.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('master.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                    Ringkasan Master
                                </a>
                            </li>
                            @if($user->hasPermission('products.view'))
                                <li>
                                    <a href="{{ route('products.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('products.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                        Katalog Produk
                                    </a>
                                </li>
                            @endif
                            @if($user->hasPermission('catalog.manage'))
                                <li>
                                    <a href="{{ route('categories.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('categories.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                        Kategori Produk
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('units.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('units.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                        Satuan Kuantitas
                                    </a>
                                </li>
                            @endif
                            @if($user->hasPermission('suppliers.view'))
                                <li>
                                    <a href="{{ route('suppliers.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('suppliers.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                        Pemasok / Supplier
                                    </a>
                                </li>
                            @endif
                            @if($user->hasPermission('catalog.manage') || $user->hasPermission('expenses.create'))
                                <li>
                                    <a href="{{ route('expense-categories.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('expense-categories.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                        Kategori Biaya
                                    </a>
                                </li>
                            @endif
                            @if($user->hasPermission('store_settings.manage'))
                                <li>
                                    <a href="{{ route('cash-accounts.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('cash-accounts.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                        Akun Kas & Bank
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('store-profile.edit') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('store-profile.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                        Profil Toko & Struk
                                    </a>
                                </li>
                            @endif
                        @endif

                        <!-- Inventory Links (Mobile) -->
                        @if($user && $user->hasPermission('inventory.view'))
                            <li class="pt-2 border-t border-white/10">
                                <span class="text-xs font-semibold uppercase text-slate-400">Persediaan</span>
                            </li>
                            <li>
                                <a href="{{ route('inventory.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('inventory.index') || request()->routeIs('inventory.card') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                    Saldo & Kartu Stok
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.low_stock') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('inventory.low_stock') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                    Stok Habis & Menipis
                                </a>
                            </li>
                        @endif

                        <!-- Purchasing Links (Mobile) -->
                        @if($user && ($user->hasPermission('purchases.view') || $user->hasPermission('purchases.create')))
                            <li class="pt-2 border-t border-white/10">
                                <span class="text-xs font-semibold uppercase text-slate-400">Pembelian</span>
                            </li>
                            <li>
                                <a href="{{ route('purchases.index') }}" class="group flex gap-x-3 rounded-md {{ request()->routeIs('purchases.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8' }} p-2 text-sm font-medium">
                                    Daftar PO & Penerimaan
                                </a>
                            </li>
                        @endif
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Static sidebar for desktop -->
<aside class="hidden md:flex md:w-64 md:flex-col md:inset-y-0 shrink-0 bg-[#26313f] border-r border-slate-700/60">
    <div class="flex flex-col flex-grow pt-5 pb-4 overflow-y-auto">
        <!-- Brand Header -->
        <div class="flex items-center flex-shrink-0 px-6 gap-2.5">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-slate-600 text-white font-bold text-lg">K</span>
            <div>
                <span class="text-xl font-bold tracking-tight text-white">Kasir<span class="text-slate-300">Pro</span></span>
                <span class="block text-[11px] font-medium text-slate-400 leading-none">Sistem Retail POS</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-8 flex-1 px-4 space-y-1">
            <a href="{{ route('dashboard') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-md {{ request()->routeIs('dashboard') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                Dashboard
            </a>

            <!-- Administration / User Management Section (Requires users.manage) -->
            @if($user && $user->hasPermission('users.manage'))
                <div class="pt-4 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    Administrasi
                </div>
                <a href="{{ route('users.index') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-md {{ request()->routeIs('users.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                    <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('users.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    Manajemen Pengguna
                </a>
            @endif

            <!-- Section Label: Master Data & Katalog -->
            @if($user && ($user->hasPermission('products.view') || $user->hasPermission('catalog.manage') || $user->hasPermission('suppliers.view') || $user->hasPermission('store_settings.manage')))
                <div class="pt-5 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    Master Data
                </div>

                <div class="space-y-0.5">
                    <a href="{{ route('master.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('master.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                        <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('master.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        Dashboard Master
                    </a>

                    @if($user->hasPermission('products.view'))
                        <a href="{{ route('products.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('products.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('products.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Katalog Produk
                        </a>
                    @endif

                    @if($user->hasPermission('catalog.manage'))
                        <a href="{{ route('categories.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('categories.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('categories.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            Kategori Produk
                        </a>
                        <a href="{{ route('units.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('units.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('units.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                            Satuan Presisi
                        </a>
                    @endif

                    @if($user->hasPermission('suppliers.view'))
                        <a href="{{ route('suppliers.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('suppliers.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('suppliers.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            Pemasok / Supplier
                        </a>
                    @endif

                    @if($user->hasPermission('catalog.manage') || $user->hasPermission('expenses.create'))
                        <a href="{{ route('expense-categories.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('expense-categories.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('expense-categories.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Kategori Biaya
                        </a>
                    @endif

                    @if($user->hasPermission('store_settings.manage'))
                        <a href="{{ route('cash-accounts.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('cash-accounts.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('cash-accounts.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            Akun Kas & Bank
                        </a>
                        <a href="{{ route('store-profile.edit') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('store-profile.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('store-profile.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            Profil Toko & Struk
                        </a>
                    @endif
                </div>
            @endif

            <!-- Section Label: Persediaan & Stok (FR-INV-02..07) -->
            @if($user && $user->hasPermission('inventory.view'))
                <div class="pt-5 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    Persediaan & Stok
                </div>

                <div class="space-y-0.5">
                    <a href="{{ route('inventory.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('inventory.index') || request()->routeIs('inventory.card') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                        <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('inventory.index') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Saldo & Kartu Stok
                    </a>

                    <a href="{{ route('inventory.low_stock') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('inventory.low_stock') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                        <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('inventory.low_stock') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Stok Habis & Menipis
                    </a>
                </div>
            @endif

            <!-- Section Label: Operasional Toko -->
            <div class="pt-5 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                Operasional Toko
            </div>

            <!-- Planned Modules (Filtered by User Permissions) -->
            <div class="space-y-0.5">
                @if($user && $user->hasPermission('sales.create'))
                    <span class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-500 opacity-70 cursor-not-allowed">
                        <svg class="mr-3 h-5 w-5 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        POS Kasir
                        <span class="ml-auto text-[10px] font-mono uppercase bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">T06</span>
                    </span>
                @endif

                @if($user && ($user->hasPermission('purchases.view') || $user->hasPermission('purchases.create')))
                    <a href="{{ route('purchases.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('purchases.*') ? 'bg-slate-600 text-white' : 'text-slate-300 hover:bg-white/8 hover:text-white' }}">
                        <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->routeIs('purchases.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Pembelian (PO)
                    </a>
                @endif

                @if($user && $user->hasPermission('stock_opname.create'))
                    <span class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-500 opacity-70 cursor-not-allowed">
                        <svg class="mr-3 h-5 w-5 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Stok Opname
                        <span class="ml-auto text-[10px] font-mono uppercase bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">T09</span>
                    </span>
                @endif

                @if($user && ($user->hasPermission('reports.view_all') || $user->hasPermission('reports.view_limited')))
                    <span class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-500 opacity-70 cursor-not-allowed">
                        <svg class="mr-3 h-5 w-5 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Laporan Operasional
                        <span class="ml-auto text-[10px] font-mono uppercase bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">T10</span>
                    </span>
                @endif
            </div>
        </nav>

        <!-- Current User Role Box -->
        <div class="px-4 mt-auto">
            <div class="rounded-md bg-white/5 p-3 border border-white/10">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full {{ $user?->is_active ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                    <span class="text-xs font-semibold text-slate-300 truncate">{{ $user?->name ?? 'Tamu' }}</span>
                </div>
                <p class="mt-1 text-[11px] text-slate-400 leading-tight">
                    Peran: <strong class="text-slate-300">{{ $user?->role?->name ?? 'None' }}</strong>
                </p>
            </div>
        </div>
    </div>
</aside>
