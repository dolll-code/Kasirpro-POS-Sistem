# Panduan Pengguna KasirPro (User Guide)

| Metadata | Nilai |
|---|---|
| Modul | **Master Data Management & Pengaturan Toko** |
| Target Pembaca | Pemilik Toko (Admin), Pengawas Operasional (Supervisor), Kasir |
| Terakhir Ditinjau | 2026-11-17 |

---

## 1. Pendahuluan & Hak Akses Menu Master Data

Modul Master Data merupakan fondasi utama operasional KasirPro. Seluruh transaksi penjualan kasir, penerimaan pengadaan barang, dan pencatatan kas mengacu pada data master yang dikelola di modul ini.

### Matriks Akses Pengguna:
- **Admin (Pemilik Toko):** Memiliki akses penuh terhadap seluruh master data, termasuk pendaftaran produk, perubahan harga jual, akun kas/bank, dan konfigurasi profil toko.
- **Supervisor (Pengawas Operasional):** Dapat melihat katalog produk, mengelola data pemasok/distributor, dan mengelola kategori biaya operasional.
- **Kasir:** Hanya dapat melihat daftar produk dan harga aktif untuk keperluan verifikasi harga belanjaan pelanggan di meja kasir.

---

## 2. Manajemen Kategori Produk (`/categories`)

Kategori digunakan untuk mengelompokkan barang dagangan agar mempermudah pencarian pada POS dan analisa laba per kategori produk.

1. **Menambah Kategori Baru:**
   - Masuk ke menu **Master Data &rarr; Kategori Produk**, lalu klik tombol **Tambah Kategori**.
   - Masukkan nama kategori (misal: *Sembako*, *Makanan Ringan*).
   - Kolom *Slug* dapat dikosongkan agar otomatis diisi oleh sistem.
   - Klik **Simpan Kategori**.
2. **Mengubah & Menonaktifkan Kategori:**
   - Klik tombol **Edit** pada baris kategori yang bersangkutan untuk memperbarui nama/deskripsi.
   - Klik tombol **Nonaktifkan** jika kelompok produk tersebut sementara waktu tidak dijual.
3. **Aturan Proteksi Penghapusan:**
   - Kategori yang sudah memiliki produk terdaftar di dalamnya **tidak dapat dihapus permanen** demi menjaga integritas data. Jika tidak lagi digunakan, gunakan opsi *Nonaktifkan*.

---

## 3. Manajemen Satuan Kuantitas Presisi (`/units`)

KasirPro mendukung satuan unit dengan toleransi desimal pecahan mulai dari 0 hingga 3 digit.

| Tingkat Presisi | Contoh Satuan | Contoh Kuantitas Valid | Peruntukan Barang |
|---|---|---|---|
| **0 Digit Desimal** | `pcs`, `pack`, `box` | `1`, `3`, `12` | Barang kemasan utuh (tidak dapat diecer setengah) |
| **1 Digit Desimal** | `porsi`, `lusin` | `1.5`, `2.0` | Eceran setengah porsi |
| **2 Digit Desimal** | `ltr`, `meter` | `1.25`, `2.75` | Bahan cair kemasan jeriken atau kain meteran |
| **3 Digit Desimal** | `kg`, `gram` | `0.250`, `1.475` | Barang timbangan presisi tinggi (sayur, buah, daging) |

> [!NOTE]
> Mesin kasir POS akan secara otomatis menolak input kuantitas belanjaan yang memiliki jumlah digit desimal melebihi presisi satuan barang tersebut.

---

## 4. Manajemen Katalog Produk (`/products`)

### A. Pendaftaran Produk Baru
1. Buka menu **Master Data &rarr; Katalog Produk**, lalu klik **Tambah Produk Baru**.
2. **Nomor SKU:** Masukkan kode unik produk (misal: `BRG-001`). Sistem otomatis mengonversi ke huruf kapital dan menghapus spasi berlebih.
3. **Kode Barcode:** Masukkan barcode fisik kemasan atau tembakkan alat **USB Barcode Scanner**. Angka nol di awal barcode (seperti `00123456789`) akan tersimpan utuh. Barcode bersifat opsional (dapat dikosongkan untuk produk tanpa barcode pabrik).
4. **Pilih Kategori & Satuan:** Pilih kategori dan satuan unit yang berstatus aktif.
5. **Harga Jual (Rp):** Masukkan harga jual resmi toko di meja kasir.
6. **Batas Minimum Stok:** Masukkan batas stok pengingat. Jika stok riil di toko berada di bawah angka ini, sistem akan memberikan tanda peringatan stok menipis.
7. Klik **Simpan Produk**.

### B. Mengapa Tidak Ada Kolom Pengisian Stok & HPP pada Form Produk?
Sesuai standar audit akuntansi retail (`PRD §6.2` dan `BR-CAT-03`):
- Jumlah fisik stok (*quantity on-hand*) dan harga pokok pembelian (*moving average cost*) **dilarang diisi manual** melalui formulir master produk.
- Saldo stok dan HPP awal terbentuk secara sah dan berdasar bukti audit melalui:
  1. Dokumen **Penerimaan Barang (Purchase Order / Receipt)** pada Tahap 04, atau
  2. Dokumen **Stok Opname Awal Toko** pada Tahap 09.

### C. Riwayat Perubahan Harga Jual (Audit Trail FR-MST-04)
- Setiap kali harga jual produk diubah, sistem mencatat waktu kejadian, harga sebelum, harga sesudah, nama pengguna yang mengubah, serta alasan perubahan.
- Riwayat lengkap perubahan harga dapat dilihat dengan mengklik tombol **Detail** pada baris produk terkait.

### D. Status Produk Layak Jual (*Sellable Condition*)
Sebuah produk hanya dapat dicari dan dijual di meja kasir jika:
1. Status produk tersebut berstatus **Aktif**.
2. Kategori produk tersebut berstatus **Aktif**.
3. Satuan produk tersebut berstatus **Aktif**.

---

## 5. Manajemen Pemasok / Distributor (`/suppliers`)

Digunakan untuk mendata rekanan penyedia pasokan barang toko untuk keperluan modul pengadaan (PO).
- **Kode Pemasok:** Kode unik pengenal distributor (misal: `SUP-001`).
- **Pemasok Nonaktif:** Pemasok yang dinonaktifkan dilarang dipilih saat membuat Purchase Order (PO) baru.
- **Proteksi Hapus:** Pemasok yang telah memiliki transaksi pembelian historis tidak dapat dihapus permanen.

---

## 6. Manajemen Kategori Biaya Operasional (`/expense-categories`)

Klasifikasi pos pengeluaran kas toko, antara lain:
- Tagihan Listrik, Air & Internet
- Perlengkapan Kasir (Kertas Struk Thermal & Kantong Belanja)
- Kebersihan & Pemeliharaan Alat
- Konsumsi & Lembur Staf

---

## 7. Manajemen Akun Kas & Bank (`/cash-accounts`)

Buku rekening untuk menampung pergerakan uang masuk dan keluar:
- **Kas Fisik:** Laci kasir meja toko.
- **Rekening Bank:** Rekening giro/tabungan operasional toko (misal: Bank BCA, Mandiri).
- **Saldo Pembukaan Awal (DEC-17):** Diinput saat mendaftarkan akun baru dan **terkunci permanen dari pengeditan langsung** untuk menjaga integritas buku kas *append-only*. Perubahan saldo di masa datang wajib menggunakan dokumen mutasi kas atau penyesuaian kas resmi.

---

## 8. Profil Toko & Pengaturan Struk Kasir (`/store-profile`)

Pengaturan identitas toko yang otomatis tersemat sebagai *snapshot* permanen pada setiap nota transaksi penjualan:
- **Identitas Usaha:** Nama toko, alamat, nomor telepon, email, dan NPWP toko.
- **Ukuran Lebar Kertas Printer Thermal:**
  - `58 mm`: Ukuran printer kasir standar (32 karakter per baris).
  - `80 mm`: Ukuran printer kasir lebar (48 karakter per baris).
- **Header & Footer Struk:** Ucapan selamat datang dan pesan garansi/kebijakan retur barang di bagian bawah nota.
- **Logo Struk:** Unggah logo toko (format PNG, JPG, JPEG, WEBP, maks 2 MB) untuk dicetak pada struk belanja dan tampilan sistem.
