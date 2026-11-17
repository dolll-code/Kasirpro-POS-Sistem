@extends('layouts.app', [
    'title' => 'Edit Kategori: ' . $category->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Kategori' => route('categories.index'),
        'Edit' => null
    ]
])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Ubah Data Kategori</h1>
            <p class="text-xs text-slate-500 mt-1">Perbarui nama, slug, deskripsi, atau status aktif kategori.</p>
        </div>
        <a href="{{ route('categories.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke daftar
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('categories.update', $category) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-form.label for="name" value="Nama Kategori" required />
                <x-form.input id="name" name="name" value="{{ old('name', $category->name) }}" required autofocus />
                @error('name')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-form.label for="slug" value="Slug URL" />
                <x-form.input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" />
                <p class="text-[11px] text-slate-400 mt-0.5">Slug unik untuk pengalamatan tautan internal.</p>
                @error('slug')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-form.label for="description" value="Deskripsi Singkat" />
                <textarea id="description" name="description" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800">{{ old('description', $category->description) }}</textarea>
                @error('description')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $category->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                    <span class="text-sm font-medium text-slate-700">Kategori aktif</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('categories.index') }}" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <x-button type="submit" variant="primary" size="md">
                    Simpan Perubahan
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
