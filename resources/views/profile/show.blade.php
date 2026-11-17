@extends('layouts.app', [
    'title' => 'Profil Saya & Pengaturan Keamanan',
    'breadcrumbs' => [
        'Profil Saya' => null
    ]
])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900">Profil & Keamanan Akun</h1>
        <p class="text-xs text-slate-500 mt-1">Periksa informasi akun aktif dan perbarui kata sandi Anda secara berkala.</p>
    </div>

    <!-- User Information Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-4">
        <h2 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">Informasi Akun</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
                <span class="text-slate-500 font-medium">Nama Lengkap</span>
                <div class="font-semibold text-slate-800 text-sm mt-0.5">{{ $user->name }}</div>
            </div>

            <div>
                <span class="text-slate-500 font-medium">Alamat Email</span>
                <div class="font-semibold text-slate-800 text-sm font-mono mt-0.5">{{ $user->email }}</div>
            </div>

            <div>
                <span class="text-slate-500 font-medium">Peran Akses</span>
                <div class="mt-0.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ $user->role?->name }} ({{ $user->role?->code }})
                    </span>
                </div>
            </div>

            <div>
                <span class="text-slate-500 font-medium">Login Terakhir</span>
                <div class="font-medium text-slate-700 mt-0.5">
                    {{ $user->last_login_at ? $user->last_login_at->translatedFormat('d F Y, H:i') : 'Saat ini' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Form (FR-AUTH-05) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-5">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Ubah Kata Sandi</h2>
            <p class="text-xs text-slate-500 mt-0.5">Pastikan akun Anda menggunakan kata sandi yang kuat dan aman minimal 8 karakter.</p>
        </div>

        <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-form.label for="current_password" value="Kata Sandi Saat Ini" required />
                <x-form.input id="current_password" name="current_password" type="password" required autocomplete="current-password" placeholder="••••••••" :error="$errors->has('current_password')" />
                <x-form.error :messages="$errors->get('current_password')" />
            </div>

            <div>
                <x-form.label for="password" value="Kata Sandi Baru" required />
                <x-form.input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Minimal 8 karakter" :error="$errors->has('password')" />
                <x-form.error :messages="$errors->get('password')" />
            </div>

            <div>
                <x-form.label for="password_confirmation" value="Konfirmasi Kata Sandi Baru" required />
                <x-form.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Ulangi kata sandi baru" :error="$errors->has('password_confirmation')" />
                <x-form.error :messages="$errors->get('password_confirmation')" />
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end">
                <x-button type="submit" variant="primary">
                    Perbarui Kata Sandi
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
