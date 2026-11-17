@extends('layouts.app', [
    'title' => 'Tahap 01 — Project Setup & Foundation Preview',
    'breadcrumbs' => [
        'Foundation' => null
    ]
])

@section('content')
<div class="space-y-8 max-w-7xl mx-auto">
    <!-- Header Banner -->
    <div class="rounded-2xl bg-gradient-to-r from-indigo-900 via-slate-900 to-slate-900 p-6 sm:p-8 text-white shadow-md border border-slate-800">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-xs font-semibold uppercase tracking-wider mb-3">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Tahap 01 Selesai
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">KasirPro Application Foundation</h1>
                <p class="mt-2 text-sm text-slate-300 max-w-2xl leading-relaxed">
                    Kerangka dasar aplikasi telah berhasil diinisialisasi mematuhi spesifikasi PRD v1.0.0. Halaman ini mendemonstrasikan seluruh komponen UI bersama (Shared UI Foundation) yang siap digunakan pada tahap-tahap berikutnya.
                </p>
            </div>
            <div class="shrink-0 flex flex-wrap gap-2">
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300">
                    PHP {{ PHP_VERSION }}
                </span>
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300">
                    Laravel {{ app()->version() }}
                </span>
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300">
                    MySQL 8.0 (InnoDB)
                </span>
            </div>
        </div>
    </div>

    <!-- Section 1: Baseline Architecture Status Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider">Database Engine</span>
                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6" />
                </svg>
            </div>
            <div class="text-lg font-bold text-slate-900">MySQL / InnoDB</div>
            <p class="text-xs text-slate-500 mt-1">ACID, FK, Row Locks ready</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider">Timezone Bisnis</span>
                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="text-lg font-bold text-slate-900">{{ config('app.timezone') }}</div>
            <p class="text-xs text-slate-500 mt-1">Format waktu WIB terpusat</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider">Decimal Aritmatika</span>
                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
            </div>
            <div class="text-lg font-bold text-slate-900">ext-bcmath</div>
            <p class="text-xs text-slate-500 mt-1">Tanpa galat floating-point</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider">Frontend Engine</span>
                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
            </div>
            <div class="text-lg font-bold text-slate-900">Blade + Alpine.js</div>
            <p class="text-xs text-slate-500 mt-1">Tailwind CSS v4 + Vite 6</p>
        </div>
    </div>

    <!-- Section 2: Shared UI Components Showcase -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Form Components Showcase -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-base font-semibold text-slate-900">1. Form & Input Components</h2>
                <p class="text-xs text-slate-500 mt-0.5">Komponen formulir standar dengan label, state normal, dan state validasi error.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <x-form.label value="Kode SKU Barang" required />
                    <x-form.input placeholder="Contoh: SKU-00123" />
                </div>

                <div>
                    <x-form.label value="Input dengan Error Validasi" required />
                    <x-form.input error placeholder="SKU duplikat" value="SKU-DUPLIKAT" />
                    <x-form.error :messages="['Kode SKU sudah digunakan pada produk lain.', 'Format SKU wajib menggunakan huruf kapital.']" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-form.label value="Satuan Unit" />
                        <x-form.select>
                            <option>Pcs (Pieces - Presisi 0)</option>
                            <option>Kg (Kilogram - Presisi 3)</option>
                            <option>Liter (Presisi 2)</option>
                        </x-form.select>
                    </div>

                    <div>
                        <x-form.label value="Status Input Nonaktif" />
                        <x-form.input disabled value="Nilai terkunci otomatis" />
                    </div>
                </div>

                <div>
                    <x-form.label value="Catatan Transaksi (Textarea)" />
                    <x-form.textarea placeholder="Masukkan keterangan tambahan jika diperlukan...">Contoh catatan operasional toko.</x-form.textarea>
                </div>
            </div>
        </div>

        <!-- Button & Modal Showcase -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-base font-semibold text-slate-900">2. Action Buttons & Confirmation Modal</h2>
                <p class="text-xs text-slate-500 mt-0.5">Dukungan varian warna, ukuran, loading state, dan dialog konfirmasi Alpine.js.</p>
            </div>

            <div class="space-y-4">
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-slate-600">Varian Tombol Standar:</p>
                    <div class="flex flex-wrap gap-2">
                        <x-button variant="primary">Primary</x-button>
                        <x-button variant="secondary">Secondary</x-button>
                        <x-button variant="danger">Danger</x-button>
                        <x-button variant="success">Success</x-button>
                        <x-button variant="outline">Outline</x-button>
                        <x-button variant="ghost">Ghost</x-button>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold text-slate-600">State Khusus:</p>
                    <div class="flex flex-wrap gap-2">
                        <x-button variant="primary" loading>Memproses...</x-button>
                        <x-button variant="secondary" disabled>Nonaktif</x-button>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200">
                    <p class="text-xs font-semibold text-slate-600 mb-2">Simulasi Modal Dialog Konfirmasi:</p>
                    <x-button variant="primary" x-on:click="$dispatch('open-modal', 'demo-modal')">
                        <svg class="h-4 w-4 mr-1 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Buka Modal Konfirmasi
                    </x-button>

                    <!-- The Modal Instance -->
                    <x-modal name="demo-modal" title="Konfirmasi Posting Transaksi">
                        <div class="space-y-3">
                            <p class="text-sm text-slate-600">
                                Apakah Anda yakin ingin memposting transaksi ini? Setelah diposting, mutasi persediaan dan buku kas akan dicatat secara <strong class="text-slate-800">append-only</strong> dan tidak dapat dihapus.
                            </p>
                            <div class="rounded-lg bg-amber-50 p-3 border border-amber-200 text-xs text-amber-800">
                                <strong>Catatan Audit:</strong> Aksi posting akan mencatat ID pengguna, IP address, dan timestamp WIB pada tabel audit log.
                            </div>
                        </div>

                        <x-slot:footer>
                            <x-button variant="primary" x-on:click="$dispatch('close-modal', 'demo-modal')">
                                Ya, Lanjutkan
                            </x-button>
                            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'demo-modal')">
                                Batal
                            </x-button>
                        </x-slot:footer>
                    </x-modal>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Table & Pagination Showcase -->
    <div>
        <x-table.card title="3. Table & Pagination Presentation" 
                      description="Komponen tabel data terstruktur dengan header styling, badge status, dan pagination baris.">
            <x-slot:actions>
                <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 text-xs font-medium border border-indigo-200/60">
                    Contoh Data Presentation
                </span>
            </x-slot:actions>

            <x-table>
                <thead>
                    <tr>
                        <x-table.th>Kode Transaksi</x-table.th>
                        <x-table.th>Tipe Ledger</x-table.th>
                        <x-table.th align="right">Nilai Rupiah</x-table.th>
                        <x-table.th align="center">Status Posting</x-table.th>
                        <x-table.th align="right">Waktu (WIB)</x-table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <x-table.td class="font-mono font-medium text-indigo-600">TRX-20261117-00001</x-table.td>
                        <x-table.td>Penjualan POS (Tunai)</x-table.td>
                        <x-table.td align="right" class="font-semibold text-slate-900">Rp 125.000,00</x-table.td>
                        <x-table.td align="center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Completed
                            </span>
                        </x-table.td>
                        <x-table.td align="right" class="text-xs text-slate-500">17 Nov 2026, 12:30</x-table.td>
                    </tr>
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <x-table.td class="font-mono font-medium text-indigo-600">TRX-20261117-00002</x-table.td>
                        <x-table.td>Penjualan POS (Transfer Manual)</x-table.td>
                        <x-table.td align="right" class="font-semibold text-slate-900">Rp 450.000,00</x-table.td>
                        <x-table.td align="center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                Pending (TTL 15m)
                            </span>
                        </x-table.td>
                        <x-table.td align="right" class="text-xs text-slate-500">17 Nov 2026, 12:45</x-table.td>
                    </tr>
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <x-table.td class="font-mono font-medium text-indigo-600">PO-20261117-00001</x-table.td>
                        <x-table.td>Penerimaan Barang Pemasok</x-table.td>
                        <x-table.td align="right" class="font-semibold text-slate-900">Rp 2.750.000,00</x-table.td>
                        <x-table.td align="center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Accepted
                            </span>
                        </x-table.td>
                        <x-table.td align="right" class="text-xs text-slate-500">17 Nov 2026, 13:00</x-table.td>
                    </tr>
                </tbody>
            </x-table>

            <x-slot:footer>
                <x-pagination :current-page="1" :total-pages="3" :total-items="25" :from="1" :to="3" prev-url="#" next-url="#" />
            </x-slot:footer>
        </x-table.card>
    </div>

    <!-- Section 4: Loading, Empty, and Error States -->
    <div>
        <h2 class="text-base font-semibold text-slate-900 mb-3">4. Dynamic UI States (Loading, Empty, and Error)</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Loading State Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex flex-col justify-center">
                <x-loading text="Memproses kalkulasi keranjang POS..." />
            </div>

            <!-- Empty State Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-2 flex flex-col justify-center">
                <x-empty-state title="Belum Ada Dokumen" description="Data sesi persediaan belum dibuat untuk periode aktif.">
                    <x-slot:action>
                        <x-button size="sm" variant="secondary">Tambah Dokumen</x-button>
                    </x-slot:action>
                </x-empty-state>
            </div>

            <!-- Error State Card with Correlation ID -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-2 flex flex-col justify-center">
                <x-error-state title="Stok Tidak Mencukupi" message="Stok tersedia untuk produk terpilih telah habis di kasir lain.">
                    <x-slot:action>
                        <x-button size="sm" variant="danger">Perbarui Keranjang</x-button>
                    </x-slot:action>
                </x-error-state>
            </div>
        </div>
    </div>
</div>
@endsection
