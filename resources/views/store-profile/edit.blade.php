@extends('layouts.app', [
    'title' => 'Profil Toko & Pengaturan Struk',
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Profil Toko & Struk' => null
    ]
])

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Profil Toko & Pengaturan Struk</h1>
            <p class="text-xs text-slate-500 mt-1">Identitas resmi toko, snapshot nota transaksi kasir, dan konfigurasi printer thermal (FR-STORE-01).</p>
        </div>
        <a href="{{ route('master.index') }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">
            &larr; Kembali ke Master Data
        </a>
    </div>

    <!-- Store Profile Form Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <form method="POST" action="{{ route('store-profile.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Store Details Section -->
            <div>
                <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">Identitas Usaha & Kontak</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <x-form.label for="name" value="Nama Toko / Usaha" required />
                        <x-form.input id="name" name="name" value="{{ old('name', $profile->name) }}" required autofocus placeholder="Contoh: KasirPro Mart" />
                        @error('name')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <x-form.label for="phone" value="Nomor Telepon Toko" />
                            <x-form.input id="phone" name="phone" value="{{ old('phone', $profile->phone) }}" placeholder="021-5551234" />
                            @error('phone')
                                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-form.label for="email" value="Email Toko" />
                            <x-form.input id="email" type="email" name="email" value="{{ old('email', $profile->email) }}" placeholder="kontak@toko.com" />
                            @error('email')
                                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-form.label for="tax_id" value="NPWP / Nomor Pokok Wajib Pajak" />
                            <x-form.input id="tax_id" name="tax_id" value="{{ old('tax_id', $profile->tax_id) }}" placeholder="01.234.567.8-012.000" />
                            @error('tax_id')
                                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <x-form.label for="address" value="Alamat Lengkap Toko" />
                        <textarea id="address" name="address" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Alamat jalan, nomor ruko, kota...">{{ old('address', $profile->address) }}</textarea>
                        @error('address')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Receipt & Thermal Settings -->
            <div class="pt-2">
                <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">Pengaturan Struk Kasir (Printer Thermal)</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <x-form.label for="receipt_paper_width" value="Lebar Kertas Printer Thermal" required />
                        <div class="grid grid-cols-2 gap-4 max-w-md">
                            <label class="flex items-center gap-3 p-3 rounded-lg border {{ old('receipt_paper_width', $profile->receipt_paper_width) === '58' ? 'border-slate-800 bg-slate-50' : 'border-slate-200' }} cursor-pointer">
                                <input type="radio" name="receipt_paper_width" value="58" {{ old('receipt_paper_width', $profile->receipt_paper_width) === '58' ? 'checked' : '' }} class="text-slate-800 focus:ring-slate-800">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900">58 mm</span>
                                    <span class="block text-xs text-slate-500">Printer Kasir Standar (32 kolom)</span>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 p-3 rounded-lg border {{ old('receipt_paper_width', $profile->receipt_paper_width) === '80' ? 'border-slate-800 bg-slate-50' : 'border-slate-200' }} cursor-pointer">
                                <input type="radio" name="receipt_paper_width" value="80" {{ old('receipt_paper_width', $profile->receipt_paper_width) === '80' ? 'checked' : '' }} class="text-slate-800 focus:ring-slate-800">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900">80 mm</span>
                                    <span class="block text-xs text-slate-500">Printer Kasir Lebar (48 kolom)</span>
                                </div>
                            </label>
                        </div>
                        @error('receipt_paper_width')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <x-form.label for="receipt_header" value="Teks Header Struk (Atas Nota)" />
                        <textarea id="receipt_header" name="receipt_header" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Pesan sambutan di bagian atas struk...">{{ old('receipt_header', $profile->receipt_header) }}</textarea>
                        @error('receipt_header')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <x-form.label for="receipt_footer" value="Teks Footer Struk (Bawah Nota)" />
                        <textarea id="receipt_footer" name="receipt_footer" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Kebijakan retur barang atau ucapan terima kasih di bagian bawah struk...">{{ old('receipt_footer', $profile->receipt_footer) }}</textarea>
                        @error('receipt_footer')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Logo Section -->
            <div class="pt-2">
                <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">Logo Struk & Aplikasi</h2>
                <div class="mt-4 flex flex-col sm:flex-row items-start gap-6">
                    @if($profile->logo_path)
                        <div class="shrink-0">
                            <span class="block text-xs font-semibold text-slate-500 mb-1">Logo Saat Ini:</span>
                            <div class="h-20 w-32 rounded-lg border border-slate-200 bg-slate-50 p-2 flex items-center justify-center overflow-hidden">
                                <img src="{{ asset('storage/' . $profile->logo_path) }}" alt="Logo Toko" class="max-h-full max-w-full object-contain" />
                            </div>
                        </div>
                    @endif

                    <div class="flex-1">
                        <x-form.label for="logo" value="Unggah Berkas Logo Baru" />
                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-white hover:file:bg-slate-700" />
                        <p class="text-[11px] text-slate-400 mt-1">Format gambar: PNG, JPG, JPEG, WEBP. Maksimum ukuran berkas: 2 MB.</p>
                        @error('logo')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-6 border-t border-slate-100 flex justify-end gap-3">
                <x-button type="submit" variant="primary" size="md">
                    Simpan Profil & Pengaturan Struk
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
