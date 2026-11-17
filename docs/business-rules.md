# Aturan Bisnis & Logika Sistem (Business Rules Register)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 01, 02 & 03)** |
| Dokumen Induk | `prd.md` (§6, §8, §22.1) |
| Terakhir Ditinjau | 2026-11-17 |

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
