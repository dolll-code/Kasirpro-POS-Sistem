# Dokumentasi Skema Basis Data (Database Schema)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 05 — Selesai)** |
| Engine | **MySQL 8.0 / InnoDB** |
| Collation | `utf8mb4_unicode_ci` |
| Terakhir Ditinjau | 2026-11-18 |
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

---

## 5. Struktur Tabel Persediaan & Penomoran Dokumen (Tahap 04)

### 5.1 Tabel `document_sequences`
Mengelola penomoran dokumen transaksi secara aman (*atomic counter*) menggunakan *row-level locking* (`SELECT ... FOR UPDATE`). Menjamin tidak adanya nomor ganda (*no duplicate document numbers*) tanpa mengandalkan `MAX(id)+1`.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `document_type` | `VARCHAR(50)` | Tidak | Prefiks jenis dokumen (`TRX`, `PO`, `RCV`, `RET`, `EXP`, `SO`, `ADJ`) |
| `period_key` | `VARCHAR(20)` | Tidak | Kunci periode tanggal (`YYYYMMDD` zona Asia/Jakarta) |
| `last_number` | `BIGINT UNSIGNED` | Tidak | Nomor urut terakhir (default `0`) |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

**Constraint Unik:**
- `UNIQUE (document_type, period_key)` (`uq_doc_seq_type_period`)

---

### 5.2 Tabel `inventory_balances`
Tabel proyeksi saldo fisik dan persediaan barang (*projection cache*). Kuantitas persediaan terisolasi dari form produk dan hanya bergerak melalui mutasi persediaan resmi dalam transaksi basis data.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `product_id` | `BIGINT UNSIGNED` | Tidak | **UNIQUE**, Foreign Key -> `products.id` (**`ON DELETE RESTRICT`**) |
| `quantity_on_hand` | `DECIMAL(15,3)` | Tidak | Saldo fisik di rak toko (default `0.000`) |
| `quantity_reserved` | `DECIMAL(15,3)` | Tidak | Cadangan transaksi checkout transfer gantung (default `0.000`) |
| `average_cost` | `DECIMAL(15,2)` | Tidak | Harga Pokok Penjualan rata-rata bergerak (MAC, default `0.00`) |
| `version` | `BIGINT UNSIGNED` | Tidak | Nomor versi penguncian baris (default `1`) |
| `created_at` | `TIMESTAMP` | Ya | Waktu pencatatan |
| `updated_at` | `TIMESTAMP` | Ya | Waktu pembaruan |

**CHECK Constraints (MySQL 8.0+ / InnoDB):**
- `quantity_on_hand >= 0` (`chk_inv_on_hand_nonneg`)
- `quantity_reserved >= 0` (`chk_inv_reserved_nonneg`)
- `quantity_reserved <= quantity_on_hand` (`chk_inv_reserved_lte_on_hand`)
- `average_cost >= 0` (`chk_inv_avg_cost_nonneg`)

---

### 5.3 Tabel `stock_movements`
Buku besar (*audit ledger*) seluruh arus masuk dan keluar barang secara kekal (*immutable* dan *append-only*). Dilarang diubah atau dihapus melalui antarmuka pengguna maupun operasi model.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `product_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `products.id` (**`ON DELETE RESTRICT`**) |
| `movement_type` | `VARCHAR(50)` | Tidak | **INDEX** (`IN_PURCHASE`, `OUT_SALE`, `IN_RETURN_RESELLABLE`, `IN_INITIAL`, dll) |
| `source_type` | `VARCHAR(100)` | Tidak | Nama modul/sumber transaksi (`purchase_receipts`, `sales`, dll) |
| `source_id` | `BIGINT UNSIGNED` | Ya | ID dokumen transaksi sumber |
| `source_number` | `VARCHAR(100)` | Ya | Nomor dokumen transaksi (`RCV-...`, `TRX-...`, dll) |
| `quantity_in` | `DECIMAL(15,3)` | Tidak | Kuantitas masuk (default `0.000`, non-negatif) |
| `quantity_out` | `DECIMAL(15,3)` | Tidak | Kuantitas keluar (default `0.000`, non-negatif) |
| `balance_before`| `DECIMAL(15,3)` | Tidak | Saldo fisik sebelum mutasi terjadi |
| `balance_after` | `DECIMAL(15,3)` | Tidak | Saldo fisik setelah mutasi terjadi |
| `unit_cost` | `DECIMAL(15,2)` | Tidak | Biaya per unit saat mutasi (non-negatif) |
| `total_cost` | `DECIMAL(15,2)` | Tidak | Total nilai moneter mutasi (`qty * unit_cost`) |
| `notes` | `TEXT` | Ya | Keterangan / alasan mutasi persediaan |
| `created_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `occurred_at` | `TIMESTAMP` | Tidak | Waktu mutasi terjadi (zona `Asia/Jakarta`) |
| `created_at` | `TIMESTAMP` | Tidak | Waktu mutasi dicatat |

**Indeks:**
- `INDEX (product_id, occurred_at, id)` (`idx_stock_mov_prod_occurred`)
- `INDEX (source_type, source_id)` (`idx_stock_mov_source`)
- `INDEX (movement_type)` (`idx_stock_mov_type`)

**CHECK Constraints (MySQL 8.0+ / InnoDB):**
- `quantity_in >= 0` (`chk_mov_qty_in_nonneg`)
- `quantity_out >= 0` (`chk_mov_qty_out_nonneg`)
- `unit_cost >= 0` (`chk_mov_unit_cost_nonneg`)
- `total_cost >= 0` (`chk_mov_total_cost_nonneg`)

---

### 5.4 Tabel `product_cost_histories`
Pencatatan riwayat kronologis pembaruan HPP rata-rata bergerak (*Moving Weighted Average Cost audit trail*).

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `product_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `products.id` (**`ON DELETE RESTRICT`**) |
| `reference_type`| `VARCHAR(100)` | Tidak | Modul transaksi pembaru (`purchase_receipts`, `sales_returns`, `sales`, dll) |
| `reference_id` | `BIGINT UNSIGNED` | Ya | ID transaksi pembaru |
| `reference_number`| `VARCHAR(100)` | Ya | Nomor dokumen transaksi pembaru |
| `qty_before` | `DECIMAL(15,3)` | Tidak | Kuantitas persediaan sebelum perubahan |
| `cost_before` | `DECIMAL(15,2)` | Tidak | Rata-rata HPP sebelum perubahan |
| `qty_after` | `DECIMAL(15,3)` | Tidak | Kuantitas persediaan sesudah perubahan |
| `cost_after` | `DECIMAL(15,2)` | Tidak | Rata-rata HPP baru yang disahkan |
| `reason` | `VARCHAR(255)` | Ya | Alasan penyesuaian / kalkulasi HPP |
| `effective_at` | `TIMESTAMP` | Tidak | Tanggal dan waktu efektivitas HPP |
| `created_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `created_at` | `TIMESTAMP` | Tidak | Waktu rekaman dibuat |

**Indeks:**
- `INDEX (product_id, effective_at)` (`idx_cost_hist_prod_effective`)
- `INDEX (reference_type, reference_id)` (`idx_cost_hist_reference`)

**CHECK Constraints (MySQL 8.0+ / InnoDB):**
- `cost_before >= 0`, `cost_after >= 0`
- `qty_before >= 0`, `qty_after >= 0`

---

## 6. Struktur Tabel Manajemen Kas & Buku Besar Kas (Tahap 05)

### 6.1 Pembaruan Kolom Tabel `cash_accounts`
Pada Tahap 05, tabel `cash_accounts` diperkaya dengan kolom proyeksi saldo terdenormalisasi yang selalu sinkron dengan buku besar mutasi kas:

| Kolom Ditambahkan | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `current_balance` | `DECIMAL(15,2)` | Tidak | Default `0.00`, saldo kas saat ini |

**CHECK Constraint:**
- `current_balance >= 0` (`chk_cash_acc_balance_nonneg`): Menjamin di level database engine bahwa saldo akun kas/bank dilarang mengalami cerukan (*overdraft / negative balance*).

---

### 6.2 Tabel `cash_movements`
Buku besar mutasi kas yang bersifat kekal (*append-only ledger*) yang mencatat setiap pergerakan kas masuk (*inflow*) dan kas keluar (*outflow*) di seluruh sistem secara atomik dan kronologis.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `cash_account_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `cash_accounts.id` (**`ON DELETE RESTRICT`**) |
| `movement_type` | `VARCHAR(50)` | Tidak | Tipe transaksi kas (`OPENING_BALANCE`, `SALE_PAYMENT`, `SUPPLIER_PAYMENT`, `EXPENSE_PAYMENT`, `CASH_IN`, `CASH_OUT`, `TRANSFER_IN`, `TRANSFER_OUT`, `REVERSAL`) |
| `direction` | `ENUM('in', 'out')`| Tidak | Arah pergerakan uang (`in` = kas masuk, `out` = kas keluar) |
| `amount` | `DECIMAL(15,2)` | Tidak | Nilai nominal transaksi kas (wajib > 0) |
| `balance_before` | `DECIMAL(15,2)` | Tidak | Saldo kas sebelum mutasi |
| `balance_after` | `DECIMAL(15,2)` | Tidak | Saldo kas setelah mutasi disahkan |
| `description` | `VARCHAR(255)` | Ya | Keterangan deskriptif transaksi kas |
| `source_type` | `VARCHAR(100)` | Ya | Entitas sumber transaksi (`opening_balance`, `sale_payments`, `supplier_payments`, `expense_payments`, `reversal`, dll) |
| `source_id` | `BIGINT UNSIGNED` | Ya | ID primer pada entitas sumber transaksi |
| `source_number` | `VARCHAR(100)` | Ya | Nomor referensi dokumen sumber (misal: nomor faktur, nota, atau kuitansi) |
| `reversal_of_id` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `cash_movements.id` (**`ON DELETE RESTRICT`**), **UNIQUE** |
| `created_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**), penanggung jawab transaksi |
| `occurred_at` | `TIMESTAMP` | Tidak | Waktu mutasi terjadi (zona `Asia/Jakarta`) |
| `created_at` | `TIMESTAMP` | Tidak | Waktu mutasi dicatat ke dalam database |

**Indeks & Constraint Keunikan:**
- `UNIQUE (source_type, source_id)` (`uq_cash_mov_source`): Menjamin idempotensi transaksi bahwa satu dokumen pembayaran/penerimaan hanya dapat membuat tepat satu mutasi kas.
- `UNIQUE (reversal_of_id)` (`uq_cash_mov_reversal`): Menjamin bahwa satu mutasi kas hanya dapat dibalikkan (*reversal*) maksimal satu kali.
- `INDEX (cash_account_id, occurred_at, id)` (`idx_cash_mov_acc_occurred`): Optimasi query kronologis buku kas dan filter rentang tanggal.
- `INDEX (movement_type)` (`idx_cash_mov_type`): Optimasi filter laporan per jenis mutasi kas.
- `INDEX (direction)` (`idx_cash_mov_direction`): Optimasi agregasi total kas masuk vs keluar.

**CHECK Constraints (MySQL 8.0+ / InnoDB):**
- `amount > 0` (`chk_cash_mov_amount_pos`): Nilai mutasi kas wajib positif.
- `balance_before >= 0` (`chk_cash_mov_before_nonneg`): Saldo sebelum mutasi dilarang negatif.
- `balance_after >= 0` (`chk_cash_mov_after_nonneg`): Saldo sesudah mutasi dilarang negatif.

