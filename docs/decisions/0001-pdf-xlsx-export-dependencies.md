# Dependency Decision: PDF dan XLSX Export (Tahap 13)

Tanggal: 2026-11-28
Status: Diterapkan

## Konteks
Laporan perlu diekspor ke PDF dan XLSX dengan data numeric/date yang benar, private storage, dan aman dari formula injection.

## Keputusan
- PDF: `dompdf/dompdf ^3.1.6`
- XLSX: `phpoffice/phpspreadsheet ^5.12.0`

## Alasan
- Keduanya aktif dipelihara dan mendukung PHP 8.2 serta sesuai dengan Laravel 12 PHP-only (tidak memerlukan binary eksternal).
- `dompdf` tersedia tanpa `wkhtmltopdf`/Chromium sehingga cocok server sederhana; `phpspreadsheet` memberi kontrol tipe sel, format tanggal, dan sanitasi formula.
- Versi awal yang dicoba, `dompdf 3.1.0` dan `phpspreadsheet 4.1.0/5.8.0`, diblokir Composer karena security advisory. Versi 3.1.6 dan 5.12.0 tidak tercatat pada advisory package tersebut saat instalasi.
- Lisensi permisif: Dompdf LGPL-2.1, PhpSpreadsheet MIT.
- Ekstensi `gd`, `zip`, `mbstring`, `dom`, `xml`, `fileinfo` tersedia di runtime test.

## Konsekuensi
- `composer audit` masih melaporkan advisory pada dependency Symfony framework (`http-kernel`, `mailer`, `routing`), bukan pada package export. Framework harus dipatch sesuai production gate PRD §12.2.
- Ekspor besar membutuhkan queue worker dan scheduler cleanup; file berada pada disk privat dengan TTL dan row limit.

## Bukti
- `composer require dompdf/dompdf:^3.1.6 phpoffice/phpspreadsheet:^5.12.0`
- `tests/Feature/Reports/ReportExportFlowTest.php`
