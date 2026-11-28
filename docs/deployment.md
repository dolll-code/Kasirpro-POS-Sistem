
## 7. Ekspor PDF/XLSX

- Dependency: `dompdf/dompdf ^3.1.6` dan `phpoffice/phpspreadsheet ^5.12.0` (lihat `docs/security.md` §5.3).
- Ekstensi production: `gd`, `zip`, `mbstring`, `dom`, `xml`, `fileinfo`.
- Pastikan permission tulis pada `storage/app/private/exports` dan folder cache Laravel; file tidak boleh berada di `public/`.
- Queue worker wajib berjalan bila `QUEUE_CONNECTION` bukan `sync`. Job export: `GenerateReportExport`, queue default, attempts 3, timeout 120 detik.
- Scheduler wajib menjalankan `expire-report-exports` hourly untuk cleanup file privat dan status expired.
- Konfigurasi environment: `REPORT_EXPORT_EXPIRY_HOURS=24`, `REPORT_EXPORT_ROW_LIMIT=5000`, `REPORT_EXPORT_SYNC_THRESHOLD=0`.
- Smoke test deployment: buat PDF kecil, buat XLSX, unduh via route privat, pastikan file tidak dapat diakses tanpa login/permission, lalu cek cleanup expiry.
- Production gate: patch advisory Symfony dan runtime mengikuti `prd.md` §12.2; jangan deploy PHP/MySQL versi awal tanpa patch keamanan.
