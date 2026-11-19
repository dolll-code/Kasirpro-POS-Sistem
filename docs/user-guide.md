# Panduan Pengguna KasirPro (User Guide)

| Metadata | Nilai |
|---|---|
| Modul | **Master Data, Buku Kas, Persediaan, Pengadaan, POS & Transfer Manual (Tahap 08)** |
| Target Pembaca | Pemilik Toko (Admin), Pengawas Operasional (Supervisor), Kasir |
| Terakhir Ditinjau | 2026-11-19 |

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

---

## 9. Buku Kas & Rekonsiliasi Pergerakan Kas (`/cash-accounts/{account}`)

Buku Kas (*Cash Ledger*) menyajikan catatan kronologis mutasi kas secara kekal (*append-only*).

### A. Kartu Ringkasan Indikator Keuangan (KPI)
Pada halaman utama **Akun Kas & Bank (`/cash-accounts`)**, terdapat 3 kartu KPI utama:
1. **Total Kas Fisik:** Jumlah seluruh saldo uang tunai yang berada di laci kasir dan brankas toko.
2. **Total Saldo Bank:** Akumulasi dana pada seluruh rekening bank operasional yang aktif.
3. **Total Likuiditas Toko:** Jumlah agregat keseluruhan uang tunai dan saldo bank yang siap digunakan.

### B. Membuka Buku Kas & Riwayat Mutasi
Klik tombol **Buku Kas** pada akun kas atau bank yang dipilih:
- **Informasi Akun:** Menampilkan kode, tipe, tanggal pembukaan, dan saldo terkini (`current_balance`).
- **Audit Rekonsiliasi:** Kartu audit otomatis yang memverifikasi bahwa `Saldo Saat Ini` sama persis dengan `Saldo Awal + Total Masuk - Total Keluar` (`difference = 0.00`).
- **Penyaringan Data:** Filter mutasi berdasarkan rentang tanggal kejadian (*Dari Tanggal* & *Sampai Tanggal*), arah mutasi (*Kas Masuk / Keluar*), serta jenis mutasi.

### C. Pembatalan Transaksi Kas (Reversal)
Sesuai prinsip kepatuhan pembukuan, baris mutasi kas **tidak dapat dihapus atau disunting**. Jika terjadi kesalahan pencatatan transaksi:
1. Klik tombol **Balikkan Transaksi (Reversal)** pada baris mutasi yang ingin dibatalkan.
2. Sistem akan membuka dialog konfirmasi dan **mewajibkan pengisian alasan pembalikan** (contoh: *"Koreksi salah input nomor nota penjualan"*).
3. Saat dikonfirmasi, sistem akan:
   - Membuat baris mutasi baru bertipe `REVERSAL` dengan arah berlawanan.
   - Menautkan ID transaksi asli pada kolom `reversal_of_id`.
   - Mengembalikan atau mengurangi saldo kas secara atomik.
   - Mencatat aktivitas pembalikan pada log audit sistem.
   - Menandai transaksi asli dengan lencana status *Dibalikkan*.
4. Transaksi yang sudah pernah dibalikkan atau transaksi reversal itu sendiri dilarang untuk dibalikkan kembali.

---

## 10. Panduan Pengadaan Barang, Penerimaan, & Pembayaran Pemasok (`/purchases`)

Modul Pengadaan KasirPro (`PRD §5.4`, `BR-PUR-01` s.d. `BR-PUR-07`) mengintegrasikan proses pembelian barang dagang, verifikasi fisik penerimaan di gudang, alokasi biaya pengiriman (*landed cost*), pembaruan otomatis Harga Pokok Penjualan (HPP Moving Weighted Average), serta pencatatan utang dan pelunasan kas pemasok secara atomik.

### 10.1 Alur Siklus Transaksi Pengadaan

```text
[ Buat Draft PO ] ──> [ Konfirmasi PO ] ──> [ Penerimaan Fisik (Receipt) ] ──> [ Pembayaran Pemasok ]
  (draft)               (ordered)             (partially_received / received)      (unpaid / partially / paid)
     │                     │
     └─> [ Hapus Draft ]   └─> [ Batalkan PO (Wajib Alasan) ]
```

1. **Draft:** Dokumen pesanan pembelian awal yang masih dapat diubah, ditambah, atau dikurangi itemnya secara bebas.
2. **Ordered:** Pesanan resmi yang telah disetujui dan dikirimkan ke pihak distributor/pemasok. Formulir pesanan langsung terkunci dari penyuntingan manual.
3. **Partially Received:** Barang telah tiba sebagian di toko/gudang dan telah dibuatkan nota penerimaan barang (*Goods Receipt Note*).
4. **Received:** Seluruh kuantitas barang yang dipesan telah diterima secara lengkap.
5. **Cancelled:** Pesanan dibatalkan (atau sisa kuantitas yang belum datang ditutup) disertai alasan pembatalan wajib.

---

### 10.2 Membuat Draft Pesanan Pembelian (Purchase Order)

1. Buka menu **Pembelian (PO)** pada navigasi utama, lalu klik tombol **+ Buat PO Baru** (`/purchases/create`).
2. **Pilih Pemasok:** Pilih distributor dari daftar pemasok aktif. (Pemasok nonaktif tidak akan muncul di daftar pilihan).
3. **Tanggal Pesanan & Estimasi Tiba:** Tentukan tanggal diterbitkannya PO serta perkiraan tanggal pengiriman barang tiba di toko.
4. **Rincian Barang yang Dipesan:**
   - Klik **+ Tambah Baris Produk** untuk menambah barang yang hendak dibeli.
   - Pilih produk dari daftar katalog barang aktif.
   - Masukkan **Kuantitas Pesanan** (harus lebih besar dari 0, sesuai dengan toleransi presisi desimal satuan barang).
   - Masukkan **Harga Beli Satuan (Rp)** (sebelum diskon).
   - Masukkan **Diskon Baris (Rp)** (opsional, nilai diskon tidak boleh melebihi nilai total baris).
5. **Diskon Dokumen / Faktur:**
   - Masukkan diskon global faktur (jika pihak pemasok memberikan potongan harga total atas seluruh nota).
   - Sistem secara otomatis membagi alokasi diskon global ke setiap baris barang secara proporsional menggunakan algoritma *deterministic residual rounding* tanpa pembulatan liar.
6. **Catatan Tambahan:** Masukkan instruksi khusus pengiriman atau keterangan faktur.
7. Klik **Simpan Draft PO**. Dokumen PO akan diterbitkan dengan format nomor urut resmi: `PO-YYYYMMDD-XXXXX`.

---

### 10.3 Mengonfirmasi Pesanan Pembelian

- Selama berstatus **Draft**, dokumen PO dapat diperiksa ulang atau diedit rinciannya melalui tombol **Edit Draft**.
- Jika rincian barang sudah disetujui, klik tombol **Konfirmasi Pesanan**.
- Sistem akan mengubah status PO dari `draft` menjadi `ordered`.
- **Proteksi Integritas:** PO yang berstatus `ordered` langsung **terkunci permanen dari pengeditan langsung** untuk mencegah manipulasi data order setelah dikirimkan ke pemasok.

---

### 10.4 Penerimaan Barang Fisik (Goods Receiving & Landed Cost)

Ketika kurir atau armada distributor tiba di toko membawa barang:

1. Buka detail PO yang bersangkutan (`/purchases/{purchase}`), lalu klik tombol **Terima Barang** (`/purchases/{purchase}/receive`).
2. Masukkan nomor surat jalan atau nomor faktur pengantar dari pemasok pada kolom **Nomor Faktur Pemasok / DO**.
3. **Pemeriksaan Fisik Kuantitas:**
   - Untuk setiap baris barang yang tiba, periksa kondisi fisiknya.
   - Masukkan kuantitas yang lolos sortir pada kolom **Diterima Layak (Accepted Qty)**.
   - Masukkan kuantitas yang rusak/cacat/kadaluwarsa pada kolom **Ditolak (Rejected Qty)**.
   - **Aturan Validasi Keras:** Sistem mewajibkan `Diterima + Ditolak = Total Fisik Datang`. Total yang diterima tidak boleh melebihi sisa pesanan yang belum terkirim.
   - **Perlakuan Barang Cacat:** Barang yang ditolak (*rejected goods*) **sama sekali tidak menambah stok persediaan** dan **tidak diakui sebagai utang usaha**, melainkan dicatat permanen dalam nota penerimaan sebagai bukti klaim retur pengiriman ke pemasok.
4. **Biaya Tambahan Pengadaan (Landed Cost):**
   - Jika ada biaya modal tambahan (seperti ongkos kirim armada, biaya bongkar muat kuli, atau asuransi kargo), masukkan nilai nominalnya pada kolom **Biaya Tambahan / Ongkos Angkut**.
   - Biaya ini akan dikapitalisasi ke dalam nilai perolehan barang diterima (*landed cost*) secara proporsional.
5. Klik **Posting Penerimaan Barang**.

#### Yang Terjadi Secara Otomatis di Latar Belakang (Atomic Transaction):
- **Buku Besar Stok:** Menambahkan mutasi masuk persediaan (`IN_PURCHASE`) untuk barang yang berstatus `accepted`.
- **Pembaruan HPP Moving Weighted Average (MWA):** Sistem menghitung ulang HPP barang secara otomatis:
  $$\text{HPP Baru} = \frac{(Q_{\text{on-hand}} \times C_{\text{lama}}) + (q_{\text{diterima}} \times c_{\text{landed}})}{Q_{\text{on-hand}} + q_{\text{diterima}}}$$
- **Status PO:** Jika seluruh barang pesanan sudah diterima lengkap, status PO otomatis berubah menjadi `received`. Jika baru tiba sebagian, status menjadi `partially_received`.
- **Kekekalan Data:** Dokumen bukti penerimaan barang (*Good Receipt Note*) bersifat *append-only* (dilarang diedit atau dihapus).

---

### 10.5 Pembayaran Utang Pemasok (Supplier Payment)

KasirPro menerapkan prinsip akuntansi kehati-hatian: **Utang usaha hanya terbentuk atas barang yang telah berstatus DITERIMA FISIK SECARA LAYAK (*accepted goods*) dari dokumen penerimaan yang sah.**

1. Buka halaman detail PO terkait (`/purchases/{purchase}`).
2. Periksa kartu status pembayaran:
   - **Total Tagihan Barang Diterima:** Nilai landed cost dari seluruh barang yang lolos sortir.
   - **Total Terbayar:** Akumulasi pembayaran yang sudah pernah disetorkan ke pemasok.
   - **Sisa Utang Terbuka (Outstanding Payable):** Batas maksimal dana yang dapat dibayarkan saat ini.
3. Klik tombol **Catat Pembayaran Pemasok** (`/purchases/{purchase}/payments/create`).
4. **Formulir Pembayaran:**
   - Pilih sumber rekening dana pada kolom **Akun Kas / Bank** (menampilkan saldo kas riil terkini).
   - Pilih metode pembayaran: `Tunai (Cash)` atau `Transfer Bank (Bank Transfer)`.
   - Masukkan nominal pembayaran pada kolom **Jumlah Bayar (Rp)**. Sistem akan menolak jika nominal melebihi sisa utang terbuka atau jika saldo kas/bank tidak mencukupi.
   - Masukkan nomor referensi bukti transfer bank atau nomor kwitansi pembayaran.
5. Klik **Simpan Pembayaran**.

#### Dampak Pembukuan Otomatis:
- Menerbitkan dokumen pembayaran bernomor resmi: `PAY-YYYYMMDD-XXXXX`.
- Memotong saldo akun kas/bank yang dipilih melalui mutasi keluar buku kas (`OUT_PURCHASE_PAYMENT`).
- Memperbarui status pembayaran PO:
  - `unpaid`: Belum ada pembayaran sama sekali.
  - `partially_paid`: Pembayaran sudah dicicil sebagian namun masih ada sisa utang.
  - `paid`: Utang atas barang yang telah diterima lunas sepenuhnya.

#### Pembatalan Pembayaran Pemasok (Reversal):
- Jika terjadi kekeliruan pencatatan pembayaran (misal salah memilih rekening bank), pembayaran dapat dibatalkan melalui tombol **Batalkan Pembayaran (Reversal)** pada riwayat pembayaran detail PO.
- Wajib mengisikan alasan pembatalan.
- Sistem akan mengembalikan dana kas/bank secara atomik melalui mutasi penyesuaian kas dan mengembalikan status sisa utang PO seperti semula.

---

### 10.6 Pembatalan & Penutupan Sisa Pesanan Pembelian

1. **Pembatalan Total:** Jika pesanan masih berstatus `draft` atau `ordered` dan barang belum pernah dikirimkan sama sekali, PO dapat dibatalkan sepenuhnya melalui tombol **Batalkan PO**.
2. **Penutupan Sisa Order (Partial Cancel):** Jika sebagian barang sudah diterima namun sisa pesanan tidak dapat dikirimkan lagi oleh pihak distributor (misal stok pabrik kosong), pengguna dapat menutup sisa pesanan dengan mengklik tombol **Tutup Sisa Pesanan**.
3. **Syarat Pembatalan:** Sistem mewajibkan pengisian **Alasan Pembatalan / Penutupan** demi keperluan audit pengawasan usaha. Sisa barang yang belum terkirim otomatis diabaikan, dan status PO ditandai sebagai `cancelled`.

---

## 11. Panduan Pengoperasian Mesin Kasir POS, Diskon, Pembayaran Tunai & Struk (Tahap 07)

### 11.1 Membuka Mesin Kasir (`/pos`)
Pengguna dengan izin `sales.create` (Kasir, Supervisor, atau Admin) dapat mengakses antarmuka kasir melalui navigasi sidebar menu **POS Kasir** atau URL `/pos`. Antarmuka didesain khusus mode layar penuh (*full landscape view*) dengan dua kolom:
- **Panel Kiri (Katalog & Input Barcode):** Area pencarian cepat produk, pemindaian barcode USB, filter kategori, dan kartu katalog produk.
- **Panel Kanan (Keranjang & Pembayaran):** Rincian item belanjaan, pengaturan kuantitas, input diskon per item & diskon nota, ringkasan subtotal & grand total real-time, serta panel pembayaran tunai.

---

### 11.2 Operasi Barcode Scanner USB HID (Keyboard Wedge)
KasirPro mendukung perangkat pemindai barcode USB standar tanpa perlu instalasi driver tambahan:
1. Masukkan kursor atau pastikan fokus berada pada kotak input **Scan barcode USB [Enter]...**.
2. Arahkan laser scanner ke barcode kemasan barang.
3. Scanner akan mengirimkan deretan karakter kode diikuti penekanan tombol **Enter**.
4. **Hasil Pemindaian:**
   - **Barcode Terdaftar & Aktif:** Produk otomatis ditambahkan ke keranjang belanja (jika sudah ada, kuantitas bertambah +1). Banner konfirmasi hijau muncul dan fokus kursor scanner dipulihkan seketika.
   - **Barcode Tidak Dikenal atau Nonaktif:** Sistem menampilkan banner galat merah (*"Produk dengan barcode [...] tidak ditemukan atau sedang nonaktif"*), tidak menambahkan item ke keranjang, dan fokus kursor langsung dipulihkan agar kasir dapat memindai barang berikutnya tanpa menyentuh mouse.
5. **Pencegahan Item Ganda:** Input teks barcode segera dibersihkan seketika saat event Enter diterima untuk mencegah pemicuan ganda akibat pembacaan berulang yang tidak disengaja.

---

### 11.3 Pencarian Produk Manual & Filter Kategori
Jika barcode pada kemasan rusak atau produk tidak memiliki barcode:
1. **Pencarian Kata Kunci:** Ketikkan nama produk atau kode SKU pada kotak pencarian **Cari nama atau SKU...**. Daftar produk akan disaring secara otomatis dengan jeda *debounce* 300 ms.
2. **Filter Kategori:** Klik tombol kategori (*pill filter*) di bawah bilah pencarian untuk menyaring barang berdasarkan kelompok jenis (misal: *Minuman*, *Makanan Ringan*).
3. **Memilih Barang:** Klik kartu produk yang diinginkan untuk memasukkannya ke dalam keranjang. Produk yang stoknya habis (`available = 0`) otomatis dinonaktifkan (*disabled*) dan tidak dapat diklik.

---

### 11.4 Pengaturan Kuantitas & Diskon Baris Item
1. **Pengaturan Kuantitas:**
   - Gunakan tombol **+** atau **-** pada baris keranjang untuk menambah atau mengurangi kuantitas belanjaan.
   - Atau ketikkan kuantitas secara langsung pada kolom input kuantitas.
   - **Validasi Presisi:** Kuantitas pecahan yang diinputkan akan divalidasi terhadap presisi desimal satuan barang (contoh: produk bersatuan `pcs` hanya menerima angka bulat).
2. **Pemberian Diskon per Item:**
   - Pilih jenis diskon: **Rp** (potongan nominal) atau **%** (potongan persentase).
   - Masukkan nilai diskon pada kolom di sampingnya.
   - Nilai diskon baris dilarang bernilai negatif dan tidak dapat melebihi nilai kotor baris barang tersebut.

---

### 11.5 Diskon Transaksi & Otorisasi Supervisor (Maker-Checker)
1. **Diskon Nota (Transaction Discount):**
   - Pada bagian bawah keranjang, kasir dapat memasukkan diskon transaksi header (pilih format **Rp** atau **%**).
   - Diskon ini akan dialokasikan secara proporsional ke seluruh item belanjaan oleh server secara matematis, dengan selisih pembulatan desimal dialokasikan deterministik ke item bernilai terbesar.
2. **Batas Kewenangan Kasir (*Discount Limits*):**
   - Kasir memiliki batas diskon maksimal standar (default: maksimal 10% atau Rp 50.000 per item, dan maksimal 10% atau Rp 100.000 per transaksi).
3. **Otorisasi Supervisor (*Maker-Checker Modal*):**
   - Apabila kasir memasukkan diskon yang melampaui batas kewenangan, sistem akan menahan proses dan memunculkan jendela sembulan (*modal*) **Otorisasi Diskon Khusus Supervisor**.
   - Supervisor atau Admin harus memilih nama akunnya, memasukkan kata sandi autentikasi yang sah, serta menuliskan alasan pemberian diskon.
   - **Prinsip Maker-Checker:** Kasir **dilarang keras** mengotorisasi diskon khusus untuk transaksinya sendiri.

---

### 11.6 Pembayaran Tunai & Perhitungan Kembalian
1. **Pilihan Akun Kas:** Kasir dapat memilih akun kas penerima uang (default terarah ke *Kas Toko*).
2. **Input Uang Diterima (Tender):**
   - Kasir dapat mengetikkan nominal uang tunai yang diserahkan pelanggan pada kolom **Uang Tunai Diterima (Tender)**.
   - Tersedia tombol cepat: **Uang Pas** (mengisi tepat sebesar Grand Total), **Rp 50.000**, dan **Rp 100.000**.
3. **Perhitungan Kembalian:**
   - Sistem langsung menghitung dan menampilkan nilai kembalian secara mencolok pada kotak **Uang Kembalian**.
   - Jika uang tunai yang diserahkan kurang dari Grand Total, tombol **Selesaikan Transaksi** otomatis dinonaktifkan dengan peringatan bahwa uang pembayaran kurang.
4. **Menyelesaikan Transaksi:**
   - Klik tombol **Selesaikan Transaksi (Tunai)**.
   - Sistem mengirimkan payload terenkripsi beserta header `Idempotency-Key` (UUIDv4) untuk menjamin tidak terjadi transaksi ganda.
   - **Dampak Pembukuan Otomatis:**
     - Saldo persediaan fisik berkurang via mutasi `OUT_SALE`.
     - HPP modal rata-rata tercatat permanen pada item penjualan (`cost_price_snapshot`).
     - Kas masuk dicatat tepat sebesar **Grand Total** (`IN_SALE`), bukan sebesar uang tender.
     - Jendela konfirmasi sukses muncul menampilkan nomor transaksi resmi dan tombol cetak struk.

---

### 11.7 Pencetakan Struk Thermal & Cetak Ulang (Reprint)
1. **Cetak Struk Langsung:**
   - Setelah checkout berhasil, klik tombol **Cetak Bukti Struk**.
   - Dialog cetak peramban (*browser print dialog*) otomatis terbuka.
2. **Ukuran Kertas Thermal:**
   - Sistem mendukung layout thermal **58 mm** (default minimarket) dan **80 mm** (lebar). Pengguna dapat beralih ukuran melalui tautan di bagian atas pratinjau struk.
3. **Snapshot Toko:**
   - Struk menggunakan data identitas toko saat transaksi disahkan (nama toko, alamat, telepon, dan catatan kaki struk), sehingga perubahan profil toko di masa depan tidak mengubah keaslian struk historis.
4. **Cetak Ulang (Reprint):**
   - Struk yang dicetak ulang dari halaman riwayat transaksi secara otomatis menampilkan penanda khusus: `*** SALINAN / REPRINT ***`.
   - **Jaminan Integritas:** Tindakan cetak ulang struk sama sekali tidak memicu mutasi stok atau mutasi kas baru.

---

### 11.8 Melihat Riwayat Transaksi Penjualan (`/sales`)
1. Buka menu **Riwayat Penjualan** (`/sales`).
2. **Kartu Ringkasan KPI:** Menampilkan omset penjualan hari ini, jumlah transaksi selesai hari ini, dan total diskon toko hari ini.
3. **Penyaringan Data:** Riwayat dapat difilter berdasarkan nomor transaksi, rentang tanggal (dari/sampai), dan filter kasir (hanya untuk Admin/Supervisor).
4. **Isolasi Hak Akses (Anti-IDOR):**
   - Kasir biasa hanya dapat melihat riwayat transaksi miliknya sendiri.
   - Supervisor dan Admin yang memiliki izin `sales.view_all` dapat memantau transaksi seluruh kasir.
5. **Rincian Transaksi:** Klik nomor transaksi untuk melihat rincian barang, harga jual, alokasi diskon, HPP modal (khusus Admin), akun kas penerima uang, dan waktu transaksi.

---

## 12. Penjualan Transfer Bank Manual & Reservasi Stok (Tahap 08)

### 12.1 Membuat Checkout Transfer dari POS
1. Buka halaman **Mesin Kasir (POS)** dan masukkan produk ke keranjang melalui scan barcode atau pencarian katalog.
2. Pada panel pembayaran, pilih tab **Transfer Bank (Manual)**.
3. Pilih **Rekening Tujuan** toko yang menerima transfer. Masukkan nomor referensi/bukti transfer jika pelanggan menyediakannya; kolom referensi opsional.
4. Klik **Kirim Transfer & Reservasi Stok**. Server menghitung ulang harga, diskon, dan total; browser tidak menentukan nilai transaksi final.
5. Sistem membuat transaksi berstatus `pending_payment` dengan pembayaran `pending_verification`. Stok fisik tidak dipotong dan kas belum bertambah; kuantitas hanya ditahan sebagai reservasi.
6. Pesanan dan batas waktu reservasi tampil pada konfirmasi. Default TTL 15 menit; selama periode ini `available = on_hand - reserved`.

### 12.2 Memeriksa dan Mengesahkan Transfer (`/sales-transfers/pending`)
Hanya Admin/Supervisor dengan izin `payments.verify_transfer` dapat membuka menu **Verifikasi Transfer**.

1. Buka menu **Penjualan (POS) → Verifikasi Transfer**.
2. Daftar menampilkan nomor transaksi, kasir pembuat, rekening tujuan, referensi, jumlah tagihan, dan countdown batas reservasi.
3. Buka **Verifikasi / Detail** untuk memeriksa item, total, informasi rekening, referensi pelanggan, status reservasi, dan timeline.
4. Cocokkan mutasi rekening dan dana efektif masuk secara manual di layanan/perangkat bank. KasirPro tidak terhubung ke API bank.
5. Jika dana cocok, klik **Verifikasi & Sahkan Pembayaran**. Konfirmasi tindakan. Sistem kemudian memotong on-hand, mengonsumsi reservasi, menyimpan HPP historis, membuat stock movement keluar, mencatat cash movement masuk sebesar grand total, dan menyelesaikan sale secara atomik.
6. Jika dana tidak cocok atau tidak diterima, isi **Catatan Verifikasi / Alasan Penolakan**, lalu klik **Tolak Transfer**. Sale dibatalkan dan stok reservasi dilepas; tidak ada stock-out atau cash-in.

### 12.3 Maker-Checker dan Admin Override
- User yang membuat transaksi transfer tidak dapat memverifikasi transaksinya sendiri, sekalipun mempunyai izin verifikasi. Minta Supervisor atau Admin lain untuk melakukan pemeriksaan.
- Admin yang menjadi requester dapat menggunakan **Admin Override** hanya dengan mengisi alasan tertulis wajib. Alasan dan identitas verifier masuk audit log.
- Supervisor tidak dapat menggunakan Admin Override untuk mengesahkan transaksi sendiri.

### 12.4 Pembatalan Transaksi Transfer Pending
- Kasir dapat membatalkan transaksi transfer miliknya sendiri selama masih `pending_payment`; Supervisor/Admin berizin juga dapat membatalkan.
- Dari detail transaksi, pilih **Batalkan Transaksi Ini** dan konfirmasi. Sistem mencatat alasan, merilis seluruh kuantitas reservasi, memperbarui status, dan menulis audit log.
- Pembatalan tidak membuat stock movement keluar atau cash movement masuk.

### 12.5 Kedaluwarsa dan Transfer Terlambat
- Reservasi default berlaku 15 menit. Scheduler melepaskan stok yang melewati `expires_at` sedikitnya setiap menit. Sale menjadi `cancelled`, pembayaran berstatus `expired`, dan alasan kedaluwarsa tersimpan.
- Jika timer habis sebelum verifikasi, verifikasi ditolak. Transaksi kedaluwarsa **tidak boleh dipaksakan** menjadi `completed`, karena stok yang dilepas mungkin telah dijual ke pelanggan lain.
- Jika pelanggan sudah mentransfer setelah TTL habis, Admin/Supervisor memeriksa stok fisik terlebih dahulu. Bila barang masih tersedia, kasir membuat transaksi baru dengan referensi transfer dan supervisor mengesahkan transaksi baru. Bila stok habis/terjual, ikuti prosedur toko untuk pengembalian dana manual atau barang pengganti; catat tindakan melalui penyesuaian kas yang beralasan dan diaudit. Jangan menghidupkan transaksi kedaluwarsa atau membuat posting sale/stok dari transaksi tersebut.

### 12.6 Status yang Ditampilkan
| Sale status | Payment status | Arti |
|---|---|---|
| `pending_payment` | `pending_verification` | Menunggu pemeriksaan manual; stok direservasi sampai TTL |
| `completed` | `paid` | Transfer diverifikasi; stock-out, cash-in, HPP, dan audit telah diposting |
| `cancelled` | `rejected` | Verifier menolak atau transaksi dibatalkan; reservasi dilepas |
| `cancelled` | `expired` | TTL habis; reservasi dilepas otomatis dan verifikasi tidak dapat dipaksakan |


