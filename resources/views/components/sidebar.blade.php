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
         class="fixed inset-0 bg-slate-900/80"></div>

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
            <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-slate-900 px-6 pb-4">
                <div class="flex h-16 shrink-0 items-center border-b border-slate-800 gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white font-bold text-lg">K</span>
                    <span class="text-xl font-bold tracking-tight text-white">Kasir<span class="text-indigo-400">Pro</span></span>
                </div>
                <nav class="flex flex-1 flex-col">
                    <ul role="list" class="flex flex-1 flex-col gap-y-7">
                        <li>
                            <ul role="list" class="-mx-2 space-y-1">
                                <li>
                                    <a href="{{ url('/') }}" class="group flex gap-x-3 rounded-md bg-slate-800 p-2 text-sm font-semibold text-white">
                                        <svg class="h-5 w-5 shrink-0 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                        </svg>
                                        Dashboard
                                    </a>
                                </li>
                                <li>
                                    <span class="flex gap-x-3 rounded-md p-2 text-sm font-semibold text-slate-400 opacity-60 cursor-not-allowed">
                                        <svg class="h-5 w-5 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        POS Kasir (Tahap 06)
                                    </span>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Static sidebar for desktop -->
<aside class="hidden md:flex md:w-64 md:flex-col md:inset-y-0 shrink-0 bg-slate-900 border-r border-slate-800">
    <div class="flex flex-col flex-grow pt-5 pb-4 overflow-y-auto">
        <!-- Brand Header -->
        <div class="flex items-center flex-shrink-0 px-6 gap-2.5">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white font-bold text-lg shadow-sm">K</span>
            <div>
                <span class="text-xl font-bold tracking-tight text-white">Kasir<span class="text-indigo-400">Pro</span></span>
                <span class="block text-[11px] font-medium text-slate-400 leading-none">Sistem Retail POS</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-8 flex-1 px-4 space-y-1">
            <a href="{{ url('/') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->is('/') ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <svg class="mr-3 h-5 w-5 shrink-0 {{ request()->is('/') ? 'text-white' : 'text-slate-400 group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                Foundation Preview
            </a>

            <!-- Section Label: Operasional Toko -->
            <div class="pt-5 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                Operasional Toko
            </div>

            <!-- Planned Modules (Disabled visual indicator) -->
            <div class="space-y-0.5">
                <span class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-500 opacity-70 cursor-not-allowed">
                    <svg class="mr-3 h-5 w-5 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    POS Kasir
                    <span class="ml-auto text-[10px] font-mono uppercase bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">T06</span>
                </span>

                <span class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-500 opacity-70 cursor-not-allowed">
                    <svg class="mr-3 h-5 w-5 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Katalog & Produk
                    <span class="ml-auto text-[10px] font-mono uppercase bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">T03</span>
                </span>

                <span class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-500 opacity-70 cursor-not-allowed">
                    <svg class="mr-3 h-5 w-5 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Pengadaan (PO)
                    <span class="ml-auto text-[10px] font-mono uppercase bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">T04</span>
                </span>

                <span class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-500 opacity-70 cursor-not-allowed">
                    <svg class="mr-3 h-5 w-5 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Stok Opname
                    <span class="ml-auto text-[10px] font-mono uppercase bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">T09</span>
                </span>

                <span class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-slate-500 opacity-70 cursor-not-allowed">
                    <svg class="mr-3 h-5 w-5 shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Laporan Operasional
                    <span class="ml-auto text-[10px] font-mono uppercase bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">T10</span>
                </span>
            </div>
        </nav>

        <!-- System Baseline Info Box -->
        <div class="px-4 mt-auto">
            <div class="rounded-lg bg-slate-800/80 p-3 border border-slate-700/60">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                    <span class="text-xs font-semibold text-slate-300">Tahap 01 Foundation</span>
                </div>
                <p class="mt-1 text-[11px] text-slate-400 leading-tight">
                    Laravel {{ app()->version() }} • PHP {{ PHP_VERSION }} • MySQL 8.0
                </p>
            </div>
        </div>
    </div>
</aside>
