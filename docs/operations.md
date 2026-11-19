# Panduan Operasional & Pemeliharaan Sistem (Operations & Maintenance Manual)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 08 — Selesai)** |
| Dokumen Induk | `prd.md` (§9.5, §15, §18, §19, §21) |
| Terakhir Ditinjau | 2026-11-19 |
| Target Pembaca | System Administrator, DevOps, Lead Engineer, Store Manager |

---

## 1. Pendahuluan
Dokumen operasional ini mengatur jadwal eksekusi pemeliharaan otomatis (*scheduled tasks*), prosedur manajemen transaksi tertunda, penanganan transfer bank kedaluwarsa (*overdue reservation handling*), rekonsiliasi buku besar stok & kas, serta panduan pemulihan sistem (*runbook*).

---

## 2. Jadwal Tugas Otomatis (Scheduled Tasks / Cron)

Sistem menggunakan Laravel Scheduler yang dijalankan oleh daemon sistem operasi setiap menit:
```bash
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

### 2.1 Pembersihan Reservasi Stok Kedaluwarsa (`sales:expire-reservations`)
- **Perintah Artisan:**
  ```bash
  php artisan sales:expire-reservations
  ```
- **Frekuensi:** Dijalankan setiap menit (`everyMinute()`).
- **Tujuan Operasional:** Memeriksa seluruh baris pada tabel `stock_reservations` yang berstatus `active` dan telah melewati batas waktu kedaluwarsa (`expires_at <= now()`).
- **Tindakan Otomatis:**
  1. Mengunci baris reservasi secara atomik (`lockForUpdate`).
  2. Mengubah status reservasi menjadi `expired` dan mengisi timestamp `released_at`.
  3. Mengembalikan kuantitas yang ditahan (`quantity_reserved`) ke dalam saldo tersedia (`available_quantity`) pada `inventory_balances` secara atomik.
  4. Memperbarui status penjualan menjadi `cancelled` dan status pembayaran menjadi `expired`.
  5. Mencatat log kejadian ke `audit_logs` dengan aksi `sale.reservation_expired`.

---

## 3. Penanganan Transaksi Transfer Kedaluwarsa & Dispute (Runbook)

### 3.1 Mengapa Transaksi Kedaluwarsa Dilarang Dipaksa Selesai?
Sesuai asumsi bisnis PRD (§3.1 DEC-05, §6.4, dan §21.1):
1. Ketika masa berlaku reservasi (default 15 menit) habis, sistem secara otomatis melepas stok barang kembali ke display toko untuk mencegah *dead inventory* atau penahanan fiktif.
2. Barang yang telah dirilis berpotensi telah dibeli dan dibawa pulang oleh pelanggan tunai lain di meja kasir.
3. Oleh sebab itu, verifier **dilarang keras** memaksakan transaksi transfer yang telah kedaluwarsa menjadi `completed`, karena dapat menyebabkan saldo stok fisik menjadi negatif (*overselling*).

### 3.2 Prosedur Pelanggan Telanjur Mentransfer Setelah Batas Waktu Habis (Dispute Runbook)
Apabila pelanggan terlambat mentransfer dana dan reservasi telah kedaluwarsa:
1. **Pemeriksaan Stok Riil:**
   - Kasir/Supervisor memeriksa apakah barang belanjaan fisik masih berada di meja kasir atau belum terjual ke pelanggan lain.
   - Jika barang masih tersedia: Kasir membuat transaksi checkout baru di meja kasir dengan mencantumkan nomor referensi mutasi bank yang sama, lalu supervisor segera mengesahkan transfer tersebut.
2. **Jika Stok Telanjur Terjual / Habis:**
   - Supervisor menghubungi pelanggan untuk menawarkan barang pengganti atau pengembalian dana (*refund*).
   - Pengembalian dana dilakukan secara manual melalui transfer balik dan dicatat pada mutasi kas penyesuaian non-operasional yang diaudit resmi.

---

## 4. Prosedur Rekonsiliasi Integritas Data

Sistem menyediakan fungsi audit mandiri untuk memvalidasi bahwa proyeksi saldo identik 100% dengan akumulasi audit buku besar.

### 4.1 Rekonsiliasi Saldo Persediaan
- Membandingkan `inventory_balances.quantity_on_hand` terhadap `SUM(quantity_in) - SUM(quantity_out)` pada tabel `stock_movements`.
- Dapat dipanggil melalui endpoint API:
  `GET /api/v1/inventory/{product_id}/reconcile`

### 4.2 Rekonsiliasi Saldo Buku Kas
- Membandingkan `cash_accounts.current_balance` terhadap persamaan:
  `opening_balance + SUM(inflows) - SUM(outflows)` pada tabel `cash_movements`.
- Dapat dipanggil melalui endpoint API:
  `GET /api/v1/cash-accounts/{account_id}/reconcile`

---

## 5. Ringkasan Error Code Operasional

| Kode Galat | Status HTTP | Penyebab | Tindakan Mitigasi |
|---|:---:|---|---|
| `RESERVATION_EXPIRED` | 409 | Supervisor mencoba memverifikasi transfer yang telah melewati batas TTL 15 menit. | Jangan memaksa verifikasi; ikuti panduan dispute pada Bagian 3.2. |
| `IDEMPOTENCY_CONFLICT` | 409 | Pengiriman ulang request dengan Idempotency-Key yang sama namun parameter pesanan berbeda. | Gunakan UUID baru untuk transaksi pesanan berbeda. |
| `INSUFFICIENT_STOCK` | 409 | Stok tersedia (`on_hand - reserved`) tidak mencukupi kuantitas yang diminta. | Periksa stok fisik atau tunggu rilis reservasi transaksi lain. |
| `FORBIDDEN` | 403 | Kasir mencoba memverifikasi transfer miliknya sendiri (pelanggaran Maker-Checker). | Minta supervisor lain memverifikasi, atau gunakan Admin Override dengan alasan wajib. |
