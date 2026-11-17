# Spesifikasi Endpoint & API (API & Route Contract)

| Metadata | Nilai |
|---|---|
| Status | **Diimplementasikan (Tahap 02 — Selesai)** |
| Base URL | `http://localhost:8000` |
| Autentikasi | Cookie-based Session (`SameSite=Lax`, CSRF Protected) |
| Terakhir Ditinjau | 2026-11-17 |
| Sumber Terkait | `prd.md` (§11), `routes/web.php` |

---

## 1. Prinsip Kontrak Endpoint
1. **Header Standar:** Setiap respons HTTP menyertakan header `X-Request-Id` (UUIDv4) untuk korelasi penelusuran log audit.
2. **Format Galat Terpadu (JSON Request):**
   ```json
   {
     "error": {
       "code": "FORBIDDEN",
       "message": "Anda tidak memiliki hak akses untuk tindakan ini."
     },
     "meta": {
       "request_id": "01J..."
     }
   }
   ```
3. **Status Codes:**
   - `200 OK`: Permintaan berhasil diproses.
   - `302 Found`: Pengalihan rute (standar formulir web).
   - `401 Unauthorized`: Sesi tidak ditemukan atau akun dinonaktifkan.
   - `403 Forbidden`: Izin tidak mencukupi untuk resource/tindakan target.
   - `422 Unprocessable Content`: Validasi input formulir gagal.
   - `429 Too Many Requests`: Batas laju pemanggilan terlampaui (*rate limited*).

---

## 2. Katalog Rute Autentikasi & Pengguna (Tahap 02)

| Method | Rute URI | Nama Rute | Izin (Permission) | Deskripsi |
|---|---|---|---|---|
| `GET` | `/login` | `login` | Publik (Guest) | Menampilkan formulir masuk sistem |
| `POST` | `/login` | — | Publik (Rate limited: 5x/m) | Memproses autentikasi kredensial |
| `POST` | `/logout` | `logout` | `auth` | Menginvalidasi sesi & keluar sistem |
| `GET` | `/profile` | `profile.show` | `auth`, `active` | Menampilkan profil pengguna sendiri |
| `PUT` | `/profile/password` | `profile.password.update` | `auth`, `active` | Memperbarui kata sandi mandiri |
| `GET` | `/users` | `users.index` | `users.manage` | Daftar pengguna toko (terfilter & terpaginasi) |
| `GET` | `/users/create` | `users.create` | `users.manage` | Formulir tambah pengguna baru |
| `POST` | `/users` | `users.store` | `users.manage` | Menyimpan pengguna baru ke basis data |
| `GET` | `/users/{user}` | `users.show` | `users.manage` | Detail pengguna, hak akses, & riwayat audit |
| `GET` | `/users/{user}/edit` | `users.edit` | `users.manage` | Formulir ubah data pengguna |
| `PUT` | `/users/{user}` | `users.update` | `users.manage` | Memperbarui data & peran pengguna |
| `PATCH` | `/users/{user}/toggle-status` | `users.toggle_status` | `users.manage` | Mengaktifkan / menonaktifkan akun staf |
