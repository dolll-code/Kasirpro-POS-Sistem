# Dokumentasi Skema Basis Data (Database Schema)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 02 — Selesai)** |
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
