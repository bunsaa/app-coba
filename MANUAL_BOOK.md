# MANUAL BOOK
# APLIKASI SISTEM MANAJEMEN INDIKATOR CAPAIAN MUTU
# RSUD (Rumah Sakit Umum Daerah)

**Versi:** 1.0
**Tanggal:** Desember 2025
**Developer:** Custom Development

---

## DAFTAR ISI

1. [Pendahuluan](#1-pendahuluan)
2. [Teknologi yang Digunakan](#2-teknologi-yang-digunakan)
3. [Fitur Utama Aplikasi](#3-fitur-utama-aplikasi)
4. [Role & Hak Akses Pengguna](#4-role--hak-akses-pengguna)
5. [Alur Kerja (Workflow)](#5-alur-kerja-workflow)
6. [Panduan Penggunaan untuk Admin](#6-panduan-penggunaan-untuk-admin)
7. [Panduan Penggunaan untuk User Unit](#7-panduan-penggunaan-untuk-user-unit)
8. [Fitur Dashboard](#8-fitur-dashboard)
9. [Manajemen Indikator](#9-manajemen-indikator)
10. [Input Capaian Indikator](#10-input-capaian-indikator)
11. [Validasi Capaian Indikator](#11-validasi-capaian-indikator)
12. [Perhitungan Capaian](#12-perhitungan-capaian)
13. [Manajemen Lampiran](#13-manajemen-lampiran)
14. [Sistem Komentar & Revisi](#14-sistem-komentar--revisi)
15. [Pengaturan Akun](#15-pengaturan-akun)
16. [FAQ & Troubleshooting](#16-faq--troubleshooting)
17. [Kontak & Dukungan](#17-kontak--dukungan)

---

## 1. PENDAHULUAN

### 1.1 Tentang Aplikasi

**Aplikasi Sistem Manajemen Indikator Capaian Mutu** adalah sistem informasi berbasis web yang dirancang khusus untuk RSUD dalam mengelola, melaporkan, dan memvalidasi indikator kinerja mutu di tingkat unit/departemen.

### 1.2 Tujuan Aplikasi

- Memudahkan unit dalam melaporkan capaian indikator mutu secara bulanan
- Memudahkan admin dalam memvalidasi dan memonitor capaian indikator
- Menyediakan dashboard untuk melihat statistik dan tren capaian
- Mengotomasi perhitungan capaian bulanan, triwulan, dan tahunan
- Menyimpan riwayat capaian dan lampiran bukti

### 1.3 Manfaat Aplikasi

**Untuk Admin:**
- Monitor capaian indikator semua unit secara real-time
- Validasi capaian dengan sistem approval yang terstruktur
- Memberikan feedback/komentar revisi kepada unit
- Melihat aktivitas terbaru dari semua unit

**Untuk User Unit:**
- Input capaian indikator dengan mudah dan terstruktur
- Upload lampiran bukti capaian
- Menerima feedback dari admin untuk perbaikan
- Melihat progress capaian unit sendiri

---

## 2. TEKNOLOGI YANG DIGUNAKAN

### 2.1 Stack Teknologi

**Backend:**
- Framework: Laravel 11 (PHP 8.2+)
- Database: MySQL/MariaDB
- Authentication: Laravel Fortify

**Frontend:**
- Framework: Vue 3 + TypeScript
- Rendering: Inertia.js (SPA-like experience)
- Styling: Tailwind CSS
- Icons: Lucide Icons

**Server:**
- Web Server: Apache/Nginx (Laragon for development)
- Storage: Local file system

### 2.2 Persyaratan Sistem

**Minimum Requirements:**
- PHP 8.2 atau lebih tinggi
- MySQL 8.0 atau MariaDB 10.3+
- Composer 2.x
- Node.js 18.x atau lebih tinggi
- NPM 8.x atau lebih tinggi

**Browser yang Didukung:**
- Google Chrome 100+ (recommended)
- Mozilla Firefox 100+
- Microsoft Edge 100+
- Safari 15+

---

## 3. FITUR UTAMA APLIKASI

### 3.1 Dashboard

Halaman utama yang menampilkan:
- **Statistik Indikator:** Total indikator, indikator baru, perubahan
- **Capaian Bulanan:** Progress capaian bulan berjalan
- **Capaian Triwulan:** Rata-rata capaian 3 bulan terakhir
- **Capaian Tahunan:** Rata-rata capaian dari awal tahun
- **Aktivitas Terbaru:** Timeline aktivitas 12 jam terakhir

### 3.2 Manajemen Indikator

Fitur untuk mengelola master data indikator:
- Tambah indikator baru
- Edit indikator
- Lihat detail indikator
- Aktifkan/nonaktifkan indikator
- Assign indikator ke multiple units

### 3.3 Input Capaian Indikator

Fitur untuk unit melaporkan capaian:
- Input numerator dan denominator per bulan
- Upload lampiran bukti per bulan
- Input analisis dan RTL per triwulan
- Auto-calculate hasil persentase
- Lihat komentar dari admin

### 3.4 Validasi Capaian Indikator

Fitur untuk admin memvalidasi capaian:
- Review capaian per unit
- Validasi single/bulk indikator
- Berikan komentar revisi
- Lihat lampiran bukti
- Track validation status

### 3.5 Pengaturan Akun

Fitur manajemen akun pengguna:
- Update profile
- Change password
- Enable Two-Factor Authentication (2FA)
- Appearance settings

---

## 4. ROLE & HAK AKSES PENGGUNA

### 4.1 Admin

**Identifikasi:**
- Email: `admin@mutu.rsud.go.id`

**Hak Akses:**
- ✅ Lihat dashboard semua unit
- ✅ Buat, edit, delete indikator
- ✅ Aktifkan/nonaktifkan indikator
- ✅ Lihat capaian semua unit
- ✅ Validasi capaian semua unit
- ✅ Kirim komentar revisi ke unit
- ✅ Lihat semua aktivitas

### 4.2 User Unit (Non-Admin)

**Identifikasi:**
- Email: `[KODE_UNIT]@mutu.rsud.go.id`
- Contoh: `datin@mutu.rsud.go.id`, `bsdm@mutu.rsud.go.id`

**Hak Akses:**
- ✅ Lihat dashboard unit sendiri
- ✅ Input/update capaian unit sendiri
- ✅ Upload lampiran capaian
- ✅ Lihat komentar dari admin
- ✅ Lihat aktivitas unit sendiri
- ❌ Tidak bisa buat indikator
- ❌ Tidak bisa validasi capaian
- ❌ Tidak bisa lihat data unit lain

### 4.3 Cara Kerja Role

Sistem mendeteksi role berdasarkan **prefix email**:
```
Email: datin@mutu.rsud.go.id
       ↓
Prefix: "datin"
       ↓
Auto-filter ke unit: DATIN
```

Email dicek dengan case-insensitive matching terhadap `kode_unit` di database.

---

## 5. ALUR KERJA (WORKFLOW)

### 5.1 Siklus Bulanan Capaian Indikator

```
┌─────────────────────────────────────────────────────────────┐
│ 1. ADMIN MEMBUAT INDIKATOR                                  │
│    - Admin login dan buka menu Indikator                    │
│    - Klik "Tambah Indikator"                                │
│    - Isi form: unit, tim unit, nama, standar, dll           │
│    - Simpan (status: aktif)                                 │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. UNIT INPUT CAPAIAN (Awal Bulan - Tanggal 29)            │
│    - User unit login                                        │
│    - Buka menu Capaian Indikator                            │
│    - Pilih tahun & triwulan                                 │
│    - Input numerator & denominator per bulan                │
│    - Upload lampiran bukti (PDF/Excel)                      │
│    - Input analisis & RTL per triwulan                      │
│    - Simpan                                                 │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. ADMIN REVIEW CAPAIAN (Selama Bulan Berjalan)            │
│    - Admin buka menu Validasi Capaian                       │
│    - Pilih bulan berjalan                                   │
│    - Review data per unit                                   │
│    - Jika ada masalah: kirim komentar revisi                │
│    - Tunggu unit revisi                                     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. UNIT REVISI (Jika Ada Komentar)                         │
│    - User unit lihat komentar dari admin                    │
│    - Update data capaian                                    │
│    - Upload ulang lampiran (jika perlu)                     │
│    - Simpan perubahan                                       │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. ADMIN VALIDASI (Sampai Akhir Bulan)                     │
│    - Admin validasi satu per satu / bulk                    │
│    - Status: Setuju (validated) atau Tolak (with comment)  │
│    - Data validated tidak bisa diubah lagi                  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. DASHBOARD UPDATE                                         │
│    - Dashboard otomatis update statistik                    │
│    - Capaian bulanan, triwulan, tahunan dihitung            │
│    - Data jadi reference untuk laporan                      │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 7. END OF MONTH                                             │
│    - Validasi window ditutup otomatis                       │
│    - Data tidak bisa divalidasi lagi untuk bulan tersebut   │
│    - Siklus baru dimulai bulan berikutnya                   │
└─────────────────────────────────────────────────────────────┘
```

### 5.2 Siklus Tahunan

**Quarter 1 (Januari - Maret):**
- Input & validasi data 3 bulan
- Input analisis & RTL Q1

**Quarter 2 (April - Juni):**
- Input & validasi data 3 bulan
- Input analisis & RTL Q2

**Quarter 3 (Juli - September):**
- Input & validasi data 3 bulan
- Input analisis & RTL Q3

**Quarter 4 (Oktober - Desember):**
- Input & validasi data 3 bulan
- Input analisis & RTL Q4

**Dashboard otomatis menghitung:**
- Capaian per quarter
- Capaian year-to-date (YTD)
- Capaian annual (akhir tahun)

---

## 6. PANDUAN PENGGUNAAN UNTUK ADMIN

### 6.1 Login sebagai Admin

1. Buka browser dan akses URL aplikasi
2. Masukkan email: `admin@mutu.rsud.go.id`
3. Masukkan password
4. Klik "Login"
5. (Opsional) Masukkan kode 2FA jika sudah enable

### 6.2 Setup Initial Data

**a. Setup Units**

Units adalah master data unit/bagian di RSUD. Data ini biasanya sudah ada di database.

Jika perlu menambah unit baru, lakukan migrasi database atau insert manual:
```sql
INSERT INTO units (kode_unit, nama_unit, alias)
VALUES ('DATIN', 'Pengelolaan Sistem dan Database', 'DATIN');
```

**b. Setup Tim Units (Opsional)**

Jika unit punya sub-tim, tambahkan di tabel `tim_units`:
```sql
INSERT INTO tim_units (kode_unit, nama_tim)
VALUES ('BSDM', 'Diklat, Pendidikan & Penelitian');
```

**c. Setup User Accounts**

Buat akun untuk setiap unit dengan format email:
```
Email: [kode_unit]@mutu.rsud.go.id
Password: (set by admin)
```

Contoh:
- `datin@mutu.rsud.go.id`
- `bsdm@mutu.rsud.go.id`
- `etikpenelitian@mutu.rsud.go.id`

### 6.3 Mengelola Indikator

**a. Membuat Indikator Baru**

1. Login sebagai admin
2. Klik menu **"Indikator"** di sidebar
3. Klik tombol **"Tambah Indikator"**
4. Isi form:
   - **Unit:** Pilih unit pemilik indikator
   - **Tim Unit:** Pilih tim (jika ada), atau kosongkan
   - **Nama Indikator:** Tulis nama/deskripsi indikator
   - **Standar:** Tulis target/standar (contoh: ≥ 90%)
   - **Numerator:** Deskripsi pembilang (contoh: "Jumlah pasien yang puas")
   - **Denominator:** Deskripsi penyebut (contoh: "Total pasien yang disurvei")
   - **Satuan:** Pilih persen/rata-rata/permil
   - **Satuan Waktu:** Pilih hari/jam/menit (opsional)
   - **PIC (Person In Charge):** Pilih unit yang bertanggung jawab (bisa multiple)
5. Klik **"Simpan"**

**b. Mengedit Indikator**

1. Di halaman Indikator, cari indikator yang ingin diedit
2. Klik tombol **"Edit"** (icon pensil)
3. Update data yang diperlukan
4. Klik **"Simpan"**

**c. Melihat Detail Indikator**

1. Di halaman Indikator, klik tombol **"Lihat"** (icon mata)
2. Modal akan muncul menampilkan detail indikator

**d. Aktifkan/Nonaktifkan Indikator**

1. Di halaman Indikator, klik toggle switch di kolom "Status"
2. Indikator nonaktif tidak akan muncul di halaman Capaian dan Validasi

### 6.4 Memonitor Capaian

**a. Via Dashboard**

1. Login sebagai admin
2. Dashboard otomatis menampilkan:
   - Total indikator aktif
   - Capaian bulanan berjalan
   - Capaian triwulan
   - Capaian tahunan
   - Aktivitas terbaru (12 jam terakhir)
3. Hover di card Capaian Triwulan untuk lihat breakdown per bulan

**b. Via Halaman Capaian Indikator**

1. Klik menu **"Capaian Indikator"** di sidebar
2. Pilih **Unit** (dropdown)
3. Pilih **Tahun & Triwulan**
4. Review data capaian per indikator:
   - Numerator & Denominator per bulan
   - Hasil persentase otomatis
   - Status lampiran
   - Komentar (jika ada)
5. Klik **"Lampiran"** untuk lihat/download file bukti

### 6.5 Memvalidasi Capaian

**a. Akses Halaman Validasi**

1. Login sebagai admin
2. Klik menu **"Validasi Capaian Indikator"** di sidebar
3. Pilih **Bulan & Tahun** yang ingin divalidasi
4. Sistem akan menampilkan:
   - Status validasi (terbuka/ditutup)
   - Daftar unit dengan progress validasi
   - Total indikator vs tervalidasi

**b. Review & Validasi Per Unit**

1. Klik tombol **"Lihat & Validasi"** pada unit yang ingin direview
2. Modal detail akan muncul, menampilkan:
   - Daftar indikator unit tersebut
   - Data N, D, Hasil per indikator
   - Lampiran bukti
   - Komentar (jika ada)
   - Status validasi
3. Review data dengan teliti

**c. Validasi Indikator**

**Jika Data Benar:**
1. Klik tombol **"Validasi"** di bawah indikator
2. Konfirmasi validasi
3. Status indikator berubah menjadi "Tervalidasi" (✅)

**Jika Data Salah/Perlu Revisi:**
1. Klik tombol **"Beri Catatan Revisi"**
2. Tulis komentar revisi dengan jelas
   - Contoh: "Data numerator bulan Januari perlu dikoreksi, harusnya 120 bukan 100"
3. Klik **"Kirim Catatan"**
4. Unit akan menerima notifikasi komentar
5. Tunggu unit melakukan revisi

**d. Validasi Bulk**

**Validasi Semua Indikator di 1 Unit:**
1. Di modal detail unit, klik tombol **"Validasi Semua"** di header
2. Konfirmasi
3. Semua indikator di unit tersebut akan tervalidasi sekaligus

**Validasi Semua Indikator di Semua Unit:**
1. Di halaman utama Validasi, klik tombol **"Validasi Semua Unit"**
2. Konfirmasi (hati-hati!)
3. Semua indikator di semua unit akan tervalidasi sekaligus

**e. Clear Komentar (Hapus Komentar)**

Jika komentar sudah tidak relevan:
1. Di modal detail, klik icon **"Trash"** (🗑️) di samping komentar
2. Konfirmasi hapus
3. Komentar akan dihapus dan tombol validasi aktif kembali

### 6.6 Melihat Lampiran Bukti

1. Di modal validasi, lihat kolom **"Lampiran"**
2. Jika ada lampiran:
   - **PDF:** Klik link untuk preview inline di modal
   - **Excel:** Klik link untuk download file
3. Verifikasi lampiran sesuai dengan data capaian

### 6.7 Melihat Aktivitas Terbaru

1. Login sebagai admin
2. Di halaman Dashboard, scroll ke bagian **"Aktivitas Terbaru"**
3. Sistem menampilkan 15 aktivitas terakhir (12 jam):
   - Input/Update Capaian (🔄)
   - Indikator Baru (➕)
   - Indikator Dinonaktifkan (❌)
   - Komentar dari Admin (💬)
   - Validasi Capaian (✅)
4. Setiap aktivitas menampilkan:
   - Icon aktivitas
   - Deskripsi
   - User yang melakukan
   - Timestamp

### 6.8 Tips untuk Admin

- ✅ **Validasi Tepat Waktu:** Validasi sebaiknya dilakukan sebelum tanggal 5 bulan berikutnya
- ✅ **Review Lampiran:** Selalu cek lampiran sebelum validasi
- ✅ **Komentar Jelas:** Berikan komentar revisi yang spesifik dan jelas
- ✅ **Monitor Dashboard:** Cek dashboard setiap hari untuk monitor progress
- ✅ **Backup Data:** Lakukan backup database secara berkala

---

## 7. PANDUAN PENGGUNAAN UNTUK USER UNIT

### 7.1 Login sebagai User Unit

1. Buka browser dan akses URL aplikasi
2. Masukkan email: `[kode_unit]@mutu.rsud.go.id`
   - Contoh: `datin@mutu.rsud.go.id`
3. Masukkan password (yang diberikan admin)
4. Klik "Login"
5. (Opsional) Masukkan kode 2FA jika sudah enable

### 7.2 Melihat Dashboard Unit

1. Setelah login, Anda otomatis masuk ke halaman Dashboard
2. Dashboard menampilkan:
   - **Statistik Indikator Unit:** Total indikator, indikator baru
   - **Capaian Bulanan:** Progress capaian bulan berjalan unit Anda
   - **Capaian Triwulan:** Rata-rata 3 bulan unit Anda
   - **Capaian Tahunan:** Rata-rata dari awal tahun unit Anda
   - **Aktivitas Terbaru:** Timeline aktivitas 12 jam terakhir unit Anda

### 7.3 Input Capaian Indikator

**a. Akses Halaman Input Capaian**

1. Login sebagai user unit
2. Klik menu **"Capaian Indikator"** di sidebar
3. Unit Anda otomatis ter-select (tidak bisa pilih unit lain)
4. Pilih **Tahun & Triwulan** yang ingin di-input

**b. Input Numerator & Denominator**

1. Sistem menampilkan tabel indikator dengan kolom per bulan
2. Untuk setiap indikator, input:
   - **N (Numerator):** Angka pencapaian
   - **D (Denominator):** Angka target
3. Hasil persentase akan dihitung otomatis: `(N / D) × 100%`
4. **Catatan:** Jika hasil > 100%, akan ditampilkan sebagai 100% dengan nilai asli di bawahnya dalam tanda kurung

**c. Upload Lampiran Bukti**

1. Di kolom **"Lampiran"**, klik tombol **"Upload"** untuk bulan yang ingin diupload
2. Modal upload akan muncul
3. Klik **"Pilih File"**
4. Pilih file dari komputer Anda:
   - **Format:** PDF atau Excel (.pdf, .xlsx, .xls)
   - **Ukuran Maksimal:** 500 KB
5. Klik **"Upload"**
6. File akan otomatis ter-rename: `indikator_tim_bulan_tahun.ext`
7. Setelah upload, Anda bisa:
   - **Preview:** Lihat file PDF inline di modal
   - **Download:** Download file Excel
   - **Re-upload:** Upload ulang jika ada kesalahan (hanya sebelum divalidasi)

**d. Input Analisis & RTL (Per Triwulan)**

1. Scroll ke bagian bawah halaman
2. Isi form:
   - **Analisis:** Deskripsi/analisis capaian triwulan
   - **RTL (Rencana Tindak Lanjut):** Rencana perbaikan/tindak lanjut
3. Contoh:
   ```
   Analisis:
   Capaian indikator pada TW1 mencapai 85%, masih di bawah standar 90%.
   Hal ini disebabkan oleh kurangnya pelatihan SDM dan sistem informasi
   yang belum optimal.

   RTL:
   1. Mengadakan pelatihan SDM bulan April
   2. Update sistem informasi bulan Mei
   3. Monitoring intensif setiap minggu
   ```

**e. Simpan Data**

1. Setelah semua data terisi, klik tombol **"Simpan"**
2. Sistem akan menyimpan data dan menampilkan notifikasi sukses
3. Data bisa diupdate kapan saja sampai tanggal 29 bulan berjalan

### 7.4 Melihat & Merespon Komentar

**a. Cek Komentar dari Admin**

1. Di halaman **"Capaian Indikator"**, lihat kolom **"Komentar"**
2. Jika ada komentar baru, akan muncul icon 💬 dengan badge notifikasi
3. Klik icon untuk expand komentar

**b. Membaca Komentar**

1. Modal komentar akan muncul, menampilkan:
   - **Komentar Terbaru:** Komentar dari admin
   - **Status:** Sudah direvisi / Menunggu revisi
   - **Riwayat Komentar:** History komentar sebelumnya (jika ada)

**c. Melakukan Revisi**

1. Baca komentar dengan teliti
2. Update data capaian sesuai feedback admin:
   - Edit numerator/denominator
   - Upload ulang lampiran (jika diminta)
   - Update analisis/RTL
3. Klik **"Simpan"**
4. (Opsional) Klik **"Mark as Read"** untuk menandai komentar sudah dibaca

### 7.5 Melihat Lampiran yang Sudah Diupload

1. Di halaman **"Capaian Indikator"**, klik tombol **"Lampiran"** pada bulan yang ingin dilihat
2. Modal lampiran akan muncul:
   - **PDF:** Preview inline dalam modal
   - **Excel:** Tampil info file, klik "Download" untuk download
3. Klik **"X"** untuk tutup modal

### 7.6 Melihat Status Validasi

1. Login sebagai user unit
2. Klik menu **"Validasi Capaian Indikator"** di sidebar
3. Pilih **Bulan & Tahun**
4. Sistem menampilkan daftar indikator unit Anda dengan status:
   - ✅ **Tervalidasi:** Data sudah divalidasi admin
   - ⏳ **Pending:** Menunggu validasi admin
   - 💬 **Ada Komentar:** Admin memberikan komentar revisi

### 7.7 Tips untuk User Unit

- ✅ **Input Tepat Waktu:** Input data sebelum tanggal 29 bulan berjalan
- ✅ **Lampiran Lengkap:** Selalu upload lampiran bukti untuk setiap bulan
- ✅ **Cek Komentar:** Cek komentar dari admin secara berkala
- ✅ **Revisi Cepat:** Jika ada komentar, segera lakukan revisi
- ✅ **Backup Lampiran:** Simpan copy lampiran di folder sendiri

---

## 8. FITUR DASHBOARD

### 8.1 Tampilan Dashboard

Dashboard terbagi menjadi beberapa section:

**a. Statistik Indikator (4 Cards)**

1. **Total Indikator**
   - Jumlah total indikator aktif unit Anda
   - Icon: 📊

2. **Indikator Baru**
   - Jumlah indikator yang ditambahkan hari ini
   - Icon: ➕

3. **Perubahan**
   - Selisih indikator hari ini vs kemarin
   - Icon: 🔄

4. **Indikator Kemarin**
   - Jumlah indikator kemarin
   - Icon: 📅

**b. Capaian Bulanan**

- Menampilkan capaian bulan berjalan
- Progress bar visual
- Persentase rata-rata: `(N/D × 100)%` untuk semua indikator
- Jumlah indikator yang sudah dilaporkan vs total
- Daftar unit yang belum melaporkan (untuk admin)
- Status validasi

**c. Capaian Triwulan**

- Menampilkan capaian 3 bulan dalam TW berjalan
- Persentase rata-rata TW
- Hover untuk lihat breakdown per bulan (tooltip)
- Status: Masih dalam proses / Selesai

**d. Capaian Tahunan**

- Menampilkan capaian dari Januari s/d bulan sekarang
- Persentase rata-rata YTD (Year To Date)
- Total indikator dan jumlah bulan terisi data
- Status: Menunggu data TW / Sudah lengkap

**e. Aktivitas Terbaru**

- Timeline aktivitas 12 jam terakhir
- Menampilkan 15 aktivitas terakhir
- Jenis aktivitas:
  - 🔄 Input/Update Capaian
  - ➕ Indikator Baru
  - ❌ Penonaktifan Indikator
  - 💬 Komentar dari Admin
  - ✅ Validasi Capaian
- Setiap aktivitas menampilkan:
  - Icon
  - Deskripsi
  - User yang melakukan
  - Timestamp (format: "2 jam yang lalu")

### 8.2 Perbedaan Dashboard Admin vs User Unit

**Admin:**
- Lihat data **semua unit**
- Statistik global
- Aktivitas semua unit

**User Unit:**
- Lihat data **unit sendiri** saja
- Statistik unit spesifik
- Aktivitas unit sendiri

---

## 9. MANAJEMEN INDIKATOR

### 9.1 Struktur Data Indikator

Setiap indikator memiliki:

**Informasi Dasar:**
- **Kode Unit:** Unit pemilik indikator
- **Tim Unit:** Sub-tim dalam unit (opsional)
- **Nama Indikator:** Deskripsi indikator
- **Standar:** Target/standar (contoh: ≥ 90%)
- **Satuan:** Persen, Rata-rata, atau Permil
- **Satuan Waktu:** Hari, Jam, atau Menit (opsional)

**Rumus Perhitungan:**
- **Numerator:** Deskripsi pembilang
- **Denominator:** Deskripsi penyebut

**PIC (Person In Charge):**
- Unit/units yang bertanggung jawab
- Bisa multiple units

**Status:**
- **Aktif:** Indikator muncul di Capaian & Validasi
- **Nonaktif:** Indikator tidak muncul (archive)

### 9.2 Menambah Indikator (Admin Only)

1. Login sebagai admin
2. Klik menu **"Indikator"**
3. Klik tombol **"Tambah Indikator"**
4. Isi form dengan lengkap:

**Form Fields:**

```
┌──────────────────────────────────────────────┐
│ Unit:           [Dropdown Unit]              │
│ Tim Unit:       [Dropdown Tim] (opsional)    │
│                                              │
│ Nama Indikator:                              │
│ [Text Area]                                  │
│                                              │
│ Standar:                                     │
│ [Input Text] (contoh: ≥ 90%)                 │
│                                              │
│ Numerator (Pembilang):                       │
│ [Text Area] (contoh: Jumlah pasien puas)     │
│                                              │
│ Denominator (Penyebut):                      │
│ [Text Area] (contoh: Total pasien survey)    │
│                                              │
│ Satuan:                                      │
│ ( ) Persen  ( ) Rata-rata  ( ) Permil        │
│                                              │
│ Satuan Waktu: (opsional)                     │
│ ( ) Hari  ( ) Jam  ( ) Menit                 │
│                                              │
│ PIC (Person In Charge):                      │
│ [Multi-select Dropdown Units]                │
│                                              │
│        [Batal]         [Simpan]              │
└──────────────────────────────────────────────┘
```

5. Klik **"Simpan"**
6. Indikator akan muncul di list dengan status **Aktif**

**Catatan:**
- Jika pilih multiple PIC, sistem akan create multiple records (1 record per unit)
- Setiap unit bisa input capaian terpisah

### 9.3 Mengedit Indikator (Admin Only)

1. Di halaman Indikator, cari indikator yang ingin diedit
2. Klik icon **"Edit"** (✏️)
3. Modal edit akan muncul
4. Update field yang diperlukan:
   - Nama indikator
   - Standar
   - Numerator
   - Denominator
   - Satuan
   - Satuan waktu
5. **Tidak bisa edit:** Unit, Tim Unit, PIC (read-only)
6. Klik **"Simpan"**

### 9.4 Melihat Detail Indikator

1. Di halaman Indikator, klik icon **"Lihat"** (👁️)
2. Modal detail akan muncul, menampilkan:
   - Nama indikator
   - Unit & Tim Unit
   - Standar
   - Numerator & Denominator
   - Satuan & Satuan Waktu
   - PIC
   - Status (Aktif/Nonaktif)
3. Klik **"X"** untuk tutup modal

### 9.5 Aktifkan/Nonaktifkan Indikator (Admin Only)

1. Di halaman Indikator, lihat kolom **"Status"**
2. Klik toggle switch:
   - **ON (Hijau):** Indikator aktif
   - **OFF (Abu-abu):** Indikator nonaktif
3. Sistem otomatis update status

**Efek Nonaktif:**
- Indikator tidak muncul di halaman Capaian Indikator
- Indikator tidak muncul di halaman Validasi Capaian
- Data capaian lama tetap tersimpan (tidak dihapus)

### 9.6 Multi-Unit Indikator

Admin bisa assign 1 indikator ke multiple units sekaligus.

**Cara:**
1. Saat tambah indikator, di field **"PIC"**, pilih multiple units
2. Klik **"Simpan"**
3. Sistem akan create **multiple records** (1 record per unit)

**Contoh:**
```
Indikator: "Kepuasan Pasien Rawat Inap"
PIC: [DATIN, BSDM, Penunjang]

Result:
- Record 1: Unit=DATIN, Indikator="Kepuasan Pasien..."
- Record 2: Unit=BSDM, Indikator="Kepuasan Pasien..."
- Record 3: Unit=Penunjang, Indikator="Kepuasan Pasien..."
```

Setiap unit bisa input capaian terpisah untuk indikator yang sama.

---

## 10. INPUT CAPAIAN INDIKATOR

### 10.1 Akses Halaman Input Capaian

1. Login sebagai user unit
2. Klik menu **"Capaian Indikator"** di sidebar
3. Sistem otomatis:
   - Select unit Anda (tidak bisa pilih unit lain)
   - Tampilkan tahun sekarang
   - Tampilkan triwulan sekarang

### 10.2 Pilih Triwulan & Tahun

**Dropdown Tahun:**
- Tampilkan tahun sekarang dan 2 tahun ke belakang
- Contoh: 2025, 2024, 2023

**Dropdown Triwulan:**
- **Q1:** Januari - Maret
- **Q2:** April - Juni
- **Q3:** Juli - September
- **Q4:** Oktober - Desember

### 10.3 Input Data Capaian

**Tabel Input:**

```
┌────────────────────────────────────────────────────────────────────┐
│ Indikator          │ Std  │ Num │ Denom │ Jan   │ Feb   │ Mar   │ ... │
│                    │      │     │       │ N | D │ N | D │ N | D │     │
├────────────────────────────────────────────────────────────────────┤
│ Kepuasan Pasien    │ ≥90% │ ... │ ...   │[_][_]│[_][_]│[_][_]│     │
│ Rawat Inap         │      │     │       │       │       │       │     │
│                    │      │     │       │ Hasil:│ Hasil:│ Hasil:│     │
│                    │      │     │       │  -    │  -    │  -    │     │
├────────────────────────────────────────────────────────────────────┤
│ Waktu Tunggu       │ ≤30  │ ... │ ...   │[_][_]│[_][_]│[_][_]│     │
│ Pelayanan          │ menit│     │       │       │       │       │     │
│                    │      │     │       │ Hasil:│ Hasil:│ Hasil:│     │
│                    │      │     │       │  -    │  -    │  -    │     │
└────────────────────────────────────────────────────────────────────┘
```

**Cara Input:**

1. **Input Numerator (N):**
   - Ketik angka pencapaian di kolom N
   - Contoh: 85 (pasien puas)

2. **Input Denominator (D):**
   - Ketik angka target di kolom D
   - Contoh: 100 (total pasien)

3. **Lihat Hasil Otomatis:**
   - Sistem auto-calculate: `(N / D) × 100%`
   - Contoh: (85 / 100) × 100 = 85%
   - **Catatan:** Jika hasil > 100%, ditampilkan sebagai 100% dengan nilai asli di bawahnya

4. **Ulangi untuk Setiap Bulan:**
   - Input N & D untuk Januari, Februari, Maret (Q1)
   - Atau untuk April, Mei, Juni (Q2)
   - Dan seterusnya

### 10.4 Upload Lampiran Bukti

**Per Bulan:**

1. Di kolom **"Lampiran"**, klik tombol **"Upload"** pada bulan yang ingin diupload
2. Modal upload muncul:

```
┌─────────────────────────────────────────┐
│ Upload Lampiran - Januari 2025          │
│                                         │
│ File: [Pilih File]                      │
│ Format: PDF, Excel (.xlsx, .xls)        │
│ Max Size: 500 KB                        │
│                                         │
│         [Batal]      [Upload]           │
└─────────────────────────────────────────┘
```

3. Klik **"Pilih File"**
4. Pilih file dari komputer
5. Klik **"Upload"**
6. File akan ter-rename otomatis: `indikator_tim_jan_2025.pdf`

**Preview/Download Lampiran:**

1. Setelah upload, tombol berubah menjadi **"Lihat"**
2. Klik **"Lihat"** untuk:
   - **PDF:** Preview inline di modal
   - **Excel:** Download file

**Re-upload:**

1. Jika ada kesalahan, klik **"Upload Ulang"**
2. Pilih file baru
3. File lama akan diganti
4. **Catatan:** Re-upload hanya bisa sebelum data divalidasi admin

### 10.5 Input Analisis & RTL (Per Triwulan)

**Lokasi:**
- Di bawah tabel capaian bulanan

**Form:**

```
┌────────────────────────────────────────────────────────┐
│ Analisis Triwulan 1 (Jan - Mar 2025):                 │
│ ┌────────────────────────────────────────────────────┐ │
│ │ [Text Area - ketik analisis...]                    │ │
│ │                                                    │ │
│ │                                                    │ │
│ └────────────────────────────────────────────────────┘ │
│                                                        │
│ RTL (Rencana Tindak Lanjut):                           │
│ ┌────────────────────────────────────────────────────┐ │
│ │ [Text Area - ketik RTL...]                         │ │
│ │                                                    │ │
│ │                                                    │ │
│ └────────────────────────────────────────────────────┘ │
│                                                        │
│                 [Batal]      [Simpan]                  │
└────────────────────────────────────────────────────────┘
```

**Contoh Pengisian:**

**Analisis:**
```
Capaian indikator pada Triwulan 1 (Jan-Mar 2025):
- Kepuasan Pasien: 85% (target ≥90%)
- Waktu Tunggu: 32 menit (target ≤30 menit)

Belum mencapai target karena:
1. Kurangnya SDM di loket pendaftaran
2. Sistem antrian manual masih belum optimal
3. Pelatihan customer service belum merata
```

**RTL:**
```
Rencana Tindak Lanjut Q2 (Apr-Jun 2025):
1. Rekrutmen 2 petugas loket (April)
2. Implementasi sistem antrian digital (Mei)
3. Pelatihan customer service untuk semua petugas (Juni)
4. Monitoring & evaluasi setiap 2 minggu
5. Target perbaikan: 90% kepuasan, 25 menit waktu tunggu
```

### 10.6 Simpan Data

1. Setelah semua data terisi (N, D, Lampiran, Analisis, RTL)
2. Klik tombol **"Simpan"** di bagian bawah
3. Sistem akan:
   - Validasi data
   - Simpan ke database
   - Tampilkan notifikasi sukses
4. Data tersimpan dan bisa diupdate kapan saja sampai:
   - Tanggal 29 bulan berjalan (untuk input)
   - Sebelum divalidasi admin (untuk update)

### 10.7 Batasan Input

**Time-Lock Input:**
- Input/update hanya bisa dilakukan **sampai tanggal 29 bulan berjalan**
- Setelah tanggal 29, input ditutup
- Error message: "Input capaian hanya dapat dilakukan sampai tanggal 29 bulan berjalan"

**Validation-Lock:**
- Setelah data divalidasi admin, data **tidak bisa diubah lagi**
- Re-upload lampiran **tidak bisa** setelah validasi
- Jika perlu revisi, hubungi admin untuk clear validasi

---

## 11. VALIDASI CAPAIAN INDIKATOR

### 11.1 Akses Halaman Validasi (Admin Only)

1. Login sebagai admin
2. Klik menu **"Validasi Capaian Indikator"** di sidebar
3. Sistem menampilkan halaman validasi

### 11.2 Pilih Bulan & Tahun

**Dropdown Bulan:**
- Tampilkan 12 bulan terakhir
- Format: "Januari 2025", "Februari 2025", dst

**Auto-Select:**
- Default: Bulan berjalan

**Status Validasi:**
```
┌─────────────────────────────────────────────────────┐
│ Status Validasi: Validasi Terbuka ✅                │
│ Batas: 31 Januari 2025 23:59:59                     │
└─────────────────────────────────────────────────────┘
```

Atau:

```
┌─────────────────────────────────────────────────────┐
│ Status Validasi: Periode Validasi Ditutup ❌        │
│ (Mode Lihat History)                                 │
└─────────────────────────────────────────────────────┘
```

### 11.3 Lihat Daftar Unit

**Tabel Unit:**

```
┌────────────────────────────────────────────────────────────────┐
│ No │ Unit/Bagian              │ Indikator │ Validasi │ Aksi   │
├────┼──────────────────────────┼───────────┼──────────┼────────┤
│ 1  │ Pengelolaan Sistem       │    5      │   3/5    │ [Lihat]│
│    │ dan Database (DATIN)     │           │          │        │
├────┼──────────────────────────┼───────────┼──────────┼────────┤
│ 2  │ Bagian SDM (BSDM)        │    8      │   8/8    │ [Lihat]│
│    │                          │           │   ✅     │        │
├────┼──────────────────────────┼───────────┼──────────┼────────┤
│ 3  │ Etik & Penelitian        │    3      │   0/3    │ [Lihat]│
│    │                          │           │          │        │
└────────────────────────────────────────────────────────────────┘
```

**Informasi:**
- **Unit/Bagian:** Nama unit
- **Jumlah Indikator:** Total indikator aktif
- **Tervalidasi:** Jumlah yang sudah divalidasi / total
- **Progress Bar:** Visual progress (hijau jika 100%)
- **Aksi:** Tombol "Lihat & Validasi"

### 11.4 Review Detail Capaian Per Unit

**Klik Tombol "Lihat & Validasi":**

Modal detail muncul dengan layout:

```
┌──────────────────────────────────────────────────────────────────┐
│ Detail Capaian Indikator                              [X] Tutup  │
│ Pengelolaan Sistem dan Database (DATIN)                          │
│ Bulan: Januari 2025                                              │
│                                                                  │
│ [Filter Tim Unit: ▼ Semua Tim Unit]    [Validasi Semua Unit]    │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ 📊 1. Kepuasan Pasien Rawat Inap                                │
│ Standar: ≥ 90%                                                   │
│                                                                  │
│ ┌────────────────────────────────────────────────────────────┐  │
│ │ Numerator:    85        Denominator:    100                │  │
│ │ Hasil:        85%       Lampiran:       📄 Download        │  │
│ └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│ 💬 Catatan Revisi (1):                           [Hapus 🗑️]     │
│ "Data numerator perlu dikoreksi, harusnya 90"                   │
│ Status: Menunggu revisi                                          │
│                                                                  │
│ Analisis Q1: [Text Area]                                        │
│ RTL Q1:      [Text Area]                                        │
│                                                                  │
│ [Beri Catatan Revisi]                          [Validasi ✅]    │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ 📊 2. Waktu Tunggu Pelayanan                                    │
│ ...                                                              │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### 11.5 Validasi Indikator

**A. Validasi Single Indikator**

**Jika Data Benar:**
1. Review data dengan teliti:
   - Numerator & Denominator
   - Hasil persentase
   - Lampiran bukti (klik untuk lihat/download)
   - Analisis & RTL
2. Klik tombol **"Validasi ✅"** di bawah indikator
3. Konfirmasi: "Validasi indikator: [Nama Indikator]?"
4. Klik **"OK"**
5. Status berubah menjadi **"Tervalidasi"** dengan icon ✅
6. Tombol validasi berubah menjadi disabled

**Jika Data Salah/Perlu Revisi:**
1. Klik tombol **"Beri Catatan Revisi"**
2. Modal komentar muncul:

```
┌─────────────────────────────────────────────┐
│ 💬 Catatan Revisi                           │
│                                             │
│ Tulis catatan untuk tim unit:               │
│ ┌─────────────────────────────────────────┐ │
│ │ [Text Area]                             │ │
│ │                                         │ │
│ │                                         │ │
│ └─────────────────────────────────────────┘ │
│                                             │
│     [Batal]           [Kirim Catatan 📤]    │
└─────────────────────────────────────────────┘
```

3. Tulis komentar dengan jelas dan spesifik
   - Contoh: "Data numerator bulan Januari perlu dikoreksi. Menurut laporan, seharusnya 90 pasien puas, bukan 85. Mohon cek ulang dan upload lampiran yang benar."
4. Klik **"Kirim Catatan"**
5. Komentar akan tersimpan dan unit akan melihat komentar
6. Tombol validasi akan disabled sampai unit melakukan revisi

**B. Validasi Bulk (Multiple Indikator)**

**Validasi Semua Indikator di 1 Unit:**
1. Di modal detail unit, klik tombol **"Validasi Semua"** di header
2. Konfirmasi: "Validasi semua indikator di [Nama Unit]?"
3. Klik **"OK"**
4. Semua indikator di unit tersebut akan tervalidasi sekaligus
5. **Hati-hati:** Pastikan semua data sudah benar sebelum bulk validasi

**Validasi Semua Indikator di Semua Unit:**
1. Di halaman utama Validasi, klik tombol **"Validasi Semua Unit"**
2. Konfirmasi: "Validasi SEMUA indikator di SEMUA unit?"
3. Klik **"OK"**
4. Semua indikator di semua unit akan tervalidasi sekaligus
5. **SANGAT HATI-HATI:** Ini akan validasi seluruh data, tidak bisa di-undo

### 11.6 Mengelola Komentar

**A. Lihat Komentar & History**

1. Di modal detail, jika ada komentar, akan muncul box:

```
┌──────────────────────────────────────────────────────┐
│ 💬 Catatan Revisi (3)                   [Hapus 🗑️]   │
│                                                       │
│ Catatan Terbaru:                                     │
│ "Lampiran bulan Februari belum diupload"             │
│ Status: Menunggu revisi                              │
│                                                       │
│ Riwayat Catatan: (Hover untuk lihat)                 │
│ - "Data numerator perlu dikoreksi..." (dibaca)       │
│ - "Upload lampiran yang benar..." (dibaca)           │
└──────────────────────────────────────────────────────┘
```

2. Hover di **"Riwayat Catatan"** untuk expand history
3. Tooltip akan muncul menampilkan semua komentar lama dengan timestamp

**B. Clear Komentar (Hapus)**

Jika komentar sudah tidak relevan atau unit sudah revisi:
1. Klik icon **"Hapus 🗑️"** di samping komentar
2. Konfirmasi: "Hapus komentar dan aktifkan kembali tombol validasi?"
3. Klik **"OK"**
4. Komentar terhapus
5. Tombol validasi aktif kembali

**C. Edit/Update Komentar**

1. Klik tombol **"Beri Catatan Revisi"** lagi
2. Tulis komentar baru
3. Klik **"Kirim Catatan"**
4. Komentar lama akan masuk ke history
5. Komentar baru menjadi komentar aktif

### 11.7 Lihat & Verifikasi Lampiran

**A. Lihat Lampiran**

1. Di modal detail, lihat kolom **"Lampiran"**
2. Jika ada lampiran, klik link **"📄 Download"** atau **"🔍 Lihat"**
3. Modal lampiran muncul:

**Untuk PDF:**
```
┌─────────────────────────────────────────────┐
│ Lampiran - Kepuasan Pasien - Januari 2025  │
│                                             │
│ ┌─────────────────────────────────────────┐ │
│ │                                         │ │
│ │     [PDF Preview Inline]                │ │
│ │                                         │ │
│ │                                         │ │
│ └─────────────────────────────────────────┘ │
│                                             │
│              [Download PDF 📥]              │
└─────────────────────────────────────────────┘
```

**Untuk Excel:**
```
┌─────────────────────────────────────────────┐
│ Lampiran - Kepuasan Pasien - Januari 2025  │
│                                             │
│ 📊 File Excel                               │
│ Nama: kepuasan_pasien_datin_jan_2025.xlsx   │
│ Ukuran: 250 KB                              │
│ Uploaded: 15 Jan 2025 10:30                 │
│                                             │
│            [Download Excel 📥]              │
└─────────────────────────────────────────────┘
```

**B. Verifikasi Lampiran**

1. Buka/download lampiran
2. Cek kesesuaian dengan data capaian:
   - Numerator & denominator sesuai?
   - Tanggal/periode sesuai?
   - Bukti valid dan jelas?
3. Jika sesuai: validasi
4. Jika tidak sesuai: berikan komentar revisi

### 11.8 Input Analisis & RTL dari Admin

Admin juga bisa menambahkan atau mengedit analisis & RTL:

1. Di modal detail, lihat text area **"Analisis Q1"** dan **"RTL Q1"**
2. Ketik atau edit analisis/RTL
3. Auto-save saat blur (keluar dari text area)
4. Tidak perlu klik tombol simpan

**Use Case:**
- Admin menambahkan catatan tambahan
- Admin memperbaiki typo dari unit
- Admin memberikan masukan/saran

### 11.9 Filter Tim Unit

Jika unit punya multiple tim:

1. Di modal detail, gunakan dropdown **"Filter Tim Unit"**
2. Pilih tim spesifik atau **"Semua Tim Unit"**
3. Daftar indikator akan ter-filter sesuai tim
4. Validasi per tim: klik **"Validasi Semua"** akan validasi hanya tim yang ter-filter

### 11.10 Time-Lock Validasi

**Aturan:**
- Validasi hanya bisa dilakukan **sampai akhir bulan berjalan**
- Setelah bulan berakhir, validasi window otomatis ditutup
- Mode berubah menjadi **"Mode Lihat History"**

**Contoh:**
- Bulan Januari: Validasi bisa sampai 31 Januari 23:59:59
- Mulai 1 Februari: Validasi Januari ditutup, hanya bisa lihat data

**Error Message:**
```
┌─────────────────────────────────────────────────────┐
│ ⚠️ Validasi hanya dapat dilakukan sampai akhir      │
│    bulan berjalan                                   │
└─────────────────────────────────────────────────────┘
```

**Solusi:**
- Jika perlu revisi data bulan lalu, admin bisa:
  1. Hapus validasi
  2. Minta unit update data
  3. Validasi ulang di bulan berjalan (jika masih dalam window)

---

## 12. PERHITUNGAN CAPAIAN

### 12.1 Rumus Dasar

**Capaian Per Indikator:**
```
Hasil = (Numerator / Denominator) × 100%

Contoh:
N = 85 (pasien puas)
D = 100 (total pasien)
Hasil = (85 / 100) × 100 = 85%
```

**Catatan:**
- Jika hasil > 100%, ditampilkan sebagai **100%** dengan nilai asli dalam tanda kurung
- Contoh: (125 / 100) × 100 = 125% → Tampil: **100%** (125%)

### 12.2 Capaian Bulanan

**Rumus:**
```
Capaian Bulanan = Rata-rata hasil semua indikator aktif di bulan tersebut

Contoh Bulan Januari:
- Indikator A: 85%
- Indikator B: 90%
- Indikator C: 75%

Capaian Januari = (85 + 90 + 75) / 3 = 83.33%
```

**Catatan:**
- Hanya indikator yang **sudah divalidasi** yang dihitung
- Indikator nonaktif tidak dihitung

### 12.3 Capaian Triwulan (Quarter)

**Rumus:**
```
Capaian TW = Rata-rata capaian 3 bulan dalam TW

Contoh TW1 (Januari - Maret):
- Januari: 83.33%
- Februari: 87.50%
- Maret: 85.00%

Capaian TW1 = (83.33 + 87.50 + 85.00) / 3 = 85.28%
```

**Breakdown Per Bulan:**
- Hover di card Capaian Triwulan untuk lihat breakdown
- Tooltip menampilkan capaian per bulan

### 12.4 Capaian Tahunan (Year-to-Date)

**Rumus:**
```
Capaian Tahunan = Rata-rata capaian semua bulan dari Januari s/d bulan sekarang

Contoh sampai akhir Februari (bulan sekarang = Februari):
- Januari: 83.33%
- Februari: 87.50%

Capaian Tahunan = (83.33 + 87.50) / 2 = 85.42%
```

**Catatan:**
- Hanya bulan yang sudah lewat yang dihitung
- Bulan berjalan (belum selesai) tidak dihitung

### 12.5 Contoh Perhitungan Lengkap

**Data:**
```
Unit: DATIN
Tahun: 2025
Bulan: Januari

Indikator 1: Kepuasan Pasien
- N: 85, D: 100
- Hasil: 85%

Indikator 2: Waktu Tunggu
- N: 25, D: 30 (target ≤30 menit)
- Hasil: 83.33%

Indikator 3: Kelengkapan Rekam Medis
- N: 95, D: 100
- Hasil: 95%
```

**Perhitungan:**

1. **Capaian Januari:**
   ```
   = (85 + 83.33 + 95) / 3
   = 263.33 / 3
   = 87.78%
   ```

2. **Capaian TW1 (sampai Januari):**
   ```
   = Januari saja (belum ada Feb & Mar)
   = 87.78%
   ```

3. **Capaian Tahunan (sampai Januari):**
   ```
   = Januari saja
   = 87.78%
   ```

**Setelah Input Februari & Maret:**

Misalkan:
- Februari: 90.00%
- Maret: 88.50%

**Perhitungan Update:**

1. **Capaian TW1:**
   ```
   = (87.78 + 90.00 + 88.50) / 3
   = 266.28 / 3
   = 88.76%
   ```

2. **Capaian Tahunan (sampai Maret):**
   ```
   = (87.78 + 90.00 + 88.50) / 3
   = 88.76%
   ```

---

## 13. MANAJEMEN LAMPIRAN

### 13.1 Format File yang Didukung

**Jenis File:**
- **PDF** (.pdf)
- **Excel** (.xlsx, .xls)

**Ukuran Maksimal:**
- 500 KB per file

**Penamaan Otomatis:**
```
Format: [indikator]_[tim]_[bulan]_[tahun].[ext]

Contoh:
- kepuasan_pasien_datin_jan_2025.pdf
- waktu_tunggu_bsdm_feb_2025.xlsx
```

### 13.2 Upload Lampiran

**Cara Upload:**

1. Di halaman Capaian Indikator, klik tombol **"Upload"** pada bulan yang ingin diupload
2. Modal upload muncul
3. Klik **"Pilih File"**
4. Pilih file dari komputer Anda
5. Sistem akan validasi:
   - Format file (PDF/Excel)
   - Ukuran file (max 500KB)
6. Jika valid, klik **"Upload"**
7. File akan diupload dan disimpan di server
8. Filename akan di-rename otomatis

**Lokasi Penyimpanan:**
```
storage/app/public/lampiran/[tahun]/[bulan]/[filename]

Contoh:
storage/app/public/lampiran/2025/jan/kepuasan_pasien_datin_jan_2025.pdf
```

### 13.3 Lihat/Preview Lampiran

**Untuk PDF:**

1. Klik tombol **"Lihat"** atau link download
2. Modal preview muncul dengan iframe
3. PDF ditampilkan inline dalam modal
4. Bisa scroll untuk lihat halaman lain
5. Klik **"Download"** untuk download file

**Untuk Excel:**

1. Klik tombol **"Lihat"** atau link download
2. Modal info muncul menampilkan:
   - Icon Excel
   - Nama file
   - Ukuran file
   - Tanggal upload
3. Klik **"Download"** untuk download file
4. Buka file dengan Microsoft Excel atau LibreOffice

### 13.4 Download Lampiran

**Via Halaman Capaian Indikator:**
1. Klik tombol **"Lihat"** pada lampiran
2. Modal muncul
3. Klik **"Download"** di dalam modal
4. File akan terdownload ke folder Downloads

**Via Halaman Validasi Capaian:**
1. Klik link **"📄 Download"** pada kolom Lampiran
2. Browser akan download file langsung

**URL Download:**
```
/capaian-indikator/lampiran/download/[filename]

Contoh:
/capaian-indikator/lampiran/download/kepuasan_pasien_datin_jan_2025.pdf
```

### 13.5 Re-upload Lampiran

**Kondisi:**
- Hanya bisa re-upload **sebelum data divalidasi** admin
- Setelah validasi, re-upload **tidak bisa** dilakukan

**Cara Re-upload:**

1. Klik tombol **"Upload Ulang"** (jika ada)
2. Pilih file baru
3. Klik **"Upload"**
4. File lama akan **diganti** dengan file baru
5. Filename tetap sama (auto-rename)

**Error Message:**
```
┌─────────────────────────────────────────────────────┐
│ ⚠️ Data sudah divalidasi, tidak bisa re-upload      │
└─────────────────────────────────────────────────────┘
```

### 13.6 Hapus Lampiran

**Catatan:**
- Saat ini, aplikasi **tidak menyediakan** fitur hapus lampiran
- Jika perlu menghapus, gunakan re-upload dengan file kosong atau hubungi admin

**Workaround:**
- Re-upload dengan file placeholder (misalnya: PDF kosong dengan tulisan "Tidak ada data")

### 13.7 Validasi Error

**Error 1: Format File Tidak Valid**
```
┌─────────────────────────────────────────────────────┐
│ ⚠️ File harus berformat PDF atau Excel              │
│    (.pdf, .xlsx, .xls)                              │
└─────────────────────────────────────────────────────┘
```
**Solusi:** Pilih file dengan format yang benar

**Error 2: Ukuran File Terlalu Besar**
```
┌─────────────────────────────────────────────────────┐
│ ⚠️ Ukuran file maksimal 500 KB                      │
│    (File Anda: 850 KB)                              │
└─────────────────────────────────────────────────────┘
```
**Solusi:** Kompres file atau kurangi ukuran

**Error 3: File Corrupt/Rusak**
```
┌─────────────────────────────────────────────────────┐
│ ⚠️ File tidak bisa dibaca, mungkin rusak            │
└─────────────────────────────────────────────────────┘
```
**Solusi:** Buat ulang file atau gunakan file backup

### 13.8 Tips Manajemen Lampiran

- ✅ **Naming Convention:** Gunakan nama file yang deskriptif sebelum upload (sistem akan rename, tapi memudahkan tracking)
- ✅ **Compress PDF:** Gunakan tool online untuk compress PDF jika ukuran > 500KB
- ✅ **Excel Tips:** Hapus sheet/data yang tidak perlu untuk kurangi ukuran
- ✅ **Backup:** Simpan copy lampiran di folder lokal sebagai backup
- ✅ **Scan Quality:** Untuk PDF scan, gunakan resolusi 150 DPI (cukup jelas, ukuran kecil)

---

## 14. SISTEM KOMENTAR & REVISI

### 14.1 Alur Komentar

```
┌──────────────────────────────────────────────┐
│ 1. ADMIN KIRIM KOMENTAR                      │
│    - Admin review data capaian               │
│    - Jika ada issue, kirim komentar revisi   │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 2. UNIT TERIMA KOMENTAR                      │
│    - Unit lihat komentar di halaman Capaian  │
│    - Icon 💬 dengan badge notifikasi         │
│    - Status: "Menunggu revisi"               │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 3. UNIT REVISI DATA                          │
│    - Update numerator/denominator            │
│    - Upload ulang lampiran (jika perlu)      │
│    - Simpan perubahan                        │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 4. UNIT MARK AS READ                         │
│    - Klik "Mark as Read" di modal komentar   │
│    - Status: "Sudah direvisi"                │
│    - Tombol validasi admin aktif kembali     │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 5. ADMIN REVIEW ULANG                        │
│    - Admin cek data yang sudah direvisi      │
│    - Jika OK: validasi                       │
│    - Jika masih ada issue: kirim komentar lg │
└──────────────────────────────────────────────┘
```

### 14.2 Kirim Komentar (Admin)

1. Login sebagai admin
2. Buka halaman **Validasi Capaian Indikator**
3. Klik **"Lihat & Validasi"** pada unit
4. Klik tombol **"Beri Catatan Revisi"** pada indikator yang perlu revisi
5. Modal komentar muncul
6. Tulis komentar dengan **jelas dan spesifik**:

**Contoh Komentar Baik:**
```
Data numerator bulan Januari perlu dikoreksi.

Menurut laporan dari bagian Farmasi, jumlah pasien yang puas
seharusnya 90 orang, bukan 85 orang seperti yang tercatat.

Mohon:
1. Cek ulang data di sistem rekam medis
2. Update numerator menjadi 90
3. Upload lampiran bukti yang benar (form survei)

Terima kasih.
```

**Contoh Komentar Kurang Baik:**
```
Data salah, perbaiki.
```
(Tidak jelas apa yang salah dan bagaimana memperbaikinya)

7. Klik **"Kirim Catatan"**
8. Komentar tersimpan dan unit akan melihat notifikasi

### 14.3 Lihat Komentar (Unit)

**Via Halaman Capaian Indikator:**

1. Login sebagai user unit
2. Buka halaman **Capaian Indikator**
3. Lihat kolom **"Komentar"** atau icon 💬
4. Jika ada komentar baru, akan muncul **badge notifikasi** (angka)
5. Klik icon untuk expand komentar

**Modal Komentar:**
```
┌─────────────────────────────────────────────┐
│ 💬 Catatan dari Admin                       │
│                                             │
│ Catatan Terbaru:                            │
│ "Data numerator bulan Januari perlu..."    │
│                                             │
│ Status: Menunggu revisi                     │
│                                             │
│ Riwayat Catatan: (2)                        │
│ • "Upload lampiran yang benar..." (dibaca)  │
│ • "Cek ulang data..." (dibaca)              │
│                                             │
│           [Tutup]     [Mark as Read]        │
└─────────────────────────────────────────────┘
```

### 14.4 Mark as Read (Unit)

**Setelah melakukan revisi:**

1. Buka modal komentar
2. Klik tombol **"Mark as Read"**
3. Konfirmasi: "Tandai komentar sudah dibaca dan direvisi?"
4. Klik **"OK"**
5. Status berubah menjadi **"Sudah direvisi"** ✅
6. Admin akan melihat status ini dan bisa validasi ulang

**Efek Mark as Read:**
- Komentar tetap ada (tidak dihapus)
- Status berubah: "Menunggu revisi" → "Sudah direvisi"
- Tombol validasi admin aktif kembali
- Badge notifikasi hilang

### 14.5 Komentar History (Riwayat)

**Sistem menyimpan riwayat komentar:**

Setiap kali admin mengirim komentar baru:
1. Komentar lama masuk ke **history**
2. Komentar baru menjadi komentar **aktif**
3. History tersimpan dengan:
   - Isi komentar
   - Timestamp
   - Status dibaca/tidak

**Lihat History:**

1. Buka modal komentar
2. Lihat bagian **"Riwayat Catatan"**
3. Hover atau klik untuk expand
4. Tooltip menampilkan semua komentar lama dengan detail:
   - Isi komentar
   - Tanggal & waktu
   - Status (dibaca/belum)

**Contoh:**
```
Riwayat Catatan: (3)

1. "Data numerator perlu dikoreksi..."
   15 Jan 2025 10:30 • ✅ Direvisi

2. "Upload lampiran yang benar..."
   18 Jan 2025 14:15 • ✅ Direvisi

3. "Cek ulang data di sistem..."
   20 Jan 2025 09:00 • ✅ Direvisi
```

### 14.6 Clear Komentar (Admin)

**Jika komentar sudah tidak relevan:**

1. Login sebagai admin
2. Buka modal validasi
3. Klik icon **"Hapus 🗑️"** di samping komentar
4. Konfirmasi: "Hapus komentar dan aktifkan kembali tombol validasi?"
5. Klik **"OK"**
6. Komentar dihapus dari database
7. Tombol validasi aktif kembali

**Catatan:**
- Clear komentar akan **menghapus** komentar dari database (tidak masuk history)
- Gunakan dengan hati-hati

### 14.7 Multiple Round Revisi

**Scenario:**

1. **Round 1:**
   - Admin kirim komentar: "Data numerator salah"
   - Unit revisi & mark as read
   - Admin validasi

2. **Round 2:**
   - Admin kirim komentar lagi: "Lampiran tidak sesuai"
   - Komentar Round 1 masuk history
   - Unit revisi & mark as read
   - Admin validasi

3. **Round 3:**
   - Dan seterusnya...

**History tetap tersimpan:**
```
Riwayat Catatan: (2)
• Round 1: "Data numerator salah" (direvisi)
• Round 2: "Lampiran tidak sesuai" (direvisi)
```

### 14.8 Notifikasi Komentar

**Badge Notifikasi:**
- Jika ada komentar baru, muncul **badge angka** di icon 💬
- Contoh: 💬 (1) → ada 1 komentar baru
- Badge hilang setelah mark as read

**Aktivitas Timeline:**
- Komentar baru muncul di **Aktivitas Terbaru** di Dashboard
- Icon: 💬
- Deskripsi: "Komentar dari Admin untuk [Indikator]"

### 14.9 Tips Komentar & Revisi

**Untuk Admin:**
- ✅ **Jelas & Spesifik:** Jelaskan dengan detail apa yang salah dan bagaimana memperbaikinya
- ✅ **Contoh:** Berikan contoh jika perlu (misal: "Seharusnya 90, bukan 85")
- ✅ **Checklist:** Gunakan numbering untuk memudahkan unit
- ✅ **Sopan:** Gunakan bahasa yang sopan dan profesional

**Untuk Unit:**
- ✅ **Baca Teliti:** Baca komentar dengan teliti sebelum revisi
- ✅ **Cek Data:** Cek ulang data di sistem sumber
- ✅ **Upload Bukti:** Jika diminta, upload lampiran yang benar
- ✅ **Mark as Read:** Jangan lupa mark as read setelah revisi

---

## 15. PENGATURAN AKUN

### 15.1 Akses Pengaturan

1. Login
2. Klik icon **Profile** di pojok kanan atas (atau menu **Settings**)
3. Submenu muncul:
   - Profile
   - Password
   - Two-Factor Authentication
   - Appearance

### 15.2 Update Profile

**URL:** `/settings/profile`

**Form:**
```
┌────────────────────────────────────────┐
│ Profile Information                    │
│                                        │
│ Name:                                  │
│ [Input Text]                           │
│                                        │
│ Email:                                 │
│ [Input Text] (read-only untuk unit)    │
│                                        │
│ Profile Picture:                       │
│ [Upload Image]                         │
│                                        │
│         [Cancel]      [Save]           │
└────────────────────────────────────────┘
```

**Cara Update:**
1. Isi field **Name** dengan nama baru
2. (Opsional) Upload foto profile
3. Klik **"Save"**
4. Notifikasi: "Profile updated successfully"

**Catatan:**
- Email **tidak bisa diubah** untuk user unit (karena digunakan untuk role detection)
- Admin bisa ubah email jika perlu

### 15.3 Change Password

**URL:** `/settings/password`

**Form:**
```
┌────────────────────────────────────────┐
│ Update Password                        │
│                                        │
│ Current Password:                      │
│ [Input Password]                       │
│                                        │
│ New Password:                          │
│ [Input Password]                       │
│                                        │
│ Confirm New Password:                  │
│ [Input Password]                       │
│                                        │
│         [Cancel]      [Update]         │
└────────────────────────────────────────┘
```

**Cara Update:**
1. Isi **Current Password** dengan password lama
2. Isi **New Password** dengan password baru
3. Isi **Confirm New Password** dengan password baru (sama)
4. Klik **"Update"**
5. Notifikasi: "Password updated successfully"

**Validasi:**
- Password minimal 8 karakter
- Password baru harus sama dengan confirm password
- Current password harus benar

### 15.4 Two-Factor Authentication (2FA)

**URL:** `/settings/two-factor`

**Apa itu 2FA?**
- Keamanan tambahan selain password
- Menggunakan aplikasi authenticator (Google Authenticator, Authy, dll)
- Setiap login, diminta kode OTP 6 digit

**Enable 2FA:**

1. Buka halaman 2FA
2. Klik **"Enable Two-Factor Authentication"**
3. QR Code muncul
4. Scan QR Code dengan aplikasi authenticator di HP
5. Masukkan kode OTP 6 digit untuk konfirmasi
6. Klik **"Confirm"**
7. **Recovery Codes** akan ditampilkan (simpan di tempat aman!)
8. 2FA aktif

**Recovery Codes:**
```
┌────────────────────────────────────────┐
│ Recovery Codes                         │
│ (Simpan di tempat aman!)               │
│                                        │
│ 1. ABCD-EFGH-IJKL                      │
│ 2. MNOP-QRST-UVWX                      │
│ 3. YZ12-3456-7890                      │
│ ...                                    │
│                                        │
│      [Download]    [Print]             │
└────────────────────────────────────────┘
```

**Login dengan 2FA:**
1. Masukkan email & password seperti biasa
2. Halaman OTP muncul
3. Buka aplikasi authenticator di HP
4. Masukkan kode OTP 6 digit
5. Klik **"Verify"**
6. Login sukses

**Disable 2FA:**
1. Buka halaman 2FA
2. Klik **"Disable Two-Factor Authentication"**
3. Konfirmasi dengan password
4. 2FA dinonaktifkan

### 15.5 Appearance

**URL:** `/settings/appearance`

**Pengaturan Tampilan:**
- Theme: Light / Dark / Auto (follow system)
- Font Size: Small / Medium / Large
- Language: Indonesia / English (jika support multi-language)

**Cara Ubah Theme:**
1. Pilih theme yang diinginkan
2. Klik **"Save"**
3. Tampilan aplikasi berubah sesuai theme

### 15.6 Delete Account

**Lokasi:** `/settings/profile` (bagian bawah)

**Cara Delete:**
1. Scroll ke bagian **"Delete Account"**
2. Klik **"Delete Account"**
3. Konfirmasi dengan password
4. Klik **"Confirm Delete"**
5. Akun dihapus **permanen** (tidak bisa di-restore)

**Peringatan:**
- ⚠️ Aksi ini **tidak bisa di-undo**
- ⚠️ Semua data capaian yang diinput akan **tetap ada** (tidak terhapus)
- ⚠️ Hanya akun user yang terhapus

---

## 16. FAQ & TROUBLESHOOTING

### 16.1 FAQ Umum

**Q: Bagaimana cara login pertama kali?**
A:
1. Buka URL aplikasi di browser
2. Masukkan email: `[kode_unit]@mutu.rsud.go.id`
3. Masukkan password (yang diberikan admin)
4. Klik "Login"
5. (Opsional) Setup 2FA untuk keamanan ekstra

**Q: Lupa password, bagaimana?**
A:
1. Klik link **"Forgot Password"** di halaman login
2. Masukkan email Anda
3. Klik **"Send Reset Link"**
4. Cek email, klik link reset password
5. Masukkan password baru
6. Login dengan password baru

**Q: Email saya tidak terdaftar di dropdown unit?**
A:
- Pastikan email Anda sesuai format: `[kode_unit]@mutu.rsud.go.id`
- Hubungi admin untuk menambahkan unit Anda ke database
- Cek typo di email (case-insensitive, tapi ejaan harus sama)

**Q: Kenapa unit saya tidak muncul di dropdown?**
A:
- Pastikan unit sudah ada di database (tabel `units`)
- Pastikan ada indikator aktif untuk unit Anda
- Hubungi admin untuk cek database

**Q: Kenapa tidak bisa input capaian?**
A:
Cek kondisi berikut:
1. **Tanggal:** Input hanya bisa sampai tanggal 29 bulan berjalan
2. **Indikator:** Pastikan ada indikator aktif untuk unit Anda
3. **Login:** Pastikan login sebagai user unit yang benar

**Q: Kenapa tidak bisa validasi?**
A:
Cek kondisi berikut:
1. **Role:** Hanya admin yang bisa validasi
2. **Bulan:** Validasi hanya bisa sampai akhir bulan berjalan
3. **Data:** Pastikan ada data capaian yang sudah diinput unit

**Q: File upload failed, kenapa?**
A:
Cek kondisi berikut:
1. **Format:** Harus PDF atau Excel (.pdf, .xlsx, .xls)
2. **Ukuran:** Maksimal 500 KB
3. **Corrupt:** File tidak rusak/corrupt
4. **Connection:** Koneksi internet stabil

**Q: Hasil persentase salah, kenapa?**
A:
- Cek input numerator & denominator
- Pastikan denominator tidak 0
- Jika hasil > 100%, sistem otomatis tampilkan 100% (dengan nilai asli di bawahnya)

**Q: Komentar tidak muncul, kenapa?**
A:
1. Pastikan admin sudah **kirim** komentar (bukan hanya tulis)
2. Refresh halaman (F5)
3. Logout & login lagi
4. Cek di halaman Capaian Indikator, kolom Komentar

**Q: Data sudah divalidasi tapi ingin diubah?**
A:
- Hubungi admin
- Admin bisa clear validasi
- Setelah clear, unit bisa update data lagi

### 16.2 Troubleshooting

**Problem 1: Tidak bisa login**

**Gejala:**
- Error: "Invalid credentials"

**Solusi:**
1. Cek email & password (case-sensitive)
2. Gunakan fitur "Forgot Password"
3. Cek CAPS LOCK keyboard
4. Hubungi admin untuk reset password

---

**Problem 2: Dashboard kosong / tidak ada data**

**Gejala:**
- Dashboard tidak menampilkan statistik
- Semua card kosong

**Solusi:**
1. Cek apakah ada indikator aktif untuk unit Anda
2. Cek apakah sudah ada data capaian yang diinput
3. Refresh halaman (F5)
4. Clear browser cache (Ctrl+Shift+Delete)
5. Hubungi admin untuk cek database

---

**Problem 3: Upload lampiran error 500**

**Gejala:**
- Error: "Internal Server Error" saat upload

**Solusi:**
1. Cek ukuran file (max 500KB)
2. Cek format file (PDF/Excel)
3. Cek permission folder `storage/app/public/lampiran`
4. Hubungi admin untuk cek server logs

---

**Problem 4: Lampiran tidak bisa didownload**

**Gejala:**
- Klik link download, tapi file tidak terdownload
- Error 404 Not Found

**Solusi:**
1. Cek apakah file benar-benar sudah diupload
2. Cek di server, folder `storage/app/public/lampiran`
3. Jalankan command: `php artisan storage:link`
4. Hubungi admin untuk cek file storage

---

**Problem 5: Hasil persentase tidak muncul**

**Gejala:**
- Setelah input N & D, hasil tetap "-"

**Solusi:**
1. Cek apakah denominator = 0 (tidak boleh)
2. Refresh halaman (F5)
3. Cek JavaScript console (F12) untuk error
4. Update browser ke versi terbaru

---

**Problem 6: Validasi button disabled**

**Gejala:**
- Tombol "Validasi" berwarna abu-abu (disabled)

**Penyebab & Solusi:**
1. **Ada komentar belum dibaca:** Unit harus revisi & mark as read dulu
2. **Data belum diinput:** Numerator & denominator harus terisi
3. **Periode ditutup:** Validasi hanya sampai akhir bulan berjalan
4. **Sudah divalidasi:** Data sudah tervalidasi, tidak perlu validasi lagi

---

**Problem 7: Capaian triwulan tidak muncul**

**Gejala:**
- Card capaian triwulan kosong

**Solusi:**
1. Pastikan sudah input data minimal 1 bulan dalam TW
2. Pastikan data sudah divalidasi admin
3. Refresh halaman (F5)
4. Hubungi admin untuk cek query

---

**Problem 8: Aktivitas terbaru tidak update**

**Gejala:**
- Dashboard "Aktivitas Terbaru" tidak menampilkan aktivitas baru

**Solusi:**
1. Refresh halaman (F5)
2. Cek filter waktu (hanya 12 jam terakhir)
3. Cek apakah ada aktivitas baru (input/validasi)
4. Hubungi admin untuk cek logs

---

**Problem 9: Modal tidak muncul**

**Gejala:**
- Klik tombol, tapi modal tidak muncul
- Layar gelap tapi modal kosong

**Solusi:**
1. Refresh halaman (F5)
2. Clear browser cache
3. Cek JavaScript console (F12) untuk error
4. Coba browser lain (Chrome/Firefox)
5. Disable extension browser (AdBlock, dll)

---

**Problem 10: Slow performance / loading lama**

**Gejala:**
- Aplikasi lambat
- Loading spinner lama

**Solusi:**
1. Cek koneksi internet
2. Clear browser cache
3. Tutup tab browser yang tidak digunakan
4. Restart browser
5. Hubungi admin untuk cek server performance

---

### 16.3 Error Messages

**Error:** "Validasi hanya dapat dilakukan sampai akhir bulan berjalan"

**Penyebab:** Mencoba validasi data bulan lalu

**Solusi:** Validasi hanya bisa untuk bulan berjalan, tunggu bulan depan atau hubungi admin

---

**Error:** "Input capaian hanya dapat dilakukan sampai tanggal 29 bulan berjalan"

**Penyebab:** Mencoba input setelah tanggal 29

**Solusi:** Tunggu bulan depan atau hubungi admin untuk override

---

**Error:** "Data numerator dan denominator harus terisi terlebih dahulu"

**Penyebab:** Mencoba validasi data yang belum diinput

**Solusi:** Input numerator & denominator terlebih dahulu

---

**Error:** "File harus berformat PDF atau Excel"

**Penyebab:** Upload file dengan format selain PDF/Excel

**Solusi:** Konversi file ke PDF atau Excel terlebih dahulu

---

**Error:** "Ukuran file maksimal 500 KB"

**Penyebab:** Upload file > 500KB

**Solusi:** Kompres file atau kurangi ukuran file

---

**Error:** "File tidak ditemukan di storage"

**Penyebab:** File lampiran tidak ada di server

**Solusi:** Re-upload file atau hubungi admin

---

**Error:** "Data sudah divalidasi, tidak bisa re-upload"

**Penyebab:** Mencoba re-upload setelah validasi

**Solusi:** Hubungi admin untuk clear validasi terlebih dahulu

---

### 16.4 Browser Compatibility

**Recommended Browsers:**
- ✅ Google Chrome 100+ (Best)
- ✅ Mozilla Firefox 100+
- ✅ Microsoft Edge 100+
- ✅ Safari 15+

**Not Recommended:**
- ❌ Internet Explorer (deprecated)
- ❌ Opera Mini (limited support)
- ❌ UC Browser (limited support)

**Mobile:**
- ✅ Chrome Mobile (Android)
- ✅ Safari Mobile (iOS)
- ⚠️ Responsive design, tapi lebih baik di desktop

---

## 17. KONTAK & DUKUNGAN

### 17.1 Kontak Administrator

Jika mengalami masalah atau butuh bantuan:

**Admin IT:**
- Email: `admin@mutu.rsud.go.id`
- Telepon: (021) 1234-5678 ext. 100
- Jam Kerja: Senin - Jumat, 08:00 - 16:00 WIB

**Support Teknis:**
- Email: `support@mutu.rsud.go.id`
- WhatsApp: 0812-3456-7890

### 17.2 Cara Melaporkan Bug

Jika menemukan bug/error:

1. Screenshot error message
2. Catat langkah-langkah yang menyebabkan error
3. Catat browser & OS yang digunakan
4. Kirim email ke support dengan informasi di atas

**Format Email:**
```
Subject: [BUG] Deskripsi singkat bug

Deskripsi:
- Apa yang terjadi?
- Apa yang seharusnya terjadi?

Langkah Reproduksi:
1. Login sebagai...
2. Buka halaman...
3. Klik tombol...
4. Error muncul

Environment:
- Browser: Chrome 120
- OS: Windows 11
- User: datin@mutu.rsud.go.id

Screenshot: [attach]
```

### 17.3 Request Fitur Baru

Jika ada usulan fitur:

1. Tulis deskripsi fitur dengan jelas
2. Jelaskan manfaat fitur tersebut
3. (Opsional) Berikan contoh/mockup
4. Kirim email ke admin

### 17.4 Feedback & Saran

Kami sangat menghargai feedback dan saran Anda untuk perbaikan aplikasi.

**Kirim feedback via:**
- Email: `admin@mutu.rsud.go.id`
- Form feedback di aplikasi (jika ada)
- Meeting/diskusi dengan admin

---

## LAMPIRAN

### A. Glossary

**Indikator:** Ukuran/metrik kinerja yang digunakan untuk mengevaluasi capaian

**Numerator (N):** Pembilang dalam rumus perhitungan indikator

**Denominator (D):** Penyebut dalam rumus perhitungan indikator

**Capaian:** Hasil perhitungan indikator dalam bentuk persentase atau nilai

**Validasi:** Proses approval/persetujuan data capaian oleh admin

**TW (Triwulan):** Periode 3 bulan (Q1: Jan-Mar, Q2: Apr-Jun, Q3: Jul-Sep, Q4: Oct-Dec)

**YTD (Year-to-Date):** Dari awal tahun sampai sekarang

**RTL:** Rencana Tindak Lanjut

**PIC:** Person In Charge (penanggung jawab)

**2FA:** Two-Factor Authentication (autentikasi dua faktor)

---

### B. Shortcut Keyboard

| Shortcut | Fungsi |
|----------|--------|
| `Ctrl + S` | Simpan form (jika di dalam form) |
| `Esc` | Tutup modal |
| `F5` | Refresh halaman |
| `Ctrl + Shift + Delete` | Clear browser cache |
| `F12` | Buka DevTools (untuk debug) |

---

### C. Daftar Status

**Status Indikator:**
- ✅ **Aktif:** Indikator muncul di Capaian & Validasi
- ❌ **Nonaktif:** Indikator tidak muncul (archive)

**Status Validasi:**
- ✅ **Tervalidasi:** Data sudah divalidasi admin
- ⏳ **Pending:** Menunggu validasi admin
- 💬 **Ada Komentar:** Admin memberikan komentar revisi

**Status Komentar:**
- 📝 **Menunggu Revisi:** Unit belum revisi
- ✅ **Sudah Direvisi:** Unit sudah revisi & mark as read

**Status Lampiran:**
- 📄 **Uploaded:** File sudah diupload
- ❌ **Belum Upload:** File belum diupload
- 🔄 **Re-upload Available:** Bisa re-upload

---

### D. Database Schema Sederhana

**Tabel Utama:**

1. **users** - Data pengguna
2. **units** - Master data unit
3. **tim_units** - Master data tim dalam unit
4. **indikators** - Master data indikator
5. **capaian_indikators** - Data capaian per indikator
6. **capaian_lampiran** - File lampiran bukti

**Relasi:**
```
users (1) ←→ (N) capaian_indikators (via email→kode_unit)
units (1) ←→ (N) indikators
units (1) ←→ (N) tim_units
indikators (1) ←→ (N) capaian_indikators
capaian_indikators (1) ←→ (N) capaian_lampiran
```

---

### E. API Endpoint Summary

**Public:**
- `GET /` - Welcome page
- `POST /login` - Login
- `POST /logout` - Logout
- `POST /forgot-password` - Forgot password
- `POST /reset-password` - Reset password

**Authenticated:**
- `GET /dashboard` - Dashboard
- `GET /indikator` - Indikator list (admin only)
- `GET /capaian-indikator` - Input capaian
- `GET /validasi-capaian-indikator` - Validasi (admin)
- `GET /settings/*` - Settings pages

**API (POST):**
- `/indikator` - CRUD indikator
- `/capaian-indikator/save` - Save capaian
- `/capaian-indikator/upload` - Upload lampiran
- `/validasi-capaian-indikator/validate-*` - Validasi
- `/validasi-capaian-indikator/send-komentar` - Send comment

---

### F. Changelog

**Versi 1.0 (Desember 2025):**
- Initial release
- Fitur Dashboard
- Manajemen Indikator
- Input Capaian Indikator
- Validasi Capaian Indikator
- Sistem Komentar & Revisi
- Upload Lampiran
- Two-Factor Authentication

---

## PENUTUP

Terima kasih telah menggunakan **Aplikasi Sistem Manajemen Indikator Capaian Mutu**.

Aplikasi ini dibuat untuk memudahkan pengelolaan, pelaporan, dan validasi indikator mutu di RSUD. Kami harap manual book ini membantu Anda dalam menggunakan aplikasi dengan efektif.

Jika ada pertanyaan, saran, atau feedback, jangan ragu untuk menghubungi administrator.

**Tim Pengembang**
RSUD - Bagian IT & Sistem Informasi

---

**© 2025 RSUD. All rights reserved.**
