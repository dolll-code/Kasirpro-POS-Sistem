# Dokumentasi Skema Basis Data (Database Schema)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 08 — Selesai)** |
| Engine | **MySQL 8.0 / InnoDB** |
| Collation | `utf8mb4_unicode_ci` |
| Terakhir Ditinjau | 2026-11-19 |
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

---

## 7. Struktur Tabel Pengadaan, Penerimaan & Pembayaran Supplier (Tahap 06)

### 7.1 Tabel `purchases`
Menyimpan dokumen Purchase Order (PO) pembelian barang dagangan ke pemasok dengan pemisahan status pesanan (*order status*) dan status pelunasan (*payment status*).

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `purchase_number` | `VARCHAR(100)` | Tidak | **UNIQUE** (`PO-YYYYMMDD-XXXXX`) |
| `supplier_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `suppliers.id` (**`ON DELETE RESTRICT`**) |
| `order_date` | `DATE` | Tidak | Tanggal pesanan pembelian dibuat |
| `expected_date` | `DATE` | Ya | Estimasi tanggal kedatangan barang |
| `subtotal` | `DECIMAL(15,2)` | Tidak | Default `0.00`, total nilai kotor item |
| `item_discount_total` | `DECIMAL(15,2)` | Tidak | Default `0.00`, akumulasi diskon per baris item |
| `document_discount_total` | `DECIMAL(15,2)`| Tidak | Default `0.00`, diskon header dokumen PO |
| `additional_cost_estimate`| `DECIMAL(15,2)`| Tidak | Default `0.00`, estimasi ongkir/asuransi saat PO dibuat |
| `payable_total` | `DECIMAL(15,2)` | Tidak | Default `0.00`, estimasi tagihan akhir PO |
| `paid_amount` | `DECIMAL(15,2)` | Tidak | Default `0.00`, total pembayaran yang sudah diposting |
| `status` | `VARCHAR(50)` | Tidak | Default `draft`, index (`draft`, `ordered`, `partially_received`, `received`, `cancelled`) |
| `payment_status` | `VARCHAR(50)` | Tidak | Default `unpaid`, index (`unpaid`, `partially_paid`, `paid`) |
| `notes` | `TEXT` | Ya | Catatan pemesanan |
| `created_by` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `confirmed_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `confirmed_at` | `TIMESTAMP` | Ya | Waktu konfirmasi dokumen PO |
| `cancelled_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `cancelled_at` | `TIMESTAMP` | Ya | Waktu pembatalan / penutupan sisa PO |
| `cancel_reason` | `VARCHAR(255)` | Ya | Alasan wajib pembatalan / penutupan sisa PO |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Waktu pencatatan & pembaruan |

**Indeks:**
- `UNIQUE (purchase_number)`
- `INDEX (supplier_id, order_date, status)` (`idx_purchases_supp_date_status`)
- `INDEX (status)`
- `INDEX (payment_status)`

**CHECK Constraints (MySQL 8.0+ / InnoDB):**
- `subtotal >= 0`, `item_discount_total >= 0`, `document_discount_total >= 0`, `payable_total >= 0`, `paid_amount >= 0`

---

### 7.2 Tabel `purchase_items`
Rincian baris item barang pada dokumen Purchase Order.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `purchase_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `purchases.id` (**`ON DELETE CASCADE`** saat draft) |
| `product_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `products.id` (**`ON DELETE RESTRICT`**) |
| `product_name_snapshot` | `VARCHAR(255)` | Tidak | Snapshot nama produk saat PO dibuat |
| `sku_snapshot` | `VARCHAR(100)` | Tidak | Snapshot SKU produk |
| `unit_snapshot` | `VARCHAR(50)` | Tidak | Snapshot simbol satuan produk |
| `ordered_qty` | `DECIMAL(15,3)` | Tidak | Kuantitas pemesanan (wajib > 0) |
| `unit_cost` | `DECIMAL(15,2)` | Tidak | Harga beli satuan (wajib >= 0) |
| `line_discount` | `DECIMAL(15,2)` | Tidak | Default `0.00`, diskon khusus baris barang |
| `allocated_document_discount` | `DECIMAL(15,2)` | Tidak | Default `0.00`, alokasi diskon header secara proporsional |
| `accepted_qty_total` | `DECIMAL(15,3)` | Tidak | Default `0.000`, akumulasi fisik disetujui masuk stok |
| `rejected_qty_total` | `DECIMAL(15,3)` | Tidak | Default `0.000`, akumulasi fisik ditolak |
| `cancelled_qty` | `DECIMAL(15,3)` | Tidak | Default `0.000`, kuantitas sisa yang dibatalkan |
| `line_total` | `DECIMAL(15,2)` | Tidak | Nilai bersih baris item setelah diskon |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**CHECK Constraints:**
- `ordered_qty > 0`
- `unit_cost >= 0`, `line_discount >= 0`, `allocated_document_discount >= 0`, `accepted_qty_total >= 0`, `rejected_qty_total >= 0`, `cancelled_qty >= 0`, `line_total >= 0`

---

### 7.3 Tabel `purchase_receipts`
Dokumen tanda terima barang fisik (*Goods Receipt*) yang bersifat kekal (*append-only*).

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `receipt_number` | `VARCHAR(100)` | Tidak | **UNIQUE** (`RCV-YYYYMMDD-XXXXX`) |
| `purchase_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `purchases.id` (**`ON DELETE RESTRICT`**) |
| `received_at` | `TIMESTAMP` | Tidak | Waktu kedatangan barang fisik |
| `capitalizable_additional_cost` | `DECIMAL(15,2)` | Tidak | Default `0.00`, ongkir/asuransi yang dikapitalisasi ke HPP |
| `status` | `VARCHAR(50)` | Tidak | Default `posted` |
| `notes` | `TEXT` | Ya | Catatan surat jalan / pengiriman |
| `received_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `posted_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `posted_at` | `TIMESTAMP` | Ya | Waktu posting mutasi stok dan HPP |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**CHECK Constraints:**
- `capitalizable_additional_cost >= 0`

---

### 7.4 Tabel `purchase_receipt_items`
Rincian kuantitas fisik, disetujui, ditolak, dan HPP landed per baris penerimaan barang.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `receipt_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `purchase_receipts.id` (**`ON DELETE CASCADE`**) |
| `purchase_item_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `purchase_items.id` (**`ON DELETE RESTRICT`**) |
| `product_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `products.id` (**`ON DELETE RESTRICT`**) |
| `physically_received_qty` | `DECIMAL(15,3)` | Tidak | Kuantitas fisik yang tiba di toko |
| `accepted_qty` | `DECIMAL(15,3)` | Tidak | Kuantitas disetujui (menambah stok fisik toko) |
| `rejected_qty` | `DECIMAL(15,3)` | Tidak | Default `0.000`, kuantitas ditolak (tidak menambah stok) |
| `rejection_reason` | `VARCHAR(255)` | Ya | Alasan barang ditolak (rusak/pecah/sobek) |
| `landed_unit_cost` | `DECIMAL(15,2)` | Tidak | HPP perolehan per unit setelah alokasi diskon & ongkir |
| `landed_total_cost` | `DECIMAL(15,2)` | Tidak | Total nilai persediaan yang masuk (`accepted_qty * landed_unit_cost`) |
| `notes` | `VARCHAR(255)` | Ya | Catatan per item |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**CHECK Constraints:**
- `physically_received_qty = (accepted_qty + rejected_qty)` (`chk_rcv_item_phys_sum`)
- `accepted_qty >= 0`, `rejected_qty >= 0`, `landed_unit_cost >= 0`, `landed_total_cost >= 0`

---

### 7.5 Tabel `supplier_payments`
Catatan pengeluaran kas/bank untuk pembayaran utang dagang pemasok atas barang diterima (*accepted*).

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `payment_number` | `VARCHAR(100)` | Tidak | **UNIQUE** (`PAY-YYYYMMDD-XXXXX`) |
| `purchase_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `purchases.id` (**`ON DELETE RESTRICT`**) |
| `cash_account_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `cash_accounts.id` (**`ON DELETE RESTRICT`**) |
| `method` | `VARCHAR(50)` | Tidak | Metode pembayaran (`cash`, `bank_transfer`) |
| `amount` | `DECIMAL(15,2)` | Tidak | Nominal pembayaran kas (wajib > 0) |
| `reference_number` | `VARCHAR(100)` | Ya | Nomor bukti transfer / kuitansi |
| `paid_at` | `TIMESTAMP` | Tidak | Waktu pembayaran disahkan |
| `notes` | `TEXT` | Ya | Catatan pembayaran |
| `cash_movement_id` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `cash_movements.id` (**`UNIQUE`**, **`ON DELETE RESTRICT`**) |
| `created_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `reversed_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `reversal_reason` | `VARCHAR(255)` | Ya | Alasan pembalikan pembayaran |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**CHECK Constraints:**
- `amount > 0` (`chk_supp_pay_amount_pos`)

---

## 8. Struktur Tabel Penjualan, Item, Pembayaran Kasir & Reservasi Stok (Tahap 08)

### 8.1 Tabel `sales`
Dokumen utama transaksi penjualan kasir (POS) yang mencatat total belanja, diskon, status pembayaran, dan snapshot identitas toko.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `transaction_number` | `VARCHAR(100)` | Tidak | **UNIQUE** (`TRX-YYYYMMDD-XXXXX`) |
| `cashier_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `sale_status` | `VARCHAR(50)` | Tidak | Default `completed` (`pending_payment`, `completed`, `cancelled`, `partially_returned`, `fully_returned`) |
| `payment_status` | `VARCHAR(50)` | Tidak | Default `paid` (`pending_verification`, `paid`, `refund_due`, `refunded`, `rejected`) |
| `gross_total` | `DECIMAL(15,2)` | Tidak | Total kotor sebelum diskon |
| `item_discount_total` | `DECIMAL(15,2)` | Tidak | Total potongan diskon level baris item |
| `transaction_discount_total` | `DECIMAL(15,2)` | Tidak | Total potongan diskon level dokumen transaksi |
| `grand_total` | `DECIMAL(15,2)` | Tidak | Nilai bersih akhir tagihan transaksi |
| `store_snapshot` | `JSON` | Tidak | Salinan permanen nama toko, alamat, telepon, footer & lebar struk saat transaksi |
| `idempotency_key` | `VARCHAR(64)` | Tidak | **UNIQUE**, kunci pencegah transaksi checkout ganda |
| `payload_hash` | `VARCHAR(64)` | Tidak | Hash SHA-256 parameter pesanan untuk deteksi payload conflict |
| `discount_approved_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE SET NULL`**), supervisor otorisator diskon khusus |
| `discount_override_reason` | `VARCHAR(255)` | Ya | Alasan pemberian diskon khusus supervisor |
| `completed_at` | `TIMESTAMP` | Ya | **INDEX**, waktu penyelesaian dan pengakuan penjualan |
| `pending_expires_at` | `TIMESTAMP` | Ya | Batas waktu reservasi transfer manual (TTL default 15 menit, configurable) |
| `cancelled_at` | `TIMESTAMP` | Ya | Waktu pembatalan pesanan |
| `cancellation_reason` | `VARCHAR(255)` | Ya | Alasan pembatalan transaksi |
| `notes` | `TEXT` | Ya | Catatan kasir |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**Indeks:**
- `INDEX (sale_status, completed_at)`
- `INDEX (cashier_id, completed_at)`
- `INDEX (pending_expires_at)` untuk scheduler expiry

**CHECK Constraints:**
- `gross_total >= 0`, `item_discount_total >= 0`, `transaction_discount_total >= 0`, `grand_total >= 0`

---

### 8.2 Tabel `sale_items`
Rincian setiap item barang yang terjual, mencakup harga jual, kuantitas, alokasi diskon, dan snapshot HPP modal persediaan.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `sale_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `sales.id` (**`ON DELETE CASCADE`**) |
| `product_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `products.id` (**`ON DELETE RESTRICT`**) |
| `product_name_snapshot` | `VARCHAR(255)` | Tidak | Nama produk saat transaksi disahkan |
| `sku_snapshot` | `VARCHAR(100)` | Tidak | SKU produk saat transaksi |
| `barcode_snapshot` | `VARCHAR(100)` | Ya | Barcode produk saat transaksi |
| `unit_name_snapshot` | `VARCHAR(50)` | Tidak | Simbol satuan kuantitas saat transaksi |
| `quantity` | `DECIMAL(15,3)` | Tidak | Kuantitas barang yang terjual (wajib > 0) |
| `unit_price_snapshot` | `DECIMAL(15,2)` | Tidak | Harga jual per unit resmi dari server |
| `gross_line_total` | `DECIMAL(15,2)` | Tidak | Nilai kotor baris (`quantity * unit_price_snapshot`) |
| `line_discount` | `DECIMAL(15,2)` | Tidak | Default `0.00`, potongan diskon langsung pada baris item |
| `allocated_transaction_discount` | `DECIMAL(15,2)` | Tidak | Default `0.00`, bagian diskon transaksi header yang dialokasikan ke baris ini |
| `net_line_total` | `DECIMAL(15,2)` | Tidak | Nilai bersih baris (`gross_line_total - line_discount - allocated_transaction_discount`) |
| `cost_price_snapshot` | `DECIMAL(15,2)` | Tidak | Nilai HPP modal rata-rata per unit saat transaksi |
| `cost_total_snapshot` | `DECIMAL(15,2)` | Tidak | Total HPP belanjaan barang (`quantity * cost_price_snapshot`) |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**CHECK Constraints:**
- `quantity > 0`
- `unit_price_snapshot >= 0`, `gross_line_total >= 0`, `line_discount >= 0`, `allocated_transaction_discount >= 0`, `net_line_total >= 0`, `cost_price_snapshot >= 0`, `cost_total_snapshot >= 0`

---

### 8.3 Tabel `sale_payments`
Catatan pembayaran kasir yang menghubungkan transaksi penjualan dengan akun kas penerima uang dan pergerakan kas buku besar.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `sale_id` | `BIGINT UNSIGNED` | Tidak | **UNIQUE**, Foreign Key -> `sales.id` (**`ON DELETE RESTRICT`**) |
| `payment_method` | `VARCHAR(50)` | Tidak | Metode pembayaran (`cash`, `bank_transfer`) |
| `cash_account_id` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `cash_accounts.id` (**`ON DELETE RESTRICT`**) |
| `amount` | `DECIMAL(15,2)` | Tidak | Nominal pembayaran bersih yang diakui masuk kas (harus sama dengan `grand_total`) |
| `cash_tendered` | `DECIMAL(15,2)` | Ya | Uang tunai yang diserahkan oleh pelanggan di kasir |
| `change_amount` | `DECIMAL(15,2)` | Ya | Uang kembalian yang diserahkan kembali ke pelanggan |
| `reference_number` | `VARCHAR(100)` | Ya | Nomor referensi pembayaran atau kuitansi manual |
| `verification_status` | `VARCHAR(50)` | Tidak | Default `verified` (`pending`, `verified`, `rejected`, `expired`) |
| `verification_note` | `TEXT` | Ya | Catatan verifikasi atau alasan penolakan/kedaluwarsa |
| `verified_by` | `BIGINT UNSIGNED` | Ya | Foreign Key -> `users.id` (**`ON DELETE RESTRICT`**) |
| `verified_at` | `TIMESTAMP` | Ya | Waktu pengesahan verifikasi pembayaran |
| `paid_at` | `TIMESTAMP` | Ya | Waktu diterimanya pembayaran secara nyata |
| `cash_movement_id` | `BIGINT UNSIGNED` | Ya | **UNIQUE**, Foreign Key -> `cash_movements.id` (**`ON DELETE RESTRICT`**) |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**CHECK Constraints:**
- `amount >= 0`
- `cash_tendered >= amount` (untuk pembayaran tunai)
- `change_amount = (cash_tendered - amount)`

---

### 8.4 Tabel `stock_reservations`
Header reservasi stok untuk sale transfer bank berstatus `pending_payment`. Satu transaksi memiliki maksimal satu reservasi. Reservasi aktif menahan kuantitas tersedia tetapi tidak memotong kuantitas fisik.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `sale_id` | `BIGINT UNSIGNED` | Tidak | **UNIQUE**, Foreign Key -> `sales.id` (`ON DELETE RESTRICT`) |
| `status` | `VARCHAR(30)` | Tidak | Default `active`; state `active`, `released`, `consumed`, `expired` |
| `expires_at` | `TIMESTAMP` | Tidak | Batas TTL; default 15 menit dari checkout (`POS_TRANSFER_RESERVATION_TTL_MINUTES`) |
| `released_at` | `TIMESTAMP` | Ya | Waktu reservasi dilepas, dikonsumsi, atau kedaluwarsa |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**Indeks:**
- `INDEX (status, expires_at)` untuk scheduler
- `INDEX (expires_at)`

---

### 8.5 Tabel `stock_reservation_items`
Rincian kuantitas yang ditahan per produk untuk satu reservasi.

| Kolom | Tipe Data | Nullable | Keterangan / Constraint |
|---|---|:---:|---|
| `id` | `BIGINT UNSIGNED` | Tidak | Primary Key, Auto Increment |
| `reservation_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `stock_reservations.id` (`ON DELETE CASCADE`) |
| `product_id` | `BIGINT UNSIGNED` | Tidak | Foreign Key -> `products.id` (`ON DELETE RESTRICT`) |
| `quantity` | `DECIMAL(15,3)` | Tidak | Kuantitas yang ditahan, harus `> 0` |
| `created_at` / `updated_at` | `TIMESTAMP` | Ya | Timestamps |

**Constraint/indeks:**
- `UNIQUE (reservation_id, product_id)` mencegah detail produk duplikat per reservasi
- `CHECK (quantity > 0)`

### 8.6 Siklus status & invariants transfer
- Checkout transfer membuat `sales.sale_status = pending_payment`, `sales.payment_status = pending_verification`, dan `sale_payments.verification_status = pending`.
- Sebelum verifikasi: `inventory_balances.quantity_on_hand` tidak berubah; `quantity_reserved` bertambah; belum ada stock-out atau cash-in movement.
- Verifikasi sukses mengonsumsi reservation dan mem-post sale/payment, stock-out, cash-in serta audit dalam satu transaksi InnoDB.
- Reject/cancel/expiry mengurangi `quantity_reserved`, tidak mengubah on-hand, dan tidak membuat cash movement.
- Scheduler `sales:expire-reservations` memproses reservasi aktif dengan `expires_at <= now()`; state sale/payment menjadi `cancelled`/`expired`.

## 9. Sales returns dan refund

### 9.1 `sales_returns`

Header retur, approval, pengakuan revenue, dan status refund. `approved_at` adalah tanggal pengakuan P&L; `completed_at`/`return_payments.paid_at` menandai payout aktual.

| Kolom utama | Keterangan |
|---|---|
| `return_number` | Nomor dokumen `RET-YYYYMMDD-xxxxx`, unique |
| `sale_id`, `requested_by`, `approved_by` | Sale asal dan identitas maker/checker |
| `status` | `pending_approval`, `rejected`, `approved`, `completed` |
| `refund_status` | `due` atau `paid`, independen dari sale status |
| `reason`, `notes`, `approval_note`, `admin_override_reason` | Alasan dan catatan audit |
| `return_total` | Nilai refund penuh dari snapshot item penjualan |
| `requested_at`, `approved_at`, `rejected_at`, `completed_at` | Timestamp lifecycle retur |

### 9.2 `sales_return_items`

Menyimpan condition, quantity, `selling_price_snapshot`, diskon item, alokasi diskon transaksi, net unit price, `cost_price_snapshot`, `return_value`, `cost_value`, dan `damaged_loss`. Snapshot tidak membaca harga/HPP master. Aggregate pending/approved/completed mengunci kuantitas returnable; rejected melepas kuantitas.

### 9.3 `return_payments`

Unique satu payout penuh per return melalui unique `sales_return_id` dan `cash_movement_id`. Cash movement `OUT_RETURN_REFUND` memakai source `return_payment`; `paid_at` adalah tanggal cash flow.

Approval resellable membuat `IN_RETURN_RESELLABLE` dengan cost historis dan moving average recalculation. Damaged return tidak menambah stok; cost dicatat sebagai indikator loss dan tidak mengurangi net COGS lagi.

## 10. Operating expenses

### 10.1 `expenses`

Expense number berformat `EXP-YYYYMMDD-xxxxx`. Status dokumen `draft`, `posted`, `voided`; `payment_status` dihitung terpisah (`unpaid`, `partially_paid`, `paid`). `expense_date` menjadi recognition date P&L; `posted_at` hanya audit timestamp. `is_landed_cost`/`landed_cost_reference` mencegah biaya receipt inventory dicatat ulang sebagai operating expense.

Void menyimpan `void_reason`, actor/time, `void_cash_refund_amount` aktual, dan `void_cash_unrecovered_amount`. Expense reversal recognition memakai `voided_at`; uang yang belum kembali tidak menjadi cash inflow.

### 10.2 `expense_payments` dan `expense_void_reversals`

Payment menyimpan expense, akun kas, `cash_movement_id`, nominal, `paid_at`, pembuat, unique `idempotency_key`, dan `payload_hash`. Cash movement `EXPENSE_PAYMENT` dibuat saat pembayaran aktual. Void reversal menautkan payment/movement asal ke cash movement reversal, jumlah aktual, reason, dan actor. Movement asal tetap append-only.

## 11. Stock opname

### 11.1 `stock_opnames` dan `stock_opname_items`

Sesi opname memakai nomor `SO-YYYYMMDD-xxxxx` dengan state `draft`, `counting`, `pending_approval`, `rejected`, `approved`, `posted`, `cancelled`. Saat start, tiap item menyimpan `expected_quantity` dan `average_cost_snapshot`. Counting menyimpan `counted_quantity`, actor, dan waktu; submit menghitung `variance = counted - expected` untuk seluruh item.

Negative variance memakai `average_cost_snapshot`; positive variance memakai `active_cost_snapshot` saat submit/posting. Jika cost basis aktif nol, `positive_unit_cost` dan `positive_cost_reason` wajib diisi Admin/Supervisor.

### 11.2 `stock_opname_locks`

`product_id` menyimpan histori produk, sedangkan `active_product_id` bernilai produk selama lock aktif dan diubah `NULL` saat release (reject/cancel/post). Unique `active_product_id` menegakkan maksimal satu lock aktif per produk. Semua mutasi InventoryService menolak produk yang memiliki lock aktif; release tidak pernah meninggalkan lock yatim.
