# 0000. Project Baseline & Architectural Decisions

| Metadata | Nilai |
|---|---|
| Status | Ditetapkan (Baseline) / Menunggu Persetujuan Stakeholder untuk Production Gate |
| Owner | Lead Engineer / Architect |
| Versi/release berlaku | 1.0.0 (Tahap 00 - Pemeriksaan Awal) |
| Terakhir ditinjau | 2026-11-17 |
| Sumber terkait | `prd.md` (§3, §9, §10, §11, §12, §14, §17, §18) |

---

## 1. Konteks

Proyek KasirPro dirancang sebagai sistem Point of Sale (POS) dan manajemen operasional toko retail/minimarket berbasis web untuk satu toko/cabang fisik. Berdasarkan Product Requirements Document (PRD) v1.0.0, sistem mengelola master katalog, persediaan barang dengan kalkulasi Harga Pokok Penjualan (HPP) Moving Weighted Average, transaksi penjualan tunai dan transfer bank manual, retur barang bersekat kondisi (layak jual vs rusak), biaya operasional, pencatatan kas/bank aktual, stok opname, serta pelaporan operasional (Laba Rugi Sederhana dan Arus Kas).

Sebelum memulai fase konstruksi kode (Tahap 01), keputusan arsitektural dasar harus ditetapkan secara eksplisit untuk mencegah fragmentasi stack, celah keamanan, dan inkonsistensi data transaksi.

---

## 2. Opsi yang Dievaluasi & Keputusan

### 2.1 Arsitektur Sistem: Modular Monolith (Laravel MVC + Service Layer)

- **Opsi yang dipertimbangkan:**
  1. *Microservices:* Memisahkan service auth, inventory, sales, dan reporting. (Ditolak: Kompleksitas operasional, network latency, distributed transaction/2PC overhead yang berlebihan untuk satu toko).
  2. *Single Page Application (SPA) terpisah (e.g., Vue/React + Laravel API):* (Ditolak: Duplikasi kalkulasi logika, overhead sinkronisasi state keranjang, dan melanggar prinsip *Server-authoritative pricing* serta kesederhanaan operasional MVP).
  3. *Modular Monolith Laravel (Blade + Service Layer):* (Diterima).
- **Keputusan:** Menggunakan **Modular Monolith** dengan pola Laravel MVC yang diperluas dengan Service Layer dan Form Request.
  - Controllers tipis (orchestration only).
  - Business rules, kalkulasi finansial, dan transaksi DB berada di Service Layer (`app/Services/*`).
  - Strict server-authoritative logic: Browser tidak dipercaya dalam penetapan harga, HPP, diskon, atau grand total.

### 2.2 Frontend Interactivity: Blade + Tailwind CSS + Alpine.js

- **Opsi yang dipertimbangkan:**
  1. *Blade + Vanilla JavaScript murni:* (Kurang modular dan rawan boilerplate DOM manipulation berlebihan untuk POS cart).
  2. *Blade + Alpine.js:* (Diterima: Ringan, deklaratif, berkolaborasi sempurna dengan Blade components, ideal untuk keyboard-wedge scanner events, preview totals, modal toggles, dan loading states tanpa build runtime berat).
  3. *Inertia.js + Vue/React:* (Menambah dependensi build dan dual-paradigm untuk MVP yang difokuskan pada server-rendered correctness).
- **Keputusan:** Mengadopsi **Blade + Tailwind CSS (via Vite) + Alpine.js**. Hanya satu framework interaktivitas UI yang dipakai; tidak mencampur Vue/React ke dalam proyek.

### 2.3 Framework Testing Utama: Pest v3

- **Opsi yang dipertimbangkan:**
  1. *PHPUnit standard:* Mapan, namun lebih verbose untuk dataset testing matematika diskon dan alur transaksi.
  2. *Pest v3:* (Diterima: Berjalan di atas PHPUnit, sintaks ringkas, mendukung Higher-Order Testing, architecture testing bawaan untuk enforce boundary layer, serta integrasi resmi Laravel).
- **Keputusan:** Mengadopsi **Pest v3** sebagai framework testing utama untuk Unit, Feature, dan Integration testing. Framework browser testing yang dipilih jika diperlukan adalah **Laravel Dusk** (headless Chrome) untuk menguji simulasi scanner barcode USB dan transaksi POS end-to-end.

### 2.4 Package Dokumen (PDF & XLSX): Dompdf & Laravel Excel

- **Opsi XLSX:**
  - `maatwebsite/excel` (Laravel Excel) vs `openspout/openspout`.
  - Keputusan: Menggunakan **`maatwebsite/excel`** untuk export laporan tabular yang diformat dengan header metadata, summary cards, dan styling yang konsisten. Wajib menerapkan sanitasi formula injection (sel teks yang diawali `=`, `+`, `-`, `@`, `\t`, `\r` harus diawali apostrof).
- **Opsi PDF:**
  - `barryvdh/laravel-dompdf` (Dompdf) vs headless Chromium (`spatie/laravel-pdf`).
  - Keputusan: Menggunakan **`barryvdh/laravel-dompdf`**. Berjalan murni di PHP tanpa kebutuhan binari eksternal Chromium/Node di server hosting toko, mendukung template Blade portrait/landscape dan receipt 58mm/80mm.

### 2.5 Presisi Numerik & Aritmatika: Decimal-Safe (ext-bcmath)

- **Keputusan:** Nilai uang disimpan sebagai `DECIMAL(15,2)`, kuantitas sebagai `DECIMAL(15,3)`, dan persentase sebagai `DECIMAL(7,4)`.
- **Aturan:** Dilarang keras menggunakan tipe data floating-point PHP (`float`/`double`) untuk perhitungan finansial atau kuantitas stok. Perhitungan diskon proporsional dan moving average dilakukan menggunakan fungsi `bcmath` (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bcpow`) atau helper class bertipe decimal dengan aturan pembulatan deterministik (*half-up* atau residual allocation).

### 2.6 Integritas Transaksi & Immutability Ledger

- **Keputusan:**
  1. **Append-Only Ledgers:** Tabel `stock_movements` dan `cash_movements` bersifat immutable. Tidak ada antarmuka edit atau delete untuk record ledger yang telah diposting. Koreksi dilakukan melalui reversal record atau adjustment sah.
  2. **Row-Level Locking:** Operasi kritis (checkout kasir, receiving PO, approval retur, dan posting stok opname) wajib mengunci baris terkait (`SELECT ... FOR UPDATE`) pada tabel `inventory_balances`, `purchases`, `sales`, dan `cash_accounts`.
  3. **Idempotency:** Header `Idempotency-Key` (UUIDv4) wajib disertakan pada mutasi transaksi finansial dan inventori untuk mencegah eksekusi ganda akibat retry jaringan atau double submit.
  4. **Strict Maker-Checker:** Pembuat request transaksi sensitif (diskon melampaui limit, verifikasi transfer, retur barang, adjustment kas, dan posting opname) dilarang menyetujui permintaannya sendiri.

---

## 3. Keputusan Runtime & Production Gate

| Komponen | Baseline Lokal / Permintaan Awal | Rekomendasi Production Gate PRD | Status Keputusan |
|---|---|---|---|
| **PHP** | PHP 8.2.29 (ZTS x64) | PHP 8.4 (patch terbaru) | **Local Dev Sandbox Only**. Dilarang deploy 8.2.29 ke production baru tanpa persetujuan risiko formal. |
| **MySQL** | MySQL 8.0.30 | MySQL 8.4 LTS | **Local Dev Sandbox Only**. MySQL 8.0 sudah EOL komunitas per April 2026. Target target produksi: 8.4 LTS. |
| **Laravel** | Laravel 12.x | Laravel 13.x (setelah regression test) | Laravel 12 dipakai sebagai fondasi awal; rencanakan uji kompatibilitas Laravel 13 sebelum security EOL. |

### Kebijakan Production Gate:
1. Pengembangan Tahap 01–08 dapat berjalan di atas lingkungan lokal baseline (PHP 8.2.29 dan MySQL 8.0.30) dengan mengunci kompatibilitas syntax dan fungsi.
2. Sebelum release production (Tahap 09/UAT): Environment staging wajib dijalankan di atas PHP 8.4 dan MySQL 8.4 LTS untuk memastikan seluruh suite test lulus tanpa regresi.
3. Jika pemilik produk memilih tetap menggunakan PHP 8.2 / MySQL 8.0 di production, wajib menandatangani surat penerimaan risiko (Risk Acceptance Register) yang menyatakan pemahaman atas kerentanan CVE pada build lama tersebut.

---

## 4. Konsekuensi

### Positif:
- Arsitektur modular monolith meminimalkan latensi dan kerumitan deployment pada server toko retail tunggal.
- Pengecekan harga di backend (*server-authoritative*) dan row-locking mencegah eksploitasi manipulasi keranjang serta stok minus akibat race condition.
- Ledger append-only menjamin auditabilitas penuh terhadap setiap rupiah uang kas dan setiap unit stok barang.
- Pest v3 mempercepat penulisan test skenario kalkulasi edge-case.

### Negatif / Mitigasi:
- Server-side rendering dengan Alpine.js memerlukan penanganan cermat untuk event scanner barcode agar tidak kehilangan fokus keyboard input. Mitigasi: implementasi autofocus trap dan event listener debounce pada input scan POS.
- Dompdf dapat mengalami konsumsi memori tinggi jika merender tabel ratusan halaman. Mitigasi: pembatasan filter rentang tanggal laporan, pagination, dan delegasi export besar ke queue background.

---

## 5. Referensi Silang
- `prd.md` — Bab 3 (Asumsi & Desain), Bab 9 (Arsitektur), Bab 10 (Skema Database), Bab 12 (Tech Stack), Bab 14 (Keamanan), Bab 21 (Risiko & Edge Cases).
- `docs/implementation-plan.md` — Rencana Implementasi & Roadmap Pengembangan.
