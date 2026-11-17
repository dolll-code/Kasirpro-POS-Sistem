@props(['title' => ''])

@php
    $user = auth()->user();
    $initials = $user ? strtoupper(substr($user->name, 0, 2)) : 'KP';
@endphp

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

        <!-- Right: Timezone indicator & User Profile Menu -->
        <div class="flex items-center gap-3 sm:gap-4">
            <!-- Timezone badge -->
            <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-50 border border-slate-200 text-xs font-medium text-slate-600">
                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ config('app.timezone', 'Asia/Jakarta') }}</span>
            </div>

            @auth
                <!-- Authenticated User Menu -->
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button type="button" 
                            @click="open = !open" 
                            class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <div class="h-8 w-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                            {{ $initials }}
                        </div>
                        <div class="hidden md:block text-left">
                            <div class="text-xs font-semibold text-slate-800">{{ $user->name }}</div>
                            <div class="text-[11px] text-slate-500">{{ $user->role?->name ?? 'User' }}</div>
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
                         class="absolute right-0 mt-2 w-52 rounded-xl bg-white shadow-xl ring-1 ring-slate-900/10 py-1 focus:outline-none z-50 border border-slate-100">
                        <div class="px-4 py-2.5 border-b border-slate-100">
                            <p class="text-xs font-medium text-slate-500">Masuk sebagai:</p>
                            <p class="text-xs font-bold text-slate-800 truncate">{{ $user->name }}</p>
                            <span class="inline-block mt-0.5 text-[10px] font-mono text-indigo-600 bg-indigo-50 px-1.5 py-0.2 rounded border border-indigo-200">
                                {{ $user->role?->code }}
                            </span>
                        </div>

                        <div class="py-1">
                            <a href="{{ route('profile.show') }}" class="flex items-center px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-indigo-600 gap-2">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Profil & Ubah Sandi
                            </a>

                            @if($user->hasPermission('users.manage'))
                                <a href="{{ route('users.index') }}" class="flex items-center px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-indigo-600 gap-2">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    Manajemen Pengguna
                                </a>
                            @endif
                        </div>

                        <div class="border-t border-slate-100 pt-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center px-4 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 gap-2">
                                    <svg class="h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Keluar (Logout)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    Masuk
                </a>
            @endauth
        </div>
    </div>
</header>
