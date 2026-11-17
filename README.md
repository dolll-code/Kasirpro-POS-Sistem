# KasirPro — Web-Based POS & Retail Management System

KasirPro adalah aplikasi web Point of Sale (POS) dan manajemen operasional toko retail, minimarket, serta usaha dagang kecil. Sistem ini dirancang sebagai *Modular Monolith* berbasis Laravel 12 dengan penegakan integritas data ketat, kalkulasi HPP *Moving Weighted Average*, buku besar mutasi persediaan dan kas *append-only*, serta prinsip *server-authoritative pricing*.

---

## 1. Persyaratan Sistem (System Requirements)

| Komponen | Spesifikasi Minimal | Rekomendasi Produksi (Production Gate) |
|---|---|---|
| **PHP** | PHP 8.2.29+ | PHP 8.4 (patch terbaru aktif) |
| **Ekstensi PHP Wajib** | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `iconv`, `intl`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `PDO`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip` | Sesuai daftar minimal |
| **Database Server** | MySQL 8.0.30+ (Storage Engine: **InnoDB**) | MySQL 8.4 LTS (Oracle Long Term Support) |
| **Package Manager** | Composer v2.2+ | Composer v2.9+ |
| **JavaScript Runtime** | Node.js v20+ / npm v10+ | Node.js v24+ / npm v12+ |
| **Peramban & Scanner** | Google Chrome / Microsoft Edge terkini, USB Barcode Scanner (HID Keyboard Wedge) | Monitor Desktop / Tablet Landscape |

---

## 2. Instalasi Cepat (Quick Start)

### 2.1 Kloning & Pemasangan Dependensi
```bash
# Masuk ke direktori proyek
cd kasirpro

# Pasang dependensi PHP melalui Composer
composer install

# Verifikasi kesiapan ekstensi platform
composer check-platform-reqs

# Pasang dependensi frontend (Tailwind CSS, Alpine.js, Vite)
npm install
```

### 2.2 Konfigurasi Environment
Salin template konfigurasi dan generate application encryption key:
```bash
# Salin berkas konfigurasi
cp .env.example .env

# Generate APP_KEY
php artisan key:generate
```

Pastikan variabel penting pada `.env` telah disesuaikan:
```dotenv
APP_NAME=KasirPro
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasirpro
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=private
```

---

## 3. Database Setup & Migrasi

Pastikan database server MySQL telah berjalan di komputer lokal atau server Anda.

```bash
# Buat database utama dan database testing di MySQL
mysql -u root -e "CREATE DATABASE IF NOT EXISTS kasirpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS kasirpro_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Jalankan migrasi database bawaan
php artisan migrate
```

---

## 4. Menjalankan Aplikasi & Pembangunan Aset

### 4.1 Mode Pengembangan (Development Mode)
Jalankan server Laravel backend dan Vite dev server secara bersamaan:

```bash
# Terminal 1: Laravel Development Server
php artisan serve

# Terminal 2: Vite Hot Module Replacement (HMR)
npm run dev
```
Akses aplikasi melalui peramban web di: `http://localhost:8000`.

### 4.2 Pembangunan Aset Produksi (Production Build)
Untuk mengompilasi berkas CSS (Tailwind CSS) dan JavaScript (Alpine.js) untuk lingkungan pengujian atau produksi:
```bash
npm run build
```

---

## 5. Pengujian Kualitas & Formatting Kode

### 5.1 Menjalankan Automated Testing (Pest v3)
Seluruh pengujian unit dan fitur dijalankan menggunakan framework **Pest**:
```bash
# Menjalankan seluruh test suite
php artisan test

# Menjalankan pengujian dengan filter tertentu
php artisan test --filter=DecimalTest
```

### 5.2 Pemeriksaan Format Kode (Laravel Pint)
Proyek ini mengadopsi standar penulisan kode resmi Laravel menggunakan **Pint**:
```bash
# Memeriksa kepatuhan formatting (tanpa mengubah berkas)
php ./vendor/bin/pint --test

# Mengoreksi formatting secara otomatis
php ./vendor/bin/pint
```

---

## 6. Dokumentasi Proyek & Spesifikasi Kebutuhan

- [prd.md](file:///C:/laragon/www/kasirpro/prd.md) — Product Requirements Document (PRD v1.0.0, sumber kebenaran produk).
- [docs/implementation-plan.md](file:///C:/laragon/www/kasirpro/docs/implementation-plan.md) — Rencana pentahapan implementasi teknis (Roadmap Tahap 00–11).
- [docs/decisions/0000-project-baseline.md](file:///C:/laragon/www/kasirpro/docs/decisions/0000-project-baseline.md) — Rekaman Keputusan Arsitektur Dasar (ADR 0000).

---

## 7. Status Tahapan Pengembangan

- [x] **Tahap 00:** Pemeriksaan Awal Repositori & Rencana Dasar (Selesai).
- [x] **Tahap 01:** Setup Proyek, Kerangka Arsitektur & Shared UI Foundation (Selesai).
- [ ] **Tahap 02:** Autentikasi, Hak Akses (RBAC: Admin, Supervisor, Kasir) & Audit Trail.
- [ ] **Tahap 03:** Master Data & Katalog Produk.
- [ ] **Tahap 04:** Pengadaan Barang (PO), Penerimaan & Kalkulasi HPP Moving Average.
- [ ] **Tahap 05:** Saldo Persediaan, Kartu Stok & Barcode Scanner Lookup.
- [ ] **Tahap 06:** Layar Kasir (POS), Diskon, Checkout Tunai & Transfer Manual.
- [ ] **Tahap 07:** Retur Penjualan, Pemisahan Fisik Barang & Pengembalian Dana (Refund).
- [ ] **Tahap 08:** Biaya Operasional Toko & Buku Kas Riil.
- [ ] **Tahap 09:** Stok Opname & Penguncian Produk.
- [ ] **Tahap 10:** Laporan Operasional, Ekspor PDF/XLSX & Kesiapan Produksi.
- [ ] **Tahap 11:** Fitur Lanjutan P1 & Regression Testing.

---

## Lisensi
Proyek KasirPro dikembangkan khusus di bawah lisensi privat / komersial internal sesuai ketentuan pemilik produk.
