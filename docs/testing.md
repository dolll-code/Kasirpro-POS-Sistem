# Panduan & Strategi Pengujian (Testing Guide & Test Suites)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 02 — Selesai)** |
| Framework Utama | **Pest v3** (berjalan di atas PHPUnit 11/12) |
| Database Pengujian | MySQL 8.0 InnoDB (`kasirpro_test`) dengan `DatabaseTransactions` |
| Terakhir Ditinjau | 2026-11-17 |
| Sumber Terkait | `prd.md` (§17), `tests/*` |

---

## 1. Strategi & Lingkungan Pengujian
Pengujian otomatis KasirPro dijalankan menggunakan framework **Pest v3** dengan koneksi ke database pengujian MySQL riil (`kasirpro_test`). Untuk menjaga kebersihan data antar-test dan eksekusi yang cepat, seluruh test fitur menggunakan trait `Illuminate\Foundation\Testing\DatabaseTransactions`.

---

## 2. Struktur Test Suites As-Built (43 Tests Passed)

```text
tests/
├── Unit/
│   ├── DecimalTest.php                # 4 tests: presisi bcmath, pembagian, perbandingan
│   ├── ExampleTest.php                # 1 test: baseline unit test
│   └── MoneyCalculatorTest.php        # 3 tests: format IDR, kuantitas presisi, alokasi diskon
└── Feature/
    ├── AuditLogTest.php               # 2 tests: pencatatan audit lengkap, sanitasi rahasia/password
    ├── Auth/
    │   ├── AccountStatusTest.php      # 2 tests: terminasi sesi user nonaktif, respons API 401
    │   ├── LoginTest.php              # 7 tests: login valid, generic error, inactive reject, session id, rate limit
    │   ├── LogoutTest.php             # 2 tests: logout aman, session invalidation
    │   └── PasswordSecurityTest.php   # 3 tests: bcrypt hashing, ubah password mandiri, old password check
    ├── AuthorizationTest.php          # 6 tests: matriks izin Admin, Supervisor, Kasir, IDOR ownership gate, 403 kustom
    ├── ExampleTest.php                # 1 test: status respons login
    ├── FoundationTest.php             # 4 tests: proteksi rute beranda, header X-Request-Id, correlation id
    └── UserManagementTest.php         # 8 tests: CRUD user, role assignment, email unik, toggle status, self-deactivate block
```

---

## 3. Cara Menjalankan Pengujian

```bash
# Menjalankan seluruh pengujian otomatis
php artisan test

# Menjalankan grup pengujian tertentu
php artisan test --filter=LoginTest
php artisan test --filter=AuthorizationTest
php artisan test --filter=UserManagementTest
php artisan test --filter=AuditLogTest

# Menjalankan pengujian unit kalkulasi keuangan
php artisan test tests/Unit/
```

---

## 4. Matriks Traceability Kebutuhan ke Pengujian

| Kebutuhan PRD | Deskripsi Fungsional | Berkas Test Penjamin |
|---|---|---|
| `FR-AUTH-01` | Login email/password, rate limiting, akun nonaktif ditolak | `tests/Feature/Auth/LoginTest.php` |
| `FR-AUTH-02` | Logout aman dan pembatalan sesi aktif | `tests/Feature/Auth/LogoutTest.php` |
| `FR-AUTH-03` | Admin mengelola pengguna, peran, status aktif | `tests/Feature/UserManagementTest.php` |
| `FR-AUTH-04` | Backend authorization (403 default-deny, IDOR check) | `tests/Feature/AuthorizationTest.php` |
| `FR-AUTH-05` | Profil pengguna mandiri dan ubah kata sandi | `tests/Feature/Auth/PasswordSecurityTest.php` |
| `FR-AUD-01` | Pencatatan audit trail otomatis tanpa menyimpan rahasia | `tests/Feature/AuditLogTest.php` |
