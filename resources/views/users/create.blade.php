@extends('layouts.app', [
    'title' => 'Tambah Pengguna Baru',
    'breadcrumbs' => [
        'Pengguna' => route('users.index'),
        'Tambah Baru' => null
    ]
])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Tambah Staf Baru</h1>
            <p class="text-xs text-slate-500 mt-1">Daftarkan akun kasir, supervisor, atau admin toko baru.</p>
        </div>
        <a href="{{ route('users.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke Daftar
        </a>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-6 sm:p-8">
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
            @csrf

            <div>
                <x-form.label for="name" value="Nama Lengkap" required />
                <x-form.input id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: Ahmad Kasir" required autofocus :error="$errors->has('name')" />
                <x-form.error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-form.label for="email" value="Alamat Email (Login)" required />
                <x-form.input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="ahmad@kasirpro.local" required :error="$errors->has('email')" />
                <x-form.error :messages="$errors->get('email')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-form.label for="role_id" value="Peran (Role Akses)" required />
                    <x-form.select id="role_id" name="role_id" required :error="$errors->has('role_id')">
                        <option value="">Pilih Peran...</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                {{ $role->name }} ({{ $role->code }})
                            </option>
                        @endforeach
                    </x-form.select>
                    <x-form.error :messages="$errors->get('role_id')" />
                </div>

                <div>
                    <x-form.label for="password" value="Kata Sandi Awal" required />
                    <x-form.input id="password" name="password" type="password" placeholder="Minimal 8 karakter" required :error="$errors->has('password')" />
                    <x-form.error :messages="$errors->get('password')" />
                </div>
            </div>

            <div class="pt-2">
                <label class="flex items-center text-xs text-slate-700 cursor-pointer select-none">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-700 shadow-xs focus:ring-slate-600 mr-2">
                    Akun langsung aktif dan dapat digunakan untuk login
                </label>
            </div>

            <div class="pt-5 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('users.index') }}" class="px-4 py-2 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                    Batal
                </a>
                <x-button type="submit" variant="primary">
                    Simpan Pengguna
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
