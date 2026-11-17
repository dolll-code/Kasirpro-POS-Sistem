<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 Akses Ditolak — {{ config('app.name', 'KasirPro') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex items-center justify-center p-4 sm:p-6 lg:p-8 font-sans antialiased text-slate-800">
    <div class="w-full max-w-lg text-center bg-white rounded-lg p-8 sm:p-10 border border-slate-200">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 mb-5 shadow-xs">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
        </div>

        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200 mb-2">
            HTTP 403 Forbidden
        </span>

        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Akses Ditolak</h1>
        <p class="mt-2 text-sm text-slate-500 leading-relaxed max-w-md mx-auto">
            {{ $exception->getMessage() ?: 'Peran akun Anda tidak memiliki izin untuk mengakses halaman atau tindakan ini.' }}
        </p>

        @php
            $requestId = request()->header('X-Request-Id');
        @endphp
        @if($requestId)
            <div class="mt-4 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs font-mono text-slate-600">
                <span class="text-slate-400">Request ID:</span>
                <span class="font-semibold text-rose-600 select-all">{{ $requestId }}</span>
            </div>
        @endif

        <div class="mt-8 flex justify-center gap-3">
            <a href="{{ url('/') }}" class="inline-flex items-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition">
                Kembali ke Beranda
            </a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50 transition shadow-sm">
                        Keluar Akun
                    </button>
                </form>
            @endauth
        </div>
    </div>
</body>
</html>
