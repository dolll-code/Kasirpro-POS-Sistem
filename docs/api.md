# Spesifikasi Endpoint & API (API & Route Contract)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 03 — Selesai)** |
| Base URL | `http://localhost:8000` |
| Autentikasi | Cookie-based Session (`SameSite=Lax`, CSRF Protected) |
| Terakhir Ditinjau | 2026-11-17 |
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
| `GET` | `/cash-accounts` | `cash-accounts.index` | `store_settings.manage` | Daftar akun kas fisik & rekening bank |
| `GET` | `/cash-accounts/create` | `cash-accounts.create` | `store_settings.manage` | Formulir pendaftaran akun kas/bank baru |
| `POST` | `/cash-accounts` | `cash-accounts.store` | `store_settings.manage` | Simpan akun kas/bank dengan saldo pembukaan |
| `GET` | `/cash-accounts/{account}/edit` | `cash-accounts.edit` | `store_settings.manage` | Formulir ubah akun (saldo awal terkunci) |
| `PUT` | `/cash-accounts/{account}` | `cash-accounts.update` | `store_settings.manage` | Perbarui nama / kode / tipe akun |
| `DELETE` | `/cash-accounts/{account}` | `cash-accounts.destroy` | `store_settings.manage` | Hapus akun (ditolak 409 jika memiliki pergerakan kas) |
| `PATCH` | `/cash-accounts/{account}/toggle-status` | `cash-accounts.toggle_status` | `store_settings.manage` | Aktifkan / nonaktifkan akun kas/bank |

### 3.8 Profil Toko & Pengaturan Struk (Store Profile)
| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/store-profile` | `store-profile.edit` | `store_settings.manage` | Formulir pengaturan profil toko & struk thermal |
| `PUT` | `/store-profile` | `store-profile.update` | `store_settings.manage` | Perbarui identitas toko, logo, & lebar nota (58/80 mm) |
