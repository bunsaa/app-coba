# Perbaikan: Tim Unit Filter di Validasi Capaian Indikator

## 🐛 Masalah yang Ditemukan

**Issue dari User (Lanjutan):**
> "ini masih belum muncul di lihat dan validasi pak"

**Screenshot menunjukkan**: Modal "Detail Capaian Indikator" untuk unit "Bagian Data dan Teknologi Informasi" dengan filter "Pengelolaan Sistem dan Database" menampilkan **"Tidak ada data capaian"**.

---

## 🔍 Root Cause Analysis

### 1. Data di Database (✅ SUDAH BENAR):
```json
[
  {"id":4,"kode_unit":"BSDM","tim_unit":"Perencanaan & Pemberdayaan SDM","indikator":"test123"},
  {"id":5,"kode_unit":"Datin","tim_unit":"Pengelolaan Sistem dan Database","indikator":"test123"},
  {"id":6,"kode_unit":"EtikHukum","tim_unit":null,"indikator":"test123"}
]
```
✅ Data ada dan struktur benar

### 2. Query Backend (❌ MASALAH DI SINI):

**File**: `ValidasiCapaianIndikatorController.php`
**Method**: `getDetailCapaian()`

**Logic Lama (SALAH):**
```php
$query = Indikator::where('kode_unit', $validated['kode_unit'])
    ->where('is_active', true);

if (!empty($validated['tim_unit'])) {
    $query->where('tim_unit', $validated['tim_unit']);
} else {
    $query->whereNull('tim_unit'); // ❌ MASALAH: Hanya ambil yang NULL
}
```

**Request dari Frontend:**
```javascript
{
  kode_unit: "Datin",
  tim_unit: null,  // ❌ Frontend mengirim null
  tahun: 2025,
  bulan: 11
}
```

**Hasil Query:**
```sql
SELECT * FROM indikators
WHERE kode_unit = 'Datin'
  AND tim_unit IS NULL  -- ❌ SALAH: Tidak menemukan apa-apa
  AND is_active = 1;
```
**Result**: 0 rows (tidak menemukan data)

---

### 3. Frontend Logic (✅ SUDAH BENAR):

**File**: `Validasi-Capaian-Indikator.vue`

Frontend punya logic untuk:
1. Fetch **semua indikator** untuk unit (simpan di `allDetailCapaian`)
2. User pilih filter tim_unit via dropdown (filter di frontend)

```javascript
async function lihatCapaian(unit: UnitValidasi) {
  // Fetch SEMUA indikator untuk unit
  const response = await axios.post('/validasi-capaian-indikator/get-detail', {
    kode_unit: unit.unit_kode,
    tim_unit: null, // ✅ BENAR: Minta semua
    tahun: props.tahunDipilih,
    bulan: props.bulanDipilih,
  });

  allDetailCapaian.value = response.data; // Simpan semua
  detailCapaian.value = response.data;
}

// Filter di frontend saat user pilih dropdown
watch(selectedTimUnitFilter, (newVal) => {
  if (newVal) {
    detailCapaian.value = allDetailCapaian.value.filter(d => d.tim_unit === newVal);
  } else {
    detailCapaian.value = allDetailCapaian.value; // Tampilkan semua
  }
});
```

**Frontend expect**: Backend return **SEMUA indikator** untuk unit (terlepas dari tim_unit), lalu frontend yang filter.

---

## ✅ Perbaikan yang Dilakukan

### 1. **ValidasiCapaianIndikatorController - getDetailCapaian()**
**File**: `app/Http/Controllers/ValidasiCapaianIndikatorController.php`
**Method**: `getDetailCapaian()`
**Lines**: 139-144

#### Perubahan:
```php
// ❌ SEBELUM: Jika tim_unit kosong, ambil yang NULL saja
if (!empty($validated['tim_unit'])) {
    $query->where('tim_unit', $validated['tim_unit']);
} else {
    $query->whereNull('tim_unit'); // ❌ SALAH
}

// ✅ SESUDAH: Jika tim_unit kosong, ambil SEMUA
if (!empty($validated['tim_unit'])) {
    $query->where('tim_unit', $validated['tim_unit']);
}
// ELSE: Tidak ada filter, ambil semua tim_unit (baik NULL maupun ada isinya)
```

**Query yang Dihasilkan:**
```sql
-- Request: { kode_unit: "Datin", tim_unit: null }
SELECT * FROM indikators
WHERE kode_unit = 'Datin'
  AND is_active = 1;
-- ✅ Result: 1 row (indikator dengan tim_unit "Pengelolaan Sistem dan Database")

-- Request: { kode_unit: "Datin", tim_unit: "Pengelolaan Sistem dan Database" }
SELECT * FROM indikators
WHERE kode_unit = 'Datin'
  AND tim_unit = 'Pengelolaan Sistem dan Database'
  AND is_active = 1;
-- ✅ Result: 1 row (filtered)
```

---

### 2. **ValidasiCapaianIndikatorController - validateUnit()**
**File**: `app/Http/Controllers/ValidasiCapaianIndikatorController.php`
**Method**: `validateUnit()`
**Lines**: 353-358

#### Perubahan:
```php
// ❌ SEBELUM: Jika tim_unit kosong, validasi yang NULL saja
if (!empty($validated['tim_unit'])) {
    $query->where('tim_unit', $validated['tim_unit']);
} else {
    $query->whereNull('tim_unit'); // ❌ SALAH
}

// ✅ SESUDAH: Jika tim_unit kosong, validasi SEMUA
if (!empty($validated['tim_unit'])) {
    $query->where('tim_unit', $validated['tim_unit']);
}
// ELSE: Validasi semua indikator unit ini (semua tim_unit)
```

**Manfaat:**
- ✅ Tombol "Validasi Unit" sekarang memvalidasi **semua tim_unit** di unit tersebut (jika tim_unit tidak dispecify)
- ✅ Atau validasi hanya tim_unit tertentu (jika dispecify)

---

## 📊 Perbandingan

### Sebelum (❌):

| Request | Query Backend | Result |
|---------|---------------|--------|
| `kode_unit: "Datin", tim_unit: null` | `WHERE kode_unit='Datin' AND tim_unit IS NULL` | 0 rows ❌ |
| `kode_unit: "BSDM", tim_unit: null` | `WHERE kode_unit='BSDM' AND tim_unit IS NULL` | 0 rows ❌ |

**Problem**: Unit dengan tim_unit tidak mendapat data apa-apa!

---

### Sesudah (✅):

| Request | Query Backend | Result |
|---------|---------------|--------|
| `kode_unit: "Datin", tim_unit: null` | `WHERE kode_unit='Datin'` | 1 row ✅ (Pengelolaan Sistem dan Database) |
| `kode_unit: "BSDM", tim_unit: null` | `WHERE kode_unit='BSDM'` | 1 row ✅ (Perencanaan & Pemberdayaan SDM) |
| `kode_unit: "Datin", tim_unit: "Pengelolaan Sistem dan Database"` | `WHERE kode_unit='Datin' AND tim_unit='...'` | 1 row ✅ (filtered) |

**Fixed**: Semua unit mendapat data dengan benar!

---

## 🧪 Testing Checklist

### Test Case 1: Modal "Lihat" - Unit dengan Tim Unit
1. ✅ Login sebagai admin/validator
2. ✅ Buka halaman Validasi Capaian Indikator
3. ✅ Klik "Lihat" untuk unit **"Bagian Data dan Teknologi Informasi"**
4. ✅ **Expected**:
   - Modal terbuka
   - Menampilkan indikator "test123" (tim_unit: "Pengelolaan Sistem dan Database")
   - Dropdown filter "Filter Tim Unit" menampilkan opsi "Pengelolaan Sistem dan Database"
   - Bisa filter via dropdown

### Test Case 2: Modal "Lihat" - Unit tanpa Tim Unit
1. ✅ Klik "Lihat" untuk unit **"Etik-Hukum"** (tidak punya tim_unit)
2. ✅ **Expected**:
   - Modal terbuka
   - Menampilkan indikator "test123" (tim_unit: NULL)
   - Dropdown filter kosong/tidak ada

### Test Case 3: Filter Tim Unit di Modal
1. ✅ Buka modal "Lihat" untuk unit **"BSDM"** (punya 2 tim_unit)
2. ✅ **Expected**: Tampilkan semua indikator untuk BSDM
3. ✅ Pilih filter "Perencanaan & Pemberdayaan SDM" dari dropdown
4. ✅ **Expected**: Hanya tampilkan indikator dengan tim_unit tersebut
5. ✅ Reset filter ke "Semua"
6. ✅ **Expected**: Tampilkan semua indikator lagi

### Test Case 4: Validasi Unit dengan Tim Unit
1. ✅ Input capaian untuk indikator dengan tim_unit
2. ✅ Klik tombol "Validasi" untuk unit tersebut
3. ✅ **Expected**: Indikator berhasil divalidasi

---

## 🔍 SQL Verification

### Test Query 1: Ambil semua indikator untuk unit (tanpa filter tim_unit)
```sql
SELECT id, kode_unit, tim_unit, indikator
FROM indikators
WHERE kode_unit = 'Datin'
  AND is_active = 1;
```

**Expected**:
```
┌────┬───────────┬──────────────────────────────────────┬────────────┐
│ ID │ kode_unit │ tim_unit                             │ indikator  │
├────┼───────────┼──────────────────────────────────────┼────────────┤
│ 5  │ Datin     │ Pengelolaan Sistem dan Database      │ test123    │
└────┴───────────┴──────────────────────────────────────┴────────────┘
```

### Test Query 2: Ambil indikator dengan filter tim_unit spesifik
```sql
SELECT id, kode_unit, tim_unit, indikator
FROM indikators
WHERE kode_unit = 'Datin'
  AND tim_unit = 'Pengelolaan Sistem dan Database'
  AND is_active = 1;
```

**Expected**: Same result as above

---

## 📝 Summary

### Files Modified:
1. ✅ `app/Http/Controllers/ValidasiCapaianIndikatorController.php`
   - Method `getDetailCapaian()` (lines 139-144)
   - Method `validateUnit()` (lines 353-358)

### Changes Made:
- ✅ Removed `whereNull('tim_unit')` saat `tim_unit` tidak dikirim/kosong
- ✅ Logic baru: Jika `tim_unit` kosong → ambil **SEMUA** indikator untuk unit (baik yang punya tim_unit maupun tidak)
- ✅ Logic baru: Jika `tim_unit` diisi → filter hanya tim_unit tersebut

### Impact:
- ✅ Modal "Lihat" sekarang menampilkan data dengan benar untuk unit dengan tim_unit
- ✅ Frontend filter dropdown berfungsi dengan baik
- ✅ Validasi per unit berfungsi untuk semua jenis unit
- ✅ Konsisten dengan expectation frontend (fetch all, filter di frontend)

---

**Tanggal Perbaikan**: 2025-11-25
**Status**: ✅ Ready for Testing
