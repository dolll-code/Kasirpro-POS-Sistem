@props(['title' => ''])

<header class="bg-white border-b border-slate-200 sticky top-0 z-30 flex-shrink-0">
    <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <!-- Left: Mobile menu button & Page Title -->
        <div class="flex items-center gap-3">
            <button type="button" 
                    @click="sidebarOpen = true" 
                    class="md:hidden -ml-2 p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <span class="sr-only">Buka menu sidebar</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            @if($title)
                <h1 class="text-xl font-bold tracking-tight text-slate-900 truncate">
                    {{ $title }}
                </h1>
            @else
                <div class="flex items-center gap-2">
                    <span class="text-base font-semibold text-slate-700">KasirPro POS System</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200/60">Single-Store MVP</span>
                </div>
            @endif
        </div>

        <!-- Right: Timezone indicator & User Profile Placeholder -->
        <div class="flex items-center gap-3 sm:gap-4">
            <!-- Timezone badge -->
            <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-50 border border-slate-200 text-xs font-medium text-slate-600">
                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ config('app.timezone', 'Asia/Jakarta') }}</span>
            </div>

            <!-- User Menu Component / Placeholder -->
            <div class="relative" x-data="{ open: false }" @click.away="open = false">
                <button type="button" 
                        @click="open = !open" 
                        class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <div class="h-8 w-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                        AD
                    </div>
                    <div class="hidden md:block text-left">
                        <div class="text-xs font-semibold text-slate-800">Admin Toko</div>
                        <div class="text-[11px] text-slate-500">Administrator</div>
                    </div>
                    <svg class="hidden md:block h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown menu -->
                <div x-show="open" 
                     x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-2 w-48 rounded-lg bg-white shadow-lg ring-1 ring-slate-900/10 py-1 focus:outline-none z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-xs font-medium text-slate-500">Role Sistem</p>
                        <p class="text-sm font-semibold text-slate-800 truncate">Administrator</p>
                    </div>
                    <div class="px-4 py-2 text-xs text-slate-400">
                        Auth aktif di Tahap 02
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
