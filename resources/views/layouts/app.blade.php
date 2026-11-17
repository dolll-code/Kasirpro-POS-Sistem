<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#f6f7f8]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' — ' . config('app.name', 'KasirPro') : config('app.name', 'KasirPro') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full font-sans antialiased text-slate-800" x-data="{ sidebarOpen: false }">
    <div class="min-h-full flex">
        <!-- Sidebar Navigation (Desktop & Mobile Drawer) -->
        <x-sidebar />

        <!-- Main Content Column -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Top Header -->
            <x-header :title="$title ?? ''" />

            <!-- Breadcrumbs bar if provided -->
            @isset($breadcrumbs)
                <div class="bg-white border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-2.5">
                    <x-breadcrumb :items="$breadcrumbs" />
                </div>
            @endisset

            <!-- Page Body -->
            <main class="flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                <!-- Flash Messages -->
                <x-flash-message />

                @if(isset($slot) && !empty(trim($slot)))
                    {{ $slot }}
                @else
                    @yield('content')
                @endif
            </main>
        </div>
    </div>

    <!-- Confirmation Modal Component Stack -->
    @stack('modals')
    @stack('scripts')
</body>
</html>
