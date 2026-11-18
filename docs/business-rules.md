# Aturan Bisnis & Logika Sistem (Business Rules Register)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 01, 02, 03, 04 & 05)** |
| Dokumen Induk | `prd.md` (§6, §8, §22.1) |
| Terakhir Ditinjau | 2026-11-18 |

Dokumen ini mendefinisikan seluruh invariant dan aturan bisnis resmi aplikasi KasirPro menggunakan konvensi ID stabil yang dapat ditelusuri ke automated test suite.

---

## 1. Domain Autentikasi & Otorisasi (`BR-AUTH-*`)

| Rule ID | Nama Aturan | Definisi Perilaku & Invariant | Pengujian Otomatis |
|---|---|---|---|
| **BR-AUTH-01** | Kredensial & Enkripsi Kata Sandi | Kata sandi wajib di-hash menggunakan algoritma Bcrypt dengan cost factor yang memadai. Kata sandi tidak pernah disimpan dalam bentuk plain-text atau dapat didekripsi kembali. | `PasswordSecurityTest` |
| **BR-AUTH-02** | Pencegahan Enumerasi Akun | Respons kegagalan autentikasi login mengembalikan pesan galat seragam (*"Email atau kata sandi yang Anda masukkan tidak sesuai."*) tanpa mengungkap keberadaan email di basis data. | `LoginTest` |
| **BR-AUTH-03** | Pembatasan Laju Login (Rate Limiting) | Percobaan login dibatasi maksimal 5 kali kegagalan per menit per kombinasi email & IP address. Permintaan berikutnya diblokir dengan kode HTTP 429. | `LoginTest` |
| **BR-AUTH-04** | Invalidasi Sesi Akun Nonaktif | Pengguna berstatus `is_active = false` dicegah login. Jika akun dinonaktifkan oleh administrator saat memiliki sesi login aktif, sesi langsung diputus secara paksa pada request berikutnya. | `AccountStatusTest` |
| **BR-AUTH-05** | Regenerasi ID Sesi | Sesi ID wajib diregenerasi segera setelah login berhasil dan saat pembaruan kata sandi untuk mencegah eksploitasi *Session Fixation*. | `LoginTest`, `PasswordSecurityTest` |
| **BR-AUTH-06** | Kepemilikan Transaksi Kasir (Anti-IDOR) | Kasir hanya memiliki izin melihat transaksi miliknya sendiri (`sales.view_own`). Izin `sales.view_all` diperlukan untuk melihat seluruh transaksi toko. | `AuthorizationTest` |
| **BR-AUTH-07** | Perlindungan Akun Sendiri | Pengguna dilarang menonaktifkan atau menghapus akun miliknya sendiri pada antarmuka manajemen pengguna. | `UserManagementTest` |

---

## 2. Domain Audit Trail (`BR-AUD-*`)

| Rule ID | Nama Aturan | Definisi Perilaku & Invariant | Pengujian Otomatis |
|---|---|---|---|
| **BR-AUD-01** | Metadata Jejak Audit Lengkap | Setiap mutasi entitas sensitif mencatat: `actor_id`, `action`, `module`, `reference`, `ip_address`, `user_agent`, `correlation_id` (`X-Request-Id`), serta perbandingan `old_values` dan `new_values`. | `AuditLogTest` |
| **BR-AUD-02** | Sanitasi Rekursif Data Rahasia | Log audit secara otomatis dan rekursif menghapus kunci sensitif seperti `password`, `token`, `secret`, `authorization`, dan `remember_token`. | `AuditLogTest` |

---

## 3. Domain Master Data & Katalog (`BR-MASTER-*`)

| Rule ID | Nama Aturan | Definisi Perilaku & Invariant | Pengujian Otomatis |
|---|---|---|---|
| **BR-MASTER-01** | Normalisasi & Keunikan SKU | Kode SKU produk bersifat unik di seluruh sistem setelah dinormalisasi (dihilangkan spasi di awal/akhir dan dikonversi ke huruf kapital). | `ProductTest` |
| **BR-MASTER-02** | Format Barcode & Karakter Nol Awal | Barcode disimpan bertipe string nullable unik. Kode barcode yang memiliki digit `0` di awal (contoh: `001234567890`) wajib dipertahankan utuh dan tidak boleh dikonversi ke numerik/integer. Beberapa produk diizinkan memiliki barcode kosong (`NULL`). | `ProductTest` |
| **BR-MASTER-03** | Pemisahan Katalog & Buku Besar Persediaan | Formulir produk dilarang keras memiliki kolom untuk mengedit saldo stok fisik (*on-hand stock*) atau HPP rata-rata bergerak (*moving average cost*) secara langsung. Saldo dan HPP hanya terbentuk via dokumen pergerakan stok resmi. | `ProductTest` |
| **BR-MASTER-04** | Penegakan Presisi Desimal Satuan | Presisi pecahan kuantitas satuan bernilai antara 0 hingga 3 digit desimal (`0` untuk satuan bulat seperti Pcs/Pack; `1-3` untuk satuan timbangan/takaran seperti Kg/Liter). Kuantitas pecahan yang melampaui presisi satuan ditolak. | `UnitTest` |
| **BR-MASTER-05** | Kondisi Produk Layak Jual (*Sellable*) | Produk hanya dapat dicari dan dijual di POS Kasir jika: produk aktif (`is_active = 1`), kategori terkait aktif, dan satuan terkait aktif. | `ProductTest` |
| **BR-MASTER-06** | Jejak Riwayat Perubahan Harga Jual | Setiap pembaruan kolom `selling_price` pada master produk otomatis mencatat satu entitas baru pada `product_price_histories` dengan mencatat harga lama, harga baru, pelaku perubahan, waktu efektif, dan alasan perubahan. | `ProductPriceHistoryTest` |
| **BR-MASTER-07** | Larangan Pemesanan Pemasok Nonaktif | Pemasok berstatus nonaktif (`is_active = false`) dilarang dipilih pada pembuatan dokumen Purchase Order (PO) baru. | `SupplierTest` |
| **BR-MASTER-08** | Larangan Angka Finansial & Stok Negatif | Harga jual produk, batas stok minimum, dan saldo awal akun kas/bank dilarang bernilai negatif (&ge; 0). | `ProductTest`, `CashAccountTest` |
| **BR-MASTER-09** | Kunci Saldo Pembukaan Akun Kas/Bank | Saldo awal akun kas atau bank hanya diinput saat pembuatan akun baru dan tidak dapat diubah langsung pada form edit demi menjaga integritas rekonsiliasi kas. | `CashAccountTest` |
| **BR-MASTER-10** | Larangan Hapus Data Terikat (*Referential Guard*) | Entitas master (kategori, satuan, produk, pemasok, akun kas) yang telah memiliki relasi referensi ke transaksi atau riwayat dilarang dihapus permanen (`ON DELETE RESTRICT` / HTTP 409 Conflict). | `CategoryTest`, `UnitTest`, `ProductTest`, `CashAccountTest` |
| **BR-MASTER-11** | Profil Tunggal & Standar Lebar Struk | Sistem toko beroperasi dengan satu profil aktif (DEC-01) dengan dukungan printer thermal kasir ukuran 58 mm (standar 32 kolom) atau 80 mm (lebar 48 kolom). | `StoreProfileTest` |

---

## 4. Domain Persediaan & Stok (`BR-STOCK-*`)

| Rule ID | Nama Aturan | Definisi Perilaku & Invariant | Pengujian Otomatis |
|---|---|---|---|
| **BR-STOCK-01** | Proyeksi Saldo Terisolasi per Produk | Setiap produk memiliki tepat satu baris `inventory_balances`. Saldo fisik (`quantity_on_hand`) dan cadangan (`quantity_reserved`) wajib non-negatif (&ge; 0). Nilai cadangan tidak boleh melampaui saldo fisik (`reserved <= on_hand`). | `InventoryServiceTest`, `InventoryConcurrencyTest` |
| **BR-STOCK-02** | Buku Besar Persediaan Bersifat Kekal (*Append-Only*) | Seluruh mutasi masuk dan keluar dicatat pada tabel `stock_movements`. Baris mutasi persediaan dilarang keras diubah (*update*) maupun dihapus (*delete*). Koreksi operasional wajib dilakukan melalui dokumen mutasi penyesuaian baru. | `InventoryServiceTest` |
| **BR-STOCK-03** | Pencegahan Saldo Stok Tersedia Negatif | Kuantitas tersedia dihitung sebagai `available = on_hand - reserved`. Permintaan pengurangan stok atau reservasi yang melebihi stok tersedia ditolak seketika dengan `InsufficientStockException` (HTTP 409) tanpa mengubah saldo proyeksi. | `InventoryServiceTest`, `InventoryConcurrencyTest` |
| **BR-STOCK-04** | Penahanan Stok Reservasi Transaksi (*Stock Hold*) | Checkout pembayaran transfer manual menahan stok fisik ke dalam status `quantity_reserved`. Stok tidak dipotong secara fisik sampai verifikasi pembayaran disahkan oleh Supervisor/Admin. Pembatalan/kedaluwarsa melepaskan reservasi kembali ke saldo tersedia. | `InventoryServiceTest` |
| **BR-STOCK-05** | Indikator Status Ketersediaan Stok | Kategori status stok dievaluasi secara dinamis: **Habis** (*Out of Stock*) jika `on_hand = 0`; **Menipis** (*Low Stock*) jika `0 < on_hand <= minimum_stock`; **Tersedia** (*Available*) jika `on_hand > minimum_stock`. | `InventoryServiceTest` |
| **BR-STOCK-06** | Penguncian Baris Pesimistik (*Pessimistic Locking*) | Seluruh mutasi persediaan dieksekusi di dalam transaksi database (`DB::transaction`) dengan mengunci baris saldo menggunakan klausa `SELECT ... FOR UPDATE` untuk mencegah kondisi *race condition* dan penjualan berlebih saat lonjakan kasir. | `InventoryConcurrencyTest` |
| **BR-STOCK-07** | Rekonsiliasi Saldo terhadap Buku Besar | Saldo persediaan pada `inventory_balances` harus 100% cocok dengan hasil kalkulasi akumulasi audit: `sum(quantity_in) - sum(quantity_out)`. Sistem menyediakan fungsi audit `reconcile()` untuk memverifikasi konsistensi saldo. | `InventoryServiceTest`, `InventoryControllerTest` |

---

## 5. Domain Kalkulasi HPP Rata-Rata Bergerak (`BR-COST-*`)

| Rule ID | Nama Aturan | Definisi Perilaku & Invariant | Pengujian Otomatis |
|---|---|---|---|
| **BR-COST-01** | Rumus Moving Weighted Average Cost (MAC) | HPP baru dihitung secara otomatis saat penerimaan barang disahkan (*receipt accepted*) menggunakan rumus matematika desimal presisi tinggi: `new_average_cost = ((Q × C) + (q_in × c_in)) / (Q + q_in)`. Dilarang memakai tipe bawaan floating-point. | `MovingAverageCostTest` |
| **BR-COST-02** | Snapshot HPP Penjualan (COGS Isolation) | Transaksi penjualan mengambil snapshot nilai rata-rata HPP aktif saat transaksi disahkan. Perubahan harga beli di masa depan pada penerimaan barang baru tidak akan mengubah nilai HPP historis yang sudah tercatat pada item penjualan. | `MovingAverageCostTest` |
| **BR-COST-03** | Penyetelan HPP Saldo Nol | Apabila transaksi penjualan menyebabkan saldo fisik menjadi nol (`on_hand = 0`), nilai `average_cost` pada saldo proyeksi disetel menjadi `0.00`, namun riwayat audit HPP sebelumnya tetap dipertahankan utuh pada `product_cost_histories`. | `MovingAverageCostTest` |
| **BR-COST-04** | Pemulihan HPP Retur Layak Jual | Retur penjualan dengan kondisi barang layak jual (*resellable*) menambah kembali stok fisik dan menghitung ulang HPP rata-rata menggunakan HPP snapshot historis saat penjualan: `C_new = ((Q × C) + (r × hist_cost)) / (Q + r)`. | `MovingAverageCostTest` |
| **BR-COST-05** | Penanganan Retur Barang Rusak (*Damaged Return*) | Retur dengan kondisi rusak (*damaged*) tidak menambah stok fisik yang tersedia dan tidak membalikkan nilai HPP persediaan. Nilai retur rusak dicatat secara terpisah sebagai kerugian operasional (*damaged return loss*). | `MovingAverageCostTest` |

---

## 6. Domain Penomoran Dokumen Transaksi (`BR-SEQ-*`)

| Rule ID | Nama Aturan | Definisi Perilaku & Invariant | Pengujian Otomatis |
|---|---|---|---|
| **BR-SEQ-01** | Penomoran Dokumen Atomik & Anti-Duplikasi | Penomoran dokumen transaksi toko (`TRX`, `PO`, `RCV`, `RET`, `EXP`, `SO`, `ADJ`) dikelola secara atomik melalui tabel `document_sequences` dengan penguncian baris (`lockForUpdate`). Format baku: `{KODE}-{YYYYMMDD}-{00001}` (5 digit padding). Dilarang memakai `MAX(id)+1`. | `DocumentSequenceTest`, `InventoryConcurrencyTest` |

---

## 7. Domain Manajemen Kas & Buku Besar Kas (`BR-CASH-*`)

| Rule ID | Nama Aturan | Definisi Perilaku & Invariant | Pengujian Otomatis |
|---|---|---|---|
| **BR-CASH-01** | Formula Saldo Kas & Bank | Saldo akun kas/bank selalu memenuhi persamaan audit buku besar: `Balance = Opening_Balance + Total_Inflows - Total_Outflows`. Setiap mutasi memperbarui `current_balance` pada `cash_accounts` secara sinkron. | `CashMovementServiceTest`, `CashAccountControllerTest` |
| **BR-CASH-02** | Larangan Mutasi Kas Negatif & Cerukan (*Non-Negative Guard*) | Transaksi pengeluaran kas (*outflow*) yang melebihi saldo kas tersedia (`current_balance`) wajib ditolak seketika dengan `InsufficientCashBalanceException` (HTTP 409 Conflict). Saldo kas dan nilai pergerakan kas dilarang keras bernilai negatif (&ge; 0). | `CashMovementServiceTest`, `CashConcurrencyTest` |
| **BR-CASH-03** | Buku Besar Kas Bersifat Kekal (*Append-Only Cash Ledger*) | Seluruh mutasi kas dicatat pada tabel `cash_movements`. Baris mutasi kas dilarang keras diubah (*update*) maupun dihapus (*delete*). Perubahan atau pembatalan transaksi wajib dilakukan dengan membuat entri pembalik (*reversal*) baru. | `CashMovementServiceTest` |
| **BR-CASH-04** | Saldo Awal Terotorisasi Tunggal (*Single Authorized Opening Balance*) | Setiap akun kas/bank hanya diizinkan memiliki tepat satu mutasi saldo awal bertipe `OPENING_BALANCE` yang dicatat saat pembuatan akun. Percobaan pencatatan ulang ditolak dengan `DuplicateCashMovementException`. Saldo awal historis dilarang diedit langsung. | `CashMovementServiceTest`, `CashAccountTest` |
| **BR-CASH-05** | Jaminan Idempotensi Transaksi Sumber (*Source Uniqueness Guarantee*) | Setiap satu dokumen pembayaran transaksi (misal: pembayaran penjualan kasir, pembayaran utang pemasok, biaya operasional) hanya dapat menghasilkan tepat satu mutasi kas melalui constraint unik basis data `(source_type, source_id)`. | `CashMovementServiceTest`, `CashConcurrencyTest` |
| **BR-CASH-06** | Prosedur Pembalikan Transaksi (*Compensating Cash Reversal*) | Pembalikan transaksi kas (*reversal*) menghasilkan baris mutasi baru bertipe `REVERSAL` dengan arah berlawanan (`in` &rarr; `out` atau `out` &rarr; `in`), mewajibkan alasan koreksi, terhubung ke transaksi asli via `reversal_of_id`, dan diproteksi unik agar tidak dapat dibalikkan dua kali. | `CashReversalTest`, `CashAccountControllerTest` |
| **BR-CASH-07** | Penguncian Baris Kas Pesimistik (*Pessimistic Locking & Audit Consistency*) | Seluruh mutasi kas dieksekusi dalam transaksi basis data atomik (`DB::transaction`) dengan mengunci baris akun kas (`lockForUpdate`). Sistem menyediakan fungsi `reconcile()` untuk memvalidasi konsistensi 100% antara saldo terproyeksi dengan penjumlahan audit ledger. | `CashMovementServiceTest`, `CashConcurrencyTest`, `CashAccountControllerTest` |

