# Spesifikasi Endpoint & API (API & Route Contract)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 07 — Selesai)** |
| Base URL | `http://localhost:8000` |
| Autentikasi | Cookie-based Session (`SameSite=Lax`, CSRF Protected) |
| Terakhir Ditinjau | 2026-11-19 |
| Sumber Terkait | `prd.md` (§11), `routes/web.php` |

---

## 1. Prinsip Kontrak Endpoint
1. **Header Standar:** Setiap respons HTTP menyertakan header `X-Request-Id` (UUIDv4) untuk korelasi penelusuran log audit.
2. **Format Galat Terpadu (JSON Request):**
   ```json
   {
     "error": {
       "code": "FORBIDDEN",
       "message": "Anda tidak memiliki hak akses untuk tindakan ini."
     },
     "meta": {
       "request_id": "01J..."
     }
   }
   ```
3. **Status Codes:**
   - `200 OK`: Permintaan berhasil diproses.
   - `302 Found`: Pengalihan rute (standar formulir web).
   - `401 Unauthorized`: Sesi tidak ditemukan atau akun dinonaktifkan.
   - `403 Forbidden`: Izin tidak mencukupi untuk resource/tindakan target.
   - `422 Unprocessable Content`: Validasi input formulir gagal.
   - `429 Too Many Requests`: Batas laju pemanggilan terlampaui (*rate limited*).

---

## 2. Katalog Rute Autentikasi & Pengguna (Tahap 02)

| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/login` | `login` | Publik (Guest) | Menampilkan formulir masuk sistem |
| `POST` | `/login` | — | Publik (Rate limited: 5x/m) | Memproses autentikasi kredensial |
| `POST` | `/logout` | `logout` | `auth` | Menginvalidasi sesi & keluar sistem |
| `GET` | `/profile` | `profile.show` | `auth`, `active` | Menampilkan profil pengguna sendiri |
| `PUT` | `/profile/password` | `profile.password.update` | `auth`, `active` | Memperbarui kata sandi mandiri |
| `GET` | `/users` | `users.index` | `users.manage` | Daftar pengguna toko (terfilter & terpaginasi) |
| `GET` | `/users/create` | `users.create` | `users.manage` | Formulir tambah pengguna baru |
| `POST` | `/users` | `users.store` | `users.manage` | Menyimpan pengguna baru ke basis data |
| `GET` | `/users/{user}` | `users.show` | `users.manage` | Detail pengguna, hak akses, & riwayat audit |
| `GET` | `/users/{user}/edit` | `users.edit` | `users.manage` | Formulir ubah data pengguna |
| `PUT` | `/users/{user}` | `users.update` | `users.manage` | Memperbarui data & peran pengguna |
| `PATCH` | `/users/{user}/toggle-status` | `users.toggle_status` | `users.manage` | Mengaktifkan / menonaktifkan akun staf |

---

## 3. Katalog Rute Master Data & Katalog (Tahap 03)

### 3.1 Dashboard Master
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/master` | `master.index` | `auth`, `active` | Dashboard ringkasan jumlah entitas master data |

### 3.2 Kategori Produk (Categories)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/categories` | `categories.index` | `catalog.manage` | Daftar kategori produk (filter & paginasi) |
| `GET` | `/categories/create` | `categories.create` | `catalog.manage` | Formulir tambah kategori |
| `POST` | `/categories` | `categories.store` | `catalog.manage` | Simpan kategori baru |
| `GET` | `/categories/{category}/edit` | `categories.edit` | `catalog.manage` | Formulir ubah kategori |
| `PUT` | `/categories/{category}` | `categories.update` | `catalog.manage` | Perbarui data kategori |
| `DELETE` | `/categories/{category}` | `categories.destroy` | `catalog.manage` | Hapus kategori (ditolak 409 jika terikat produk) |
| `PATCH` | `/categories/{category}/toggle-status` | `categories.toggle_status` | `catalog.manage` | Aktifkan / nonaktifkan kategori |

### 3.3 Satuan Kuantitas (Units)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/units` | `units.index` | `catalog.manage` | Daftar satuan unit & presisi pecahan |
| `GET` | `/units/create` | `units.create` | `catalog.manage` | Formulir tambah satuan |
| `POST` | `/units` | `units.store` | `catalog.manage` | Simpan satuan baru (presisi 0–3 digit) |
| `GET` | `/units/{unit}/edit` | `units.edit` | `catalog.manage` | Formulir ubah satuan |
| `PUT` | `/units/{unit}` | `units.update` | `catalog.manage` | Perbarui data satuan |
| `DELETE` | `/units/{unit}` | `units.destroy` | `catalog.manage` | Hapus satuan (ditolak 409 jika terikat produk) |
| `PATCH` | `/units/{unit}/toggle-status` | `units.toggle_status` | `catalog.manage` | Aktifkan / nonaktifkan satuan |

### 3.4 Katalog Produk (Products)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/products` | `products.index` | `products.view` | Katalog produk terfilter (SKU/barcode/kategori/status) |
| `GET` | `/products/create` | `products.create` | `products.create` | Formulir pendaftaran produk baru |
| `POST` | `/products` | `products.store` | `products.create` | Simpan produk baru & catat harga awal |
| `GET` | `/products/{product}` | `products.show` | `products.view` | Detail produk & audit riwayat perubahan harga |
| `GET` | `/products/{product}/edit` | `products.edit` | `products.update` | Formulir ubah produk |
| `PUT` | `/products/{product}` | `products.update` | `products.update` | Perbarui produk & catat `product_price_histories` bila harga berubah |
| `DELETE` | `/products/{product}` | `products.destroy` | `products.deactivate`| Hapus produk (ditolak 409 jika punya riwayat harga) |
| `PATCH` | `/products/{product}/toggle-status` | `products.toggle_status` | `products.deactivate`| Aktifkan / nonaktifkan status jual produk |
| `GET` | `/api/v1/products/lookup?q=` | `products.lookup` | `products.view` | Endpoint pencarian cepat produk aktif untuk POS / kasir |

### 3.5 Pemasok (Suppliers)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/suppliers` | `suppliers.index` | `suppliers.view` | Daftar pemasok distributor barang |
| `GET` | `/suppliers/create` | `suppliers.create` | `suppliers.manage` | Formulir tambah pemasok |
| `POST` | `/suppliers` | `suppliers.store` | `suppliers.manage` | Simpan pemasok baru |
| `GET` | `/suppliers/{supplier}` | `suppliers.show` | `suppliers.view` | Detail profil pemasok |
| `GET` | `/suppliers/{supplier}/edit` | `suppliers.edit` | `suppliers.manage` | Formulir ubah data pemasok |
| `PUT` | `/suppliers/{supplier}` | `suppliers.update` | `suppliers.manage` | Perbarui data pemasok |
| `DELETE` | `/suppliers/{supplier}` | `suppliers.destroy` | `suppliers.manage` | Hapus pemasok (ditolak 409 jika memiliki transaksi PO) |
| `PATCH` | `/suppliers/{supplier}/toggle-status` | `suppliers.toggle_status` | `suppliers.manage` | Aktifkan / nonaktifkan status pemasok |

### 3.6 Kategori Biaya Operasional (Expense Categories)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/expense-categories` | `expense-categories.index` | `expenses.create` | Daftar pos pengeluaran operasional |
| `GET` | `/expense-categories/create` | `expense-categories.create` | `expenses.create` | Formulir tambah kategori biaya |
| `POST` | `/expense-categories` | `expense-categories.store` | `expenses.create` | Simpan kategori biaya baru |
| `GET` | `/expense-categories/{category}/edit` | `expense-categories.edit` | `expenses.create` | Formulir ubah kategori biaya |
| `PUT` | `/expense-categories/{category}` | `expense-categories.update` | `expenses.create` | Perbarui data kategori biaya |
| `DELETE` | `/expense-categories/{category}` | `expense-categories.destroy` | `expenses.create` | Hapus kategori biaya (ditolak 409 jika terpakai) |
| `PATCH` | `/expense-categories/{category}/toggle-status` | `expense-categories.toggle_status` | `expenses.create` | Aktifkan / nonaktifkan pos beban |

### 3.7 Akun Kas & Bank (Cash Accounts)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/cash-accounts` | `cash-accounts.index` | `store_settings.manage` | Daftar akun kas fisik & rekening bank (dengan kartu KPI saldo likuiditas) |
| `GET` | `/cash-accounts/create` | `cash-accounts.create` | `store_settings.manage` | Formulir pendaftaran akun kas/bank baru |
| `POST` | `/cash-accounts` | `cash-accounts.store` | `store_settings.manage` | Simpan akun kas/bank dengan saldo pembukaan awal atomik |
| `GET` | `/cash-accounts/{account}` | `cash-accounts.show` | `store_settings.manage` | Buku Kas: rincian akun, riwayat mutasi kas, filter & status rekonsiliasi |
| `GET` | `/cash-accounts/{account}/edit` | `cash-accounts.edit` | `store_settings.manage` | Formulir ubah akun (saldo awal terkunci) |
| `PUT` | `/cash-accounts/{account}` | `cash-accounts.update` | `store_settings.manage` | Perbarui nama / kode / tipe akun |
| `DELETE` | `/cash-accounts/{account}` | `cash-accounts.destroy` | `store_settings.manage` | Hapus akun (ditolak 409 jika memiliki pergerakan kas) |
| `PATCH` | `/cash-accounts/{account}/toggle-status` | `cash-accounts.toggle_status` | `store_settings.manage` | Aktifkan / nonaktifkan akun kas/bank |
| `POST` | `/cash-accounts/{account}/reverse/{movement}` | `cash-accounts.reverse_movement` | `store_settings.manage` | Pembalikan transaksi kas (*reversal*) dengan alasan wajib |
| `GET` | `/cash-accounts/{account}/reconcile` | `cash-accounts.reconcile` | `store_settings.manage` | Status rekonsiliasi audit saldo buku besar kas (JSON) |

### 3.8 Profil Toko & Pengaturan Struk (Store Profile)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/store-profile` | `store-profile.edit` | `store_settings.manage` | Formulir pengaturan profil toko & struk thermal |
| `PUT` | `/store-profile` | `store-profile.update` | `store_settings.manage` | Perbarui identitas toko, logo, & lebar nota (58/80 mm) |

---

## 4. Katalog Rute Persediaan & Buku Besar Stok (Tahap 04)

### 4.1 Rute Antarmuka Web
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/inventory` | `inventory.index` | `inventory.view` | Dashboard saldo stok, status ketersediaan, & filter kategori |
| `GET` | `/inventory/low-stock` | `inventory.low_stock` | `inventory.view` | Daftar produk berstatus stok habis (0) dan menipis (<= minimum) |
| `GET` | `/inventory/{product}/card` | `inventory.card` | `inventory.view` | Kartu stok barang: mutasi kronologis & status rekonsiliasi |
| `GET` | `/inventory/{product}/reconcile` | `inventory.reconcile` | `inventory.view` | Status rekonsiliasi saldo proyeksi vs ledger mutasi |

### 4.2 Endpoint JSON API (`/api/v1/inventory/*`)
| Method | Rute URI | Izin (Permission) | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/inventory/balances` | `inventory.view` | Mengambil data saldo stok produk (terfilter & terpaginasi) |
| `GET` | `/api/v1/inventory/movements` | `inventory.view` | Mengambil jejak mutasi buku besar persediaan append-only |
| `GET` | `/api/v1/inventory/low-stock` | `inventory.view` | Mengambil data produk yang membutuhkan restock |
| `GET` | `/api/v1/inventory/{product}/reconcile` | `inventory.view` | Memeriksa integritas konsistensi saldo vs akumulasi mutasi |

---

## 5. Katalog Rute Manajemen Kas & Buku Besar Kas (Tahap 05)

### 5.1 Rute Antarmuka Web Buku Kas
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/cash-accounts` | `cash-accounts.index` | `store_settings.manage` | Ringkasan KPI saldo kas & bank, daftar akun aktif/nonaktif |
| `GET` | `/cash-accounts/{account}` | `cash-accounts.show` | `store_settings.manage` | Buku Kas: kartu rekonsiliasi, filter arah & tanggal, riwayat mutasi kas |
| `POST` | `/cash-accounts/{account}/reverse/{movement}` | `cash-accounts.reverse_movement` | `store_settings.manage` | Eksekusi pembalikan kas (*reversal*) dengan pencatatan jejak audit |

### 5.2 Endpoint JSON API (`/api/v1/cash-accounts/*`)
| Method | Rute URI | Izin (Permission) | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/cash-accounts` | `store_settings.manage` | Mengambil daftar akun kas/bank, saldo terkini, dan total KPI |
| `GET` | `/api/v1/cash-accounts/{account}` | `store_settings.manage` | Mengambil rincian akun kas, saldo, dan status rekonsiliasi |
| `GET` | `/api/v1/cash-accounts/{account}/movements` | `store_settings.manage` | Mengambil buku besar mutasi kas terpaginasi beserta relasi pembuat & pembalik |
| `GET` | `/api/v1/cash-accounts/{account}/reconcile` | `store_settings.manage` | Mengambil status rekonsiliasi matematis buku besar kas |
| `POST` | `/api/v1/cash-accounts/{account}/reverse/{movement}` | `store_settings.manage` | Melakukan pembalikan transaksi via API (wajib menyertakan `reason`) |

---

## 6. Katalog Rute Pembelian, Penerimaan, & Pembayaran Pemasok (Tahap 06)

### 6.1 Rute Antarmuka Web Pembelian & Pesanan (Purchase Orders)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/purchases` | `purchases.index` | `purchases.view` | Dashboard PO, filter status pesanan & status bayar, kartu KPI ringkasan |
| `GET` | `/purchases/create` | `purchases.create` | `purchases.create` | Formulir pembuat draft PO interaktif (Alpine.js live calculation) |
| `POST` | `/purchases` | `purchases.store` | `purchases.create` | Menyimpan draft pesanan pembelian baru beserta rincian item & diskon |
| `GET` | `/purchases/{purchase}` | `purchases.show` | `purchases.view` | Detail lengkap PO, progress penerimaan, daftar receipt & pembayaran |
| `GET` | `/purchases/{purchase}/edit` | `purchases.edit` | `purchases.create` | Formulir edit draft pesanan pembelian (hanya saat status `draft`) |
| `PUT` | `/purchases/{purchase}` | `purchases.update` | `purchases.create` | Memperbarui rincian item/diskon draft PO |
| `DELETE` | `/purchases/{purchase}` | `purchases.destroy` | `purchases.create` | Menghapus draft pesanan (ditolak 409 jika status bukan `draft`) |
| `POST` | `/purchases/{purchase}/confirm` | `purchases.confirm` | `purchases.create` | Konfirmasi PO (`draft` -> `ordered`), mengunci pengeditan langsung |
| `POST` | `/purchases/{purchase}/cancel` | `purchases.cancel` | `purchases.create` | Membatalkan/menutup sisa PO dengan alasan pembatalan wajib |

### 6.2 Rute Antarmuka Web Penerimaan Barang (Goods Receiving)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/purchases/{purchase}/receive` | `purchases.receipts.create` | `purchases.create` | Formulir penerimaan fisik barang (`accepted`, `rejected`, & biaya landed cost) |
| `POST` | `/purchases/{purchase}/receipts` | `purchases.receipts.store` | `purchases.create` | Memposting penerimaan barang atomik, mutasi persediaan, & pembaruan HPP MWA |
| `GET` | `/purchases/{purchase}/receipts/{receipt}` | `purchases.receipts.show` | `purchases.view` | Rincian bukti penerimaan barang (*Good Receipt Note*) & alokasi biaya landed cost |

### 6.3 Rute Antarmuka Web Pembayaran Pemasok (Supplier Payments)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/purchases/{purchase}/payments/create` | `purchases.payments.create` | `purchases.create` | Formulir pencatatan pembayaran utang pemasok dengan batas sisa tagihan |
| `POST` | `/purchases/{purchase}/payments` | `purchases.payments.store` | `purchases.create` | Menyimpan pembayaran utang, memotong saldo kas/bank, & memperbarui status bayar |
| `POST` | `/purchases/{purchase}/payments/{payment}/reverse` | `purchases.payments.reverse` | `purchases.create` | Pembalikan pembayaran pemasok (*reversal*) dengan pengembalian saldo kas & alasan wajib |

### 6.4 Endpoint JSON API (`/api/v1/purchases/*`)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/api/v1/purchases` | `api.purchases.index` | `purchases.view` | Mengambil daftar PO terpaginasi dengan relasi pemasok & rincian status |
| `POST` | `/api/v1/purchases` | `api.purchases.store` | `purchases.create` | Membuat draft PO baru via payload JSON terstruktur |
| `GET` | `/api/v1/purchases/{purchase}` | `api.purchases.show` | `purchases.view` | Mengambil rincian PO, item, riwayat receipt, dan riwayat pembayaran |
| `PATCH` | `/api/v1/purchases/{purchase}` | `api.purchases.update` | `purchases.create` | Memperbarui draft PO via JSON |
| `POST` | `/api/v1/purchases/{purchase}/confirm` | `api.purchases.confirm` | `purchases.create` | Mengonfirmasi PO menjadi `ordered` via API |
| `POST` | `/api/v1/purchases/{purchase}/cancel` | `api.purchases.cancel` | `purchases.create` | Membatalkan / menutup sisa PO via API (wajib field `cancellation_reason`) |
| `POST` | `/api/v1/purchases/{purchase}/receipts` | `api.purchases.receipts.store` | `purchases.create` | Memposting penerimaan barang via JSON API dengan kalkulasi landed cost |
| `GET` | `/api/v1/purchases/{purchase}/receipts/{receipt}` | `api.purchases.receipts.show` | `purchases.view` | Mengambil rincian nota penerimaan dan alokasi landed cost per item |
| `POST` | `/api/v1/purchases/{purchase}/payments` | `api.purchases.payments.store` | `purchases.create` | Mencatat pembayaran utang pemasok via API (memvalidasi batas utang barang diterima) |
| `POST` | `/api/v1/purchases/{purchase}/payments/{payment}/reverse` | `api.purchases.payments.reverse` | `purchases.create` | Membalikkan transaksi pembayaran pemasok via API (wajib field `reason`) |

---

## 7. Katalog Rute POS, Penjualan Tunai, Diskon & Struk (Tahap 07)

### 7.1 Rute Antarmuka Web POS & Penjualan (Sales)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/pos` | `pos.index` | `sales.create` | Antarmuka kasir layar penuh (pencarian, barcode USB wedge, katalog, keranjang, diskon, pembayaran tunai) |
| `POST` | `/pos/calculate` | `pos.calculate` | `sales.create` | Endpoint kalkulasi total keranjang belanja server-authoritative live preview |
| `POST` | `/pos/checkout` | `pos.checkout` | `sales.create` | Memproses checkout pembayaran tunai secara atomik dengan Idempotency-Key |
| `GET` | `/pos/barcode/{barcode}` | `pos.barcode` | `sales.create` | Endpoint pencarian barcode cepat USB scanner dengan pengembalian fokus otomatis |
| `GET` | `/sales` | `sales.index` | `sales.view_own` / `sales.view_all` | Riwayat transaksi penjualan kasir (kasir hanya transaksi sendiri, admin/supervisor semua) |
| `GET` | `/sales/idempotency/{key}` | `sales.idempotency` | `sales.view_own` / `sales.view_all` | Pemeriksaan status transaksi berdasarkan Idempotency-Key setelah insiden timeout |
| `GET` | `/sales/{sale}` | `sales.show` | `sales.view_own` / `sales.view_all` | Rincian lengkap transaksi penjualan, rincian diskon, pembayaran tunai, dan HPP snapshot |
| `GET` | `/sales/{sale}/receipt` | `sales.receipt` | `sales.view_own` / `sales.view_all` | Tampilan struk belanja siap cetak format thermal 58 mm / 80 mm dan penanda cetak ulang |

### 7.2 Endpoint JSON API (`/api/v1/sales/*` & `/api/v1/products/barcode/*`)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `POST` | `/api/v1/sales/calculate` | `api.sales.calculate` | `sales.create` | Menghitung subtotal, diskon baris, alokasi diskon transaksi, dan grand total di server |
| `POST` | `/api/v1/sales/checkout` | `api.sales.checkout` | `sales.create` | Checkout tunai transaksi penjualan via API (header `Idempotency-Key: UUID`) |
| `GET` | `/api/v1/sales` | `api.sales.index` | `sales.view_own` / `sales.view_all` | Daftar transaksi penjualan terpaginasi format JSON |
| `GET` | `/api/v1/sales/idempotency/{key}` | `api.sales.idempotency` | `sales.view_own` / `sales.view_all` | Mengambil status transaksi via Idempotency-Key (pencegahan double checkout akibat timeout) |
| `GET` | `/api/v1/sales/{sale}` | `api.sales.show` | `sales.view_own` / `sales.view_all` | Rincian data transaksi penjualan dan rincian item format JSON |
| `GET` | `/api/v1/products/barcode/{barcode}` | `api.products.barcode` | `products.view` | Lookup produk aktif via barcode untuk integrasi perangkat kasir |

### 7.3 Contoh Kontrak Payload & Respons Checkout Tunai

#### Request: `POST /api/v1/sales/checkout`
Header:
- `Content-Type: application/json`
- `X-CSRF-TOKEN: <token>`
- `Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000`

Body:
```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": "2.000",
      "line_discount_type": "amount",
      "line_discount_value": "1000.00"
    }
  ],
  "transaction_discount_type": "percent",
  "transaction_discount_value": "5.00",
  "payment": {
    "method": "cash",
    "cash_account_id": 1,
    "cash_tendered": "100000.00"
  },
  "discount_approval": {
    "approver_id": 2,
    "approver_password": "supervisor_secret",
    "reason": "Diskon khusus hari kemerdekaan"
  },
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
}
```

#### Response Sukses (201 Created):
```json
{
  "message": "Transaksi penjualan berhasil diproses.",
  "data": {
    "id": 10,
    "transaction_number": "TRX-20261119-00001",
    "gross_total": "50000.00",
    "item_discount_total": "2000.00",
    "transaction_discount_total": "2400.00",
    "grand_total": "45600.00",
    "cash_tendered": "100000.00",
    "change_amount": "54400.00",
    "completed_at": "2026-11-19T17:00:00+07:00",
    "receipt_url": "http://localhost:8000/sales/10/receipt",
    "items_count": 1
  }
}
```

#### Response Replay Idempotent (Key Sama & Payload Sama):
Mengembalikan status HTTP 201 Created dengan nomor transaksi dan nilai yang identik tanpa menciptakan mutasi baru.

#### Response Konflik Idempotent (Key Sama & Payload Berbeda - 409 Conflict):
```json
{
  "error": {
    "code": "IDEMPOTENCY_CONFLICT",
    "message": "Idempotency-Key [550e8400-e29b-41d4-a716-446655440000] telah digunakan sebelumnya dengan parameter pesanan berbeda."
  }
}
```



