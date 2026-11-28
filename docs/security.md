# Panduan Keamanan Sistem (Security Requirements & Controls)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 02 — Selesai)** |
| Standar Kepatuhan | OWASP Top 10, PRD §14, ISO 27001 Reference |
| Terakhir Ditinjau | 2026-11-17 |
| Sumber Terkait | `prd.md` (§14, §15), `app/Services/AuditLogger.php`, `app/Http/Middleware/*` |

---

## 1. Ikhtisar Keamanan

KasirPro dirancang dengan prinsip **Defense in Depth** dan **Default Deny**. Karena sistem mengelola transaksi uang riil, inventori barang berharga, dan data staf toko, kontrol keamanan diterapkan secara ketat di backend server Laravel, bukan mengandalkan penyembunyian tombol di antarmuka browser.

---

## 2. Pengendalian Kredensial & Autentikasi

### 2.1 Password Hashing & Penyimpanan Aman
- Seluruh kata sandi dienkripsi menggunakan algoritma **Bcrypt** (`rounds: 12`) bawaan Laravel.
- Kata sandi tidak pernah disimpan dalam bentuk teks mentah (*plaintext*) di database maupun log.

### 2.2 Pencegahan Enumerasi Akun (Account Enumeration)
- Pesan kesalahan login diformulasikan secara generik:
  > *"Email atau kata sandi yang Anda masukkan tidak sesuai."*
- Waktu respons pengujian kredensial dinormalisasi untuk mencegah *timing attack* yang mengidentifikasi keberadaan alamat email staf.

### 2.3 Pembatasan Laju Percobaan Login (Brute-Force Rate Limiting)
- Endpoint login (`POST /login`) dilindungi rate limiter:
  - Maksimal **5 percobaan gagal per menit** untuk setiap kombinasi email dan alamat IP.
  - Percobaan keenam akan ditolak dengan respons HTTP 429 / error throttled dan sisa waktu tunggu dalam detik.
  - Setiap insiden rate limit otomatis dicatat ke tabel `audit_logs` dengan aksi `auth.login_rate_limited`.

### 2.4 Keamanan Sesi & Pembatalan Aktif (Session Invalidation)
- **Regenerasi Sesi:** Sesi diperbarui setiap kali login berhasil dan setelah perubahan kata sandi mandiri (`$request->session()->regenerate()`).
- **Terminasi Sesi Akun Nonaktif:** Middleware `EnsureAccountIsActive` memverifikasi status akun pengguna pada setiap request. Jika pengguna dinonaktifkan oleh Admin, sesi aktifnya langsung dibatalkan seketika, mencegah staf yang diberhentikan tetap melakukan transaksi kasir.

---

## 3. Otorisasi & Kontrol Akses Granular (Anti-IDOR & Default Deny)

### 3.1 Prinsip Default Deny
- Setiap rute sistem di bawah grup `auth` mewajibkan izin spesifik via middleware `permission:<code_name>`.
- Pengguna tanpa izin menerima respons **HTTP 403 Forbidden**. Halaman 403 tidak mengungkap informasi sensitif dan menyertakan *Request ID* untuk pelacakan audit.

### 3.2 Pencegahan Insecure Direct Object Reference (Anti-IDOR)
- **Kepemilikan Transaksi Penjualan:**
  - Kasir hanya berhak melihat transaksi yang diproses oleh dirinya sendiri (`sales.view_own`).
  - Supervisor dan Admin dapat melihat seluruh transaksi toko melalui izin `sales.view_all`.
  - Gate otorisasi `view-sale` memvalidasi kepemilikan record sebelum data transaksi disajikan ke antarmuka.

---

## 4. Perlindungan Jejak Audit & Sanitasi Rahasia (Audit Hygiene)

Class `App\Services\AuditLogger` bertugas merekam setiap mutasi data sensitif dengan aturan sanitasi ketat:

### 4.1 Kunci yang Wajib Disanitasi (Redaction Policy)
Nilai dari parameter berikut otomatis diganti dengan teks `[REDACTED]` sebelum disimpan ke kolom `old_values` atau `new_values` tabel `audit_logs`:
- `password`, `password_confirmation`, `current_password`, `new_password`, `old_password`
- `remember_token`, `token`, `secret`, `_token`, `authorization`
- `credit_card`, `cvv`

### 4.2 Pelacakan Jejak Transaksi (Correlation ID)
Setiap mutasi log audit menyertakan:
- `correlation_id` yang diambil dari header HTTP `X-Request-Id` (UUIDv4).
- `ip_address` dan `user_agent` pemanggil.
- ID pengguna pelaku aksi (*Actor User ID*).

## 5. Ekspor Laporan (PDF/XLSX)

### 5.1 Perlindungan file
- File ekspor **wajib** berada pada disk privat. Jangan memasang symlink/URL statis menuju `storage/app/private/exports`.
- Route download memeriksa kepemilikan record (`report_exports.user_id`), permission `reports.export`, status `completed`, expiry, dan keberadaan file.
- Nama file menggunakan UUID ekspor, bukan input pengguna, untuk mencegah path traversal.
- Jangan mencatat isi file atau secret pada log/audit.

### 5.2 Formula injection
- Cell teks yang diawali `=`, `+`, `-`, `@`, tab, atau carriage return diberi prefix apostrof sebelum ditulis ke XLSX.
- Angka ditulis sebagai tipe numeric, tanggal sebagai date, sehingga data tidak berubah menjadi formula.

### 5.3 Dependency
- `dompdf/dompdf ^3.1.6` dipilih setelah advisory versi 3.1.0 terblokir; versi 3.1.6 tidak memiliki advisory saat instalasi.
- `phpoffice/phpspreadsheet ^5.12.0` dipilih karena versi 4.1/5.8 terkena advisory aktual.
- Keduanya lisensi permisif (LGPL-2.1 untuk Dompdf, MIT untuk PhpSpreadsheet) dan kompatibel PHP 8.2 + Laravel 12.
- `composer audit` saat implementasi masih mencatat advisory pada dependency Symfony bawaan framework (http-kernel, mailer, routing), bukan pada package ekspor; patch framework harus mengikuti production gate `docs/deployment.md`.
