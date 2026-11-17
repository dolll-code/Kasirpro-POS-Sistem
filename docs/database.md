# Dokumentasi Skema Basis Data (Database Schema)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 03 — Selesai)** |
| Engine | **MySQL 8.0 / InnoDB** |
| Collation | `utf8mb4_unicode_ci` |
| Terakhir Ditinjau | 2026-11-17 |
| Sumber Terkait | `prd.md` (§10), `database/migrations/*` |

---

## 1. Konvensi Basis Data
- **Engine:** Seluruh tabel menggunakan storage engine **InnoDB** untuk mendukung transaksi atomik (ACID), foreign key constraints, dan row-level locking.
- **Tipe Finansial & Stok:** Uang disimpan sebagai `DECIMAL(15,2)`, kuantitas persediaan sebagai `DECIMAL(15,3)`, dan persentase sebagai `DECIMAL(7,4)`.
- **Penghapusan Data Sensitif (No Hard Delete):** Master data dan transaksi yang telah direferensikan dilarang dihapus secara permanen (`ON DELETE RESTRICT`). Pemulihan atau penonaktifan dilakukan melalui atribut status boolean atau dokumen penyesuaian.

---

## 2. Struktur Tabel Identitas & Jejak Audit (Tahap 02)

### 2.1 Tabel `roles`
Menyimpan daftar peran pengguna toko.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `code` | `VARCHAR(50)` | Tidak | **UNIQUE** (`admin`, `supervisor`, `cashier`) |
| `name` | `VARCHAR(100)` | Tidak | Nama representasi peran |
| `description` | `TEXT` | Ya | Penjelasan kewenangan peran |
| `is_system` | `TINYINT(1)` | Tidak | Default `1` (peran baku sistem dilarang dihapus) |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

### 2.2 Tabel `permissions`
Menyimpan daftar hak akses granular per fungsi sistem.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `code` | `VARCHAR(100)` | Tidak | **UNIQUE** (contoh: `users.manage`, `sales.create`) |
| `name` | `VARCHAR(100)` | Tidak | Nama deskriptif izin |
| `module` | `VARCHAR(50)` | Tidak | **INDEX** (pengelompokan modul: `sales`, `users`, dll) |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

### 2.3 Tabel `role_permissions`
Relasi banyak-ke-banyak antara peran dan izin akses.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `role_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `roles.id` (`ON DELETE CASCADE`) |
| `permission_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `permissions.id` (`ON DELETE CASCADE`) |
| **Primary Key** | `(role_id, permission_id)` | | Composite Primary Key |

### 2.4 Tabel `users`
Menyimpan data staf dan kredensial login terautentikasi.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `role_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `roles.id` (**`ON DELETE RESTRICT`**) |
| `name` | `VARCHAR(150)` | Tidak | Nama lengkap pengguna |
| `email` | `VARCHAR(150)` | Tidak | **UNIQUE**, email login |
| `password` | `VARCHAR(255)` | Tidak | Kata sandi terenkripsi (Bcrypt) |
| `is_active` | `TINYINT(1)` | Tidak | **INDEX**, default `1` (status aktif/nonaktif) |
| `last_login_at` | `TIMESTAMP` | Ya | Waktu terakhir berhasil login |
| `remember_token`| `VARCHAR(100)` | Ya | Token sesi ingat saya |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

### 2.5 Tabel `audit_logs`
Buku besar jejak audit aktivitas sensitif toko secara *append-only*.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `user_id` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE SET NULL`**) |
| `action` | `VARCHAR(100)` | Tidak | **INDEX** (contoh: `auth.login`, `user.updated`) |
| `module` | `VARCHAR(50)` | Tidak | **INDEX** (kategori modul: `auth`, `user_management`) |
| `reference_type`| `VARCHAR(100)` | Ya | Nama model referensi (`App\Models\User`, dll) |
| `reference_id` | `VARCHAR(100)` | Ya | ID unik record yang diubah |
| `old_values` | `JSON` | Ya | Salinan nilai lama (tersanitasi tanpa secret) |
| `new_values` | `JSON` | Ya | Salinan nilai baru (tersanitasi tanpa secret) |
| `ip_address` | `VARCHAR(45)` | Ya | Alamat IP pemanggil |
| `user_agent` | `TEXT` | Ya | User agent peramban / perangkat |
| `correlation_id`| `VARCHAR(100)` | Ya | **INDEX**, UUID pelacakan request (`X-Request-Id`) |
| `created_at` | `TIMESTAMP` | Tidak | **INDEX**, default `CURRENT_TIMESTAMP` |

**Indeks Majemuk:**
- `INDEX (user_id, created_at)`
- `INDEX (module, created_at)`
- `INDEX (reference_type, reference_id)`

---

## 3. Skema Pendukung Framework (Bawaan Laravel)
- **`sessions`:** Penyimpanan sesi pengguna (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`).
- **`cache` & `cache_locks`:** Penyimpanan cache data database.
- **`jobs`, `job_batches`, `failed_jobs`:** Antrean eksekusi latar belakang untuk ekspor laporan besar.

---

## 4. Struktur Tabel Master Data & Katalog (Tahap 03)

### 4.1 Tabel `categories`
Menyimpan kelompok kategori produk toko.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `name` | `VARCHAR(100)` | Tidak | **UNIQUE**, nama kategori |
| `slug` | `VARCHAR(100)` | Tidak | **UNIQUE**, slug URL |
| `description` | `TEXT` | Ya | Keterangan kelompok produk |
| `is_active` | `TINYINT(1)` | Tidak | **INDEX**, default `1` |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

### 4.2 Tabel `units`
Menyimpan satuan unit hitung dan batas presisi desimal kuantitas pecahan.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `name` | `VARCHAR(50)` | Tidak | **UNIQUE**, nama satuan (Kilogram, Pieces) |
| `symbol` | `VARCHAR(20)` | Tidak | **UNIQUE**, simbol (kg, pcs, ltr) |
| `decimal_precision` | `TINYINT UNSIGNED` | Tidak | Batas desimal (0–3 digit, default `0`) |
| `is_active` | `TINYINT(1)` | Tidak | **INDEX**, default `1` |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

### 4.3 Tabel `products`
Menyimpan katalog master barang dagangan.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `category_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `categories.id` (**`ON DELETE RESTRICT`**) |
| `unit_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `units.id` (**`ON DELETE RESTRICT`**) |
| `sku` | `VARCHAR(50)` | Tidak | **UNIQUE**, kode SKU ter-normalisasi (kapital) |
| `barcode` | `VARCHAR(100)` | Ya | **UNIQUE**, barcode scanner (leading zero dipertahankan) |
| `name` | `VARCHAR(255)` | Tidak | **INDEX**, nama lengkap barang |
| `description` | `TEXT` | Ya | Keterangan produk |
| `selling_price` | `DECIMAL(15,2)` | Tidak | Harga jual resmi kasir (non-negatif) |
| `minimum_stock` | `DECIMAL(15,3)` | Tidak | Batas minimum peringatan stok menipis |
| `is_active` | `TINYINT(1)` | Tidak | **INDEX**, default `1` |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

**Indeks:**
- `UNIQUE (sku)`
- `UNIQUE (barcode)` (mengizinkan multiple null)
- `INDEX (name)`
- `INDEX (category_id, is_active)`
- `INDEX (is_active)`

*Catatan Integritas Persediaan:* Tabel `products` secara ketat **tidak** memiliki kolom `on_hand_stock` atau `average_cost`. Kuantitas persediaan fisik dan HPP rata-rata bergerak disimpan dan dikelola pada buku besar persediaan (`inventory_balances` & `stock_movements`) pada Tahap 04 & 05.

### 4.4 Tabel `product_price_histories`
Jejak audit otomatis perubahan harga jual produk.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `product_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `products.id` (`ON DELETE CASCADE`) |
| `old_price` | `DECIMAL(15,2)` | Tidak | Harga jual sebelum diubah |
| `new_price` | `DECIMAL(15,2)` | Tidak | Harga jual baru |
| `changed_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `effective_at` | `TIMESTAMP` | Tidak | Waktu perubahan mulai berlaku |
| `reason` | `VARCHAR(255)` | Ya | Alasan perubahan harga |
| `created_at` | `TIMESTAMP` | Tidak | Waktu pencatatan |

**Indeks:**
- `INDEX (product_id, effective_at)`

### 4.5 Tabel `suppliers`
Menyimpan data rekanan distributor dan pemasok barang dagangan.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `supplier_code` | `VARCHAR(50)` | Tidak | **UNIQUE**, kode pemasok |
| `name` | `VARCHAR(255)` | Tidak | **INDEX**, nama perusahaan pemasok |
| `contact_person` | `VARCHAR(100)` | Ya | Nama kontak PIC |
| `phone` | `VARCHAR(50)` | Ya | Nomor telepon / WhatsApp |
| `email` | `VARCHAR(100)` | Ya | Alamat email resmi |
| `address` | `TEXT` | Ya | Alamat gudang / kantor |
| `notes` | `TEXT` | Ya | Catatan tambahan / termin |
| `is_active` | `TINYINT(1)` | Tidak | **INDEX**, default `1` |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

### 4.6 Tabel `expense_categories`
Menyimpan klasifikasi pos beban operasional toko.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `name` | `VARCHAR(100)` | Tidak | **UNIQUE**, nama kategori pengeluaran |
| `description` | `TEXT` | Ya | Keterangan pos beban |
| `is_active` | `TINYINT(1)` | Tidak | **INDEX**, default `1` |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

### 4.7 Tabel `cash_accounts`
Menyimpan master buku kas fisik dan rekening bank operasional.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `code` | `VARCHAR(50)` | Tidak | **UNIQUE**, kode akun |
| `name` | `VARCHAR(100)` | Tidak | Nama akun kas / bank |
| `account_type` | `ENUM('cash','bank')`| Tidak | **INDEX**, tipe akun keuangan |
| `opening_balance` | `DECIMAL(15,2)` | Tidak | Saldo pembukaan awal (non-negatif) |
| `opening_balance_at`| `TIMESTAMP` | Ya | Tanggal penetapan saldo pembukaan |
| `is_active` | `TINYINT(1)` | Tidak | **INDEX**, default `1` |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

### 4.8 Tabel `store_profiles`
Profil toko tunggal aktif untuk snapshot nota transaksi dan konfigurasi printer thermal.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `name` | `VARCHAR(255)` | Tidak | Nama resmi toko |
| `address` | `TEXT` | Ya | Alamat lengkap toko |
| `phone` | `VARCHAR(50)` | Ya | Nomor telepon |
| `email` | `VARCHAR(100)` | Ya | Email usaha |
| `tax_id` | `VARCHAR(50)` | Ya | NPWP |
| `receipt_header` | `TEXT` | Ya | Teks pembuka nota belanja |
| `receipt_footer` | `TEXT` | Ya | Teks penutup nota belanja |
| `receipt_paper_width` | `ENUM('58','80')`| Tidak | Lebar kertas thermal kasir (default `'58'`) |
| `logo_path` | `VARCHAR(255)` | Ya | Lokasi berkas gambar logo |
| `is_active` | `TINYINT(1)` | Tidak | Default `1` |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |
