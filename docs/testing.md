# Panduan & Strategi Pengujian (Testing Guide & Test Suites)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 06 — Selesai)** |
| Framework Utama | **Pest v3** (berjalan di atas PHPUnit 11) |
| Database Pengujian | MySQL 8.0 InnoDB (`kasirpro_test`) dengan `DatabaseTransactions` |
| Terakhir Ditinjau | 2026-11-18 |
| Hasil Uji Terkini | **168 Tests Passed (732 Assertions, 0 Failures)** |
| Sumber Terkait | `prd.md` (§17), `tests/*` |

---

## 1. Strategi & Lingkungan Pengujian
Pengujian otomatis KasirPro dijalankan menggunakan framework **Pest v3** dengan koneksi ke database pengujian MySQL riil (`kasirpro_test`). Untuk menjaga kebersihan data antar-test dan eksekusi yang cepat, seluruh test fitur menggunakan trait `Illuminate\Foundation\Testing\DatabaseTransactions`.

Setiap domain bisnis krusial (Inventory, Cash Ledger, Purchasing) diuji secara komprehensif melalui tiga lapis pengujian:
1. **Domain Service & Business Logic Tests:** Memverifikasi kalkulasi matematis (BCMath, Moving Weighted Average, Landed Cost Proportional, Cash Balance) dan penolakan pelanggaran aturan bisnis (negative balance, over-receiving, over-paying).
2. **Feature & HTTP Controller Tests:** Memverifikasi otorisasi permission RBAC, validasi form request, status HTTP 200/302/403/409/422, respons JSON API, flash session, dan pencatatan audit log.
3. **Concurrency & Race Condition Tests:** Memverifikasi penguncian baris (`lockForUpdate`), integritas multi-transaksi serentak, serta isolasi ACID database.

---

## 2. Struktur Test Suites As-Built (168 Tests Passed)

```text
tests/
├── Unit/
│   ├── DecimalTest.php                          # 4 tests: presisi bcmath, pembagian, perbandingan
│   ├── ExampleTest.php                          # 1 test: baseline unit test
│   └── MoneyCalculatorTest.php                  # 3 tests: format IDR, kuantitas presisi, alokasi diskon deterministik
└── Feature/
    ├── AuditLogTest.php                         # 2 tests: pencatatan audit lengkap, sanitasi rahasia/password
    ├── Auth/
    │   ├── AccountStatusTest.php                # 2 tests: terminasi sesi user nonaktif, respons API 401
    │   ├── LoginTest.php                        # 7 tests: login valid, generic error, inactive reject, session id, rate limit
    │   ├── LogoutTest.php                       # 2 tests: logout aman, session invalidation
    │   └── PasswordSecurityTest.php             # 3 tests: bcrypt hashing, ubah password mandiri, old password check
    ├── AuthorizationTest.php                    # 6 tests: matriks izin Admin, Supervisor, Kasir, IDOR ownership gate, 403 kustom
    ├── ExampleTest.php                          # 1 test: status respons login
    ├── FoundationTest.php                       # 4 tests: proteksi rute beranda, header X-Request-Id, correlation id
    ├── UserManagementTest.php                   # 8 tests: CRUD user, role assignment, email unik, toggle status, self-deactivate block
    ├── Master/
    │   ├── CategoryTest.php                     # 5 tests: CRUD kategori, slug unik, proteksi hard delete berelasi
    │   ├── UnitTest.php                         # 6 tests: CRUD satuan, simbol unik, presisi desimal 0-3
    │   ├── ProductTest.php                      # 9 tests: normalisasi SKU, barcode unik, audit histori harga, proteksi delete
    │   ├── SupplierTest.php                     # 5 tests: CRUD supplier, kode unik, isolasi supplier nonaktif
    │   ├── ExpenseCategoryTest.php              # 5 tests: CRUD kategori biaya, kode unik, proteksi delete berelasi
    │   └── StoreProfileTest.php                 # 5 tests: pengaturan identitas toko, upload logo toko, lebar struk 58/80mm
    ├── Inventory/
    │   ├── InventoryLedgerTest.php              # 10 tests: ledger append-only, penolakan stok negatif, validasi available stock
    │   ├── CostingServiceTest.php               # 8 tests: formula MWA, perlakuan stok nol/kosong, histori HPP
    │   ├── InventoryControllerTest.php          # 8 tests: dashboard stok, kartu stok, filter low-stock, endpoint rekonsiliasi
    │   └── InventoryConcurrencyTest.php         # 3 tests: penguncian baris persediaan, pencegahan race condition stok
    ├── Cash/
    │   ├── CashMovementServiceTest.php          # 11 tests: saldo pembukaan, inflow, outflow, penolakan saldo negatif, reversal kas
    │   ├── CashAccountControllerTest.php        # 9 tests: buku kas UI, otorisasi RBAC, filter mutasi, rekonsiliasi API
    │   └── CashConcurrencyTest.php              # 2 tests: penguncian baris kas, pencegahan cerukan saldo serentak
    └── Purchasing/
        ├── PurchaseFlowTest.php                 # 14 tests: siklus lengkap PO draft, confirm, partial receipt, rejected items,
        │                                        # landed cost, moving average recalculation, supplier payment, rollback atomik
        ├── PurchaseControllerTest.php           # 9 tests: otorisasi RBAC PO, form penerimaan barang, form pembayaran utang, API JSON
        └── PurchaseConcurrencyTest.php          # 3 tests: penerimaan paralel tanpa over-receiving, pembayaran paralel tanpa over-paying
```

---

## 3. Cara Menjalankan Pengujian

```bash
# Menjalankan seluruh pengujian otomatis (168 tests)
./vendor/bin/pest

# Menjalankan pengujian domain Purchasing saja (Tahap 06)
./vendor/bin/pest tests/Feature/Purchasing/

# Menjalankan spesifik test alur transaksi pembelian
./vendor/bin/pest --filter=PurchaseFlowTest

# Menjalankan pengujian konkurensi pembelian
./vendor/bin/pest --filter=PurchaseConcurrencyTest

# Menjalankan seluruh pengujian master data & inventaris
./vendor/bin/pest tests/Feature/Master/
./vendor/bin/pest tests/Feature/Inventory/
```

---

## 4. Matriks Traceability Kebutuhan ke Pengujian (Tahap 01 s.d. Tahap 06)

| Kebutuhan PRD | Deskripsi Fungsional | Berkas Test Penjamin |
|---|---|---|
| `FR-AUTH-01` | Login email/password, rate limiting, akun nonaktif ditolak | `tests/Feature/Auth/LoginTest.php` |
| `FR-AUTH-02` | Logout aman dan pembatalan sesi aktif | `tests/Feature/Auth/LogoutTest.php` |
| `FR-AUTH-03` | Admin mengelola pengguna, peran, status aktif | `tests/Feature/UserManagementTest.php` |
| `FR-AUTH-04` | Backend authorization (403 default-deny, IDOR check) | `tests/Feature/AuthorizationTest.php` |
| `FR-AUTH-05` | Profil pengguna mandiri dan ubah kata sandi | `tests/Feature/Auth/PasswordSecurityTest.php` |
| `FR-AUD-01` | Pencatatan audit trail otomatis tanpa menyimpan rahasia | `tests/Feature/AuditLogTest.php` |
| `FR-CAT-01` | Kategori produk dengan slug unik dan proteksi integritas | `tests/Feature/Master/CategoryTest.php` |
| `FR-CAT-02` | Satuan unit dengan presisi desimal 0 s.d. 3 | `tests/Feature/Master/UnitTest.php` |
| `FR-CAT-03` | Produk dengan SKU normalisasi, barcode unik, & riwayat harga | `tests/Feature/Master/ProductTest.php` |
| `FR-CAT-04` | Pemasok dengan kode unik & pencegahan PO ke supplier nonaktif | `tests/Feature/Master/SupplierTest.php` |
| `FR-CAT-05` | Kategori biaya operasional toko | `tests/Feature/Master/ExpenseCategoryTest.php` |
| `FR-CAT-06` | Profil toko dan konfigurasi struk thermal | `tests/Feature/Master/StoreProfileTest.php` |
| `FR-INV-01` | Buku besar persediaan append-only & saldo stok atomik | `tests/Feature/Inventory/InventoryLedgerTest.php` |
| `FR-INV-02` | Kalkulasi Moving Weighted Average (MWA) HPP | `tests/Feature/Inventory/CostingServiceTest.php` |
| `FR-INV-03` | Dashboard stok, peringatan stok menipis, kartu stok | `tests/Feature/Inventory/InventoryControllerTest.php` |
| `FR-CSH-01` | Buku besar kas, saldo awal pembukaan, mutasi in/out | `tests/Feature/Cash/CashMovementServiceTest.php` |
| `FR-CSH-02` | Buku kas antarmuka web, filter transaksi, & reversal kas | `tests/Feature/Cash/CashAccountControllerTest.php` |
| `FR-PUR-01` | Purchase Order: draft, konfirmasi, pembatalan dengan alasan wajib | `tests/Feature/Purchasing/PurchaseFlowTest.php`, `PurchaseControllerTest.php` |
| `FR-PUR-02` | Goods Receiving: penerimaan parsial, barang ditolak (*rejected*), alokasi Landed Cost | `tests/Feature/Purchasing/PurchaseFlowTest.php` |
| `FR-PUR-03` | Sinkronisasi stok otomatis (`IN_PURCHASE`) dan pembaruan HPP MWA saat penerimaan | `tests/Feature/Purchasing/PurchaseFlowTest.php` |
| `FR-PUR-04` | Pembayaran utang pemasok terikat pada barang diterima (*accepted goods*) & terbit mutasi kas | `tests/Feature/Purchasing/PurchaseFlowTest.php`, `PurchaseControllerTest.php` |
| `FR-PUR-05` | Pembalikan (*reversal*) pembayaran pemasok dengan pengembalian saldo kas atomik | `tests/Feature/Purchasing/PurchaseFlowTest.php` |
| Concurrency | Integritas transaksi paralel PO receiving & supplier payment | `tests/Feature/Purchasing/PurchaseConcurrencyTest.php` |
