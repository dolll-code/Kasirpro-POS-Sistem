<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk — {{ config('app.name', 'KasirPro') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex items-center justify-center p-4 sm:p-6 lg:p-8 font-sans antialiased text-slate-800 bg-radial from-slate-800 to-slate-950">
    <div class="w-full max-w-md">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white font-bold text-2xl shadow-lg shadow-indigo-600/30 mb-3 border border-indigo-400/30">
                K
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Kasir<span class="text-indigo-400">Pro</span></h1>
            <p class="mt-1 text-xs text-slate-400">Sistem POS & Manajemen Operasional Toko</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-2xl border border-slate-100">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-900">Masuk ke Akun Anda</h2>
                <p class="text-xs text-slate-500 mt-1">Masukkan kredensial kerja Anda untuk memulai operasional kasir.</p>
            </div>

            <!-- Flash & Error alerts -->
            <x-flash-message />

            <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf

                <div>
                    <x-form.label for="email" value="Alamat Email" required />
                    <x-form.input id="email" 
                                  name="email" 
                                  type="email" 
                                  value="{{ old('email') }}" 
                                  required 
                                  autofocus 
                                  autocomplete="username"
                                  placeholder="nama@toko.com"
                                  :error="$errors->has('email')" />
                    <x-form.error :messages="$errors->get('email')" />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <x-form.label for="password" value="Kata Sandi" required class="mb-0" />
                    </div>
                    <x-form.input id="password" 
                                  name="password" 
                                  type="password" 
                                  required 
                                  autocomplete="current-password"
                                  placeholder="••••••••"
                                  :error="$errors->has('password')" />
                    <x-form.error :messages="$errors->get('password')" />
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center text-xs text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 shadow-xs focus:ring-indigo-500 mr-2">
                        Ingat sesi saya
                    </label>
                </div>

                <div class="pt-2">
                    <x-button type="submit" variant="primary" class="w-full py-2.5 text-sm" x-bind:loading="submitting">
                        Masuk ke Sistem
                    </x-button>
                </div>
            </form>
        </div>

        <!-- Quick Demo Accounts Helper -->
        <div class="mt-6 text-center text-xs text-slate-400 bg-slate-900/60 rounded-xl p-4 border border-slate-800">
            <div class="font-semibold text-slate-300 mb-1">Akun Standar Tahap 02:</div>
            <div class="space-y-0.5 text-[11px] font-mono text-slate-400">
                <div>Admin: <span class="text-indigo-300 select-all">admin@kasirpro.local</span> / password</div>
                <div>Supervisor: <span class="text-indigo-300 select-all">supervisor@kasirpro.local</span> / password</div>
                <div>Kasir: <span class="text-indigo-300 select-all">kasir@kasirpro.local</span> / password</div>
            </div>
        </div>
    </div>
</body>
</html>
