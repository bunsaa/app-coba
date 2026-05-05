# Perubahan Sistem Indikator

## Summary Perubahan

Sistem indikator telah diubah dari **per-unit** menjadi **global dengan multi-PIC**.

### Perubahan Utama:

#### 1. **Tombol "Tambah Indikator" dipindahkan ke atas**
   - Sebelumnya: Setiap unit memiliki tombol "Tambah Indikator" sendiri
   - Sekarang: Satu tombol global "Tambah Indikator Baru" di atas tabel

#### 2. **PIC dapat memilih lebih dari 1 unit**
   - Sebelumnya: 1 indikator = 1 unit/tim unit sebagai PIC
   - Sekarang: 1 indikator = bisa multiple unit sebagai PIC

## Perubahan Database

### Tabel `indikators`
- **Kolom baru:** `pic_units` (JSON) - menyimpan array kode_unit yang menjadi PIC
- **Kolom modified:** `kode_unit` - sekarang nullable (karena indikator tidak terikat ke satu unit)

## Perubahan Backend

### Model: `App\Models\Indikator`
- Tambah `pic_units` ke `$fillable`
- Tambah cast `pic_units` => `array`

### Controller: `App\Http\Controllers\IndikatorsController`
- **Method `store()`**: Support menerima dan menyimpan `pic_units` sebagai array
- **Method `getByUnit()`**: Update query untuk mencari indikator berdasarkan:
  - `kode_unit` cocok (indikator lama), ATAU
  - `pic_units` mengandung kode_unit (indikator baru)

### Request Validator: `App\Http\Requests\StoreIndikatorRequest`
- `kode_unit`: changed to nullable
- `pic`: changed to nullable
- **New:** `pic_units` - array (nullable)
- **New:** `pic_units.*` - validasi setiap unit code

## Perubahan Frontend

### File: `resources/js/pages/Indikator.vue`

#### State Management
- **Tambah:** `selectedPicUnits` - array untuk multi-select

#### Functions
- **New:** `openAddIndicatorModal()` - membuka modal tambah indikator global
- **Modified:** `addIndicatorSave()` - kirim `pic_units` array, bukan `kode_unit` single
- **Modified:** `makeRows()` - hapus tombol "➕ Tambah Indikator" per unit
- **Modified:** `onTableAction()` - hapus handler untuk `dt-add`

#### Template
- **Tambah:** Tombol global "Tambah Indikator Baru" di atas tabel
- **Modified:** Modal Tambah - ganti field Unit & Tim Unit dengan multi-select checkbox PIC Units
- **Modified:** Modal View - tampilkan multiple PIC sebagai badges jika ada

## Cara Penggunaan Baru

### Menambah Indikator Baru:
1. Klik tombol "Tambah Indikator Baru" di atas tabel
2. Pilih unit-unit yang menjadi PIC (bisa lebih dari 1) dengan checkbox
3. Isi Indikator, Standar, Numerator, Denominator
4. Klik "Simpan"

### Melihat Indikator:
- Klik tombol 👁️ "Lihat" pada unit
- Akan tampil semua indikator yang:
  - PIC-nya mengandung unit tersebut, ATAU
  - Indikator lama yang terikat ke unit tersebut

### PIC Display:
- Jika indikator baru (dengan `pic_units`): Tampil sebagai badges multiple unit
- Jika indikator lama (dengan `pic`): Tampil sebagai text biasa

## Kompatibilitas Backward

Sistem ini **backward compatible** dengan indikator lama:
- Indikator lama yang punya `kode_unit` dan `pic` masih bisa ditampilkan
- Query di `getByUnit()` support kedua format (lama & baru)
- UI otomatis detect dan tampilkan format yang sesuai

## Testing

Untuk testing fitur baru:
1. Buat indikator baru dengan multiple PIC
2. Cek apakah indikator muncul di semua unit yang dipilih sebagai PIC
3. Cek apakah modal view menampilkan PIC dengan benar (badges)
4. Pastikan indikator lama masih bisa ditampilkan dengan baik

## Notes

- Field `kode_unit` dan `tim_unit` sekarang nullable untuk indikator baru
- Indikator baru tidak terikat ke satu unit tertentu
- Sistem menggunakan `pic_units` (JSON array) untuk multi-PIC
- UI menggunakan checkbox multi-select untuk memilih PIC
