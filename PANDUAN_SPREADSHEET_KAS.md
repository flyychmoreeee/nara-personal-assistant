# 📊 Panduan Integrasi Kas Kelas ke Google Spreadsheet (NARA Assistant)

Sistem ini menghubungkan pencatatan uang kas di website/dashboard (yang dikelola oleh Ketua Kelas, Wakil, dan Bendahara) langsung ke **Google Spreadsheet publik** secara real-time. Teman-teman sekelas dapat memantau transparansi kas secara langsung tanpa perlu login ke sistem website!

---

## 🌟 Fitur Utama di Google Spreadsheet

Spreadsheet yang dihasilkan memiliki **2 Tab Otomatis**:

### 1. Tab `Ringkasan & Pengeluaran` (Hijau Emerald)
- **Kotak Ringkasan (KPI Cards)**:
  - 💵 **Saldo Kas Saat Ini**: Uang kas bersih yang tersedia di kas kelas.
  - 📥 **Total Kas Masuk**: Pemasukan total dari iuran siswa (lengkap dengan rincian **Tunai** vs **QRIS**).
  - 📤 **Total Pengeluaran**: Total uang kas yang telah dibelanjakan.
- **Tabel Rincian Pengeluaran Kas**:
  - Kolom: `No`, `Tanggal`, `Keperluan / Pengeluaran`, `Nominal (Rp)`, `Catatan / Keterangan`, dan tautan klik `Bukti Nota 📄`.
  - Otomatis menghitung total pengeluaran dengan formula `=SUM()`.

### 2. Tab `Rekap Pembayaran Siswa` (Biru Modern)
- **Tabel Matrix Siswa vs Minggu/Iuran**:
  - `No. Absen`, `Nama Lengkap`, `Panggilan`.
  - Kolom tiap minggu kas atau iuran khusus (dengan nominal iuran di sub-header).
  - Sel warna otomatis:
    - 🟢 **LUNAS**: Hijau muda lembut jika sudah bayar.
    - 🔴 **BELUM**: Merah muda jika belum bayar.
- **Kolom Status Pembayaran**:
  - `LUNAS ✅` (Hijau) jika lunas semua minggu.
  - `BELUM BAYAR (1x)` (Kuning/Oranye) jika nunggak 1 minggu.
  - `DOUBLE (2x) ⚠️` (Merah muda) jika nunggak 2 minggu ("double").
  - `NUNGGAK (Xx) 🚨` jika nunggak lebih dari 2 minggu.
- **Kolom Sisa Tunggakan**: Menghitung otomatis total rupiah yang harus dibayarkan siswa ke bendahara.
- **Kolom Minggu Belum Bayar**: Menampilkan nama-nama minggu yang belum dibayar (misal: *"Minggu 2, Minggu 3"*).
- **Frozen Header & Kolom**: Baris header dan 3 kolom pertama (No, Nama, Panggilan) terkunci (*freeze*), sehingga saat teman-teman menggeser layar di HP atau laptop, nama siswa tetap terlihat jelas!

---

## 🚀 Langkah Pemasangan (Hanya 3 Menit)

### Langkah 1: Buat Google Spreadsheet Baru
1. Buka [Google Sheets](https://sheets.new) di browser Anda.
2. Beri nama spreadsheet Anda, misalnya: `Uang Kas Kelas 1E-SIB`.
3. Klik tombol **Bagikan** (Share) di pojok kanan atas:
   - Ubah akses umum menjadi: **"Siapa saja yang memiliki link" -> "Pelihat" (Viewer)**.
   - Salin tautan spreadsheet tersebut.

---

### Langkah 2: Pasang Google Apps Script
1. Di Google Spreadsheet Anda, klik menu **Ekstensi** (*Extensions*) > **Apps Script**.
2. Hapus seluruh kode bawaan yang ada di editor.
3. Buka file [`google-apps-script/Code.gs`](file:///d:/laragon/www/nara/google-apps-script/Code.gs) di project ini, **salin seluruh isinya**, lalu **tempelkan** (*paste*) ke editor Apps Script.
4. Klik ikon **Simpan** (ikon disket atau `Ctrl + S`).

---

### Langkah 3: Terapkan Sebagai Web App (Deploy)
1. Klik tombol biru **Terapkan** (*Deploy*) di pojok kanan atas > pilih **Deployment baru** (*New deployment*).
2. Klik ikon gerigi ⚙️ di sebelah kiri "Pilih jenis" > pilih **Aplikasi web** (*Web app*).
3. Isi konfigurasi sebagai berikut:
   - **Deskripsi**: `NARA Kas Webhook`
   - **Jalankan sebagai** (*Execute as*): **Saya (email Anda)**
   - **Yang memiliki akses** (*Who has access*): **Siapa saja** (*Anyone*) ⚠️ *(Penting agar Laravel dapat mengirimkan data!)*
4. Klik tombol **Terapkan** (*Deploy*).
5. Jika muncul jendela otorisasi akun Google:
   - Klik **Tinjau Izin** (*Review Permissions*) > pilih akun Google Anda.
   - Klik **Lanjutan** (*Advanced*) > klik **Buka Untitled project (tidak aman)** / *Go to Untitled project*.
   - Klik **Izinkan** (*Allow*).
6. Google akan menampilkan **URL Aplikasi Web** (*Web App URL*), dengan format:
   ```
   https://script.google.com/macros/s/AKfycb.../exec
   ```
7. Salin URL tersebut.

---

### Langkah 4: Masukkan URL ke file `.env` Laravel
Buka file `.env` di project Laravel Anda, lalu perbarui baris berikut:

```env
# URL Web App Apps Script dari Langkah 3 (untuk pengiriman data otomatis)
KAS_SPREADSHEET_WEBHOOK_URL=https://script.google.com/macros/s/AKfycb.../exec

# Link Google Spreadsheet publik (agar bisa dibagikan otomatis di bot WhatsApp)
KAS_SPREADSHEET_PUBLIC_URL=https://docs.google.com/spreadsheets/d/1Bxi.../edit?usp=sharing

# Auto sync saat ada perubahan kas (true / false)
KAS_SPREADSHEET_AUTO_SYNC=true
```

---

## ⚡ Cara Kerja & Penggunaan

### 1. Sinkronisasi Otomatis (Real-time)
Setiap kali pengurus kelas melakukan aksi berikut di sistem/API:
- Men-toggle status pembayaran siswa (**Lunas / Belum Bayar**) via `POST /api/kas/toggle-payment`.
- Menambah, mengedit, atau menghapus **Pengeluaran Kas** via `apiResource('kas/expenses')`.
- Menambah atau mengubah **Minggu Kas / Iuran Khusus** via `apiResource('kas/weeks')`.

Sistem NARA akan **secara otomatis mengirimkan data terbaru ke Google Spreadsheet di background**. Pengurus tidak perlu melakukan sinkronisasi manual berulang kali!

### 2. Sinkronisasi Manual (Kapan Saja)
Jika ingin memicu pembaruan spreadsheet secara manual:
- **Via Artisan CLI**:
  ```bash
  # Tes preview data tanpa kirim
  php artisan kas:sync-spreadsheet --dry-run

  # Kirim langsung ke spreadsheet
  php artisan kas:sync-spreadsheet
  ```
- **Via API Endpoint**:
  ```http
  POST /api/kas/spreadsheet/sync
  ```
- **Cek Status Koneksi**:
  ```http
  GET /api/kas/spreadsheet/status
  ```
- **Lihat Preview JSON Payload**:
  ```http
  GET /api/kas/spreadsheet/payload
  ```

### 3. Perintah WhatsApp Bot (`!kas`)
Teman-teman sekelas dapat mengetik **`!kas`** atau **`/kas`** di grup WhatsApp kelas. Bot NARA akan otomatis membalas dengan ringkasan saldo, jumlah siswa yang lunas/tunggak, serta tautan langsung ke Google Spreadsheet!
