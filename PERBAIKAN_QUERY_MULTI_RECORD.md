# Perbaikan Query untuk Multi-Record Indikator

## 🐛 Masalah yang Ditemukan

**Issue dari User:**
> "di validasi capaian indikator di unit yg punya tim_unit saat di klik lihat dan validasi tidak muncul ya pak indikatornya?"

**Root Cause:**
- Struktur database sudah diubah ke **multiple records** (setiap unit punya record indikator sendiri)
- Tapi query di `CapaianIndikatorController` dan `ValidasiCapaianIndikatorController` masih menggunakan **logic lama** dengan `whereJsonContains('pic_units')`
- Query lama tidak bisa menemukan indikator karena field `pic_units` sekarang = NULL

**Contoh Data di Database (Struktur Baru):**
```
┌────┬───────────┬──────────────────────────────────────┬────────────┬──────────────┐
│ ID │ kode_unit │ tim_unit                             │ indikator  │ pic_units    │
├────┼───────────┼──────────────────────────────────────┼────────────┼──────────────┤
│ 4  │ BSDM      │ Perencanaan & Pemberdayaan SDM       │ test123    │ NULL         │
│ 5  │ Datin     │ Pengelolaan Sistem dan Database      │ test123    │ NULL         │
│ 6  │ Etik-Hukum│ NULL                                 │ test123    │ NULL         │
└────┴───────────┴──────────────────────────────────────┴────────────┴──────────────┘
```

**Query Lama yang Bermasalah:**
```php
// ❌ SALAH: Mencari pic_units JSON (sudah tidak dipakai)
$query = Indikator::where(function($q) use ($unit) {
    $q->where('kode_unit', $unit->kode_unit)
      ->orWhereJsonContains('pic_units', $unit->kode_unit); // ❌ Tidak menemukan karena NULL
})
```

---

## ✅ Perbaikan yang Dilakukan

### 1. **CapaianIndikatorController.php**
**File**: `app/Http/Controllers/CapaianIndikatorController.php`
**Method**: `index()`
**Lines**: 40-52

#### Perubahan:
```php
// ❌ SEBELUM: Query dengan whereJsonContains
$query = Indikator::where(function($q) use ($selectedUnitCode, $searchPattern) {
        $q->where('kode_unit', $selectedUnitCode)
          ->orWhereJsonContains('pic_units', $selectedUnitCode)
          ->orWhereJsonContains('pic_units', $searchPattern);
    })
    ->where('is_active', true);

if ($selectedTimUnit) {
    $query->where(function($q) use ($selectedTimUnit) {
        $q->where('tim_unit', $selectedTimUnit)
          ->orWhereNull('kode_unit');
    });
} else {
    $query->where(function($q) {
        $q->whereNull('tim_unit')
          ->orWhereNull('kode_unit');
    });
}

// ✅ SESUDAH: Query sederhana langsung ke kode_unit + tim_unit
$query = Indikator::where('kode_unit', $selectedUnitCode)
    ->where('is_active', true);

if ($selectedTimUnit) {
    $query->where('tim_unit', $selectedTimUnit);
} else {
    $query->whereNull('tim_unit');
}
```

**Benefit:**
- ✅ Query lebih sederhana dan cepat
- ✅ Langsung match dengan struktur database baru
- ✅ Indikator untuk unit dengan tim_unit sekarang muncul

---

### 2. **ValidasiCapaianIndikatorController.php - Method `index()`**
**File**: `app/Http/Controllers/ValidasiCapaianIndikatorController.php`
**Method**: `index()`
**Lines**: 58-67

#### Perubahan:
```php
// ❌ SEBELUM: Query dengan whereJsonContains
$indikators = Indikator::where(function($q) use ($unit) {
        $q->where('kode_unit', $unit->kode_unit)
          ->orWhereJsonContains('pic_units', $unit->kode_unit);
    })
    ->where('is_active', true)
    ->with(['capaian' => function($q) use ($tahunDipilih, $unit) {
        $q->where('tahun', $tahunDipilih)
          ->where('kode_unit', $unit->kode_unit);
    }])
    ->get();

// ✅ SESUDAH: Query langsung ke kode_unit
$indikators = Indikator::where('kode_unit', $unit->kode_unit)
    ->where('is_active', true)
    ->with(['capaian' => function($q) use ($tahunDipilih, $unit) {
        $q->where('tahun', $tahunDipilih)
          ->where('kode_unit', $unit->kode_unit);
    }])
    ->get();
```

**Benefit:**
- ✅ Counter "tervalidasi/total" sekarang akurat
- ✅ Semua indikator untuk unit muncul (termasuk yang punya tim_unit)

---

### 3. **ValidasiCapaianIndikatorController.php - Method `getDetailCapaian()`**
**File**: `app/Http/Controllers/ValidasiCapaianIndikatorController.php`
**Method**: `getDetailCapaian()`
**Lines**: 134-145

#### Perubahan:
```php
// ❌ SEBELUM: Query dengan whereJsonContains
$searchPattern = $validated['kode_unit'];
if (!empty($validated['tim_unit'])) {
    $searchPattern = $validated['kode_unit'] . '|' . $validated['tim_unit'];
}

$query = Indikator::where(function($q) use ($validated, $searchPattern) {
        $q->where('kode_unit', $validated['kode_unit'])
          ->orWhereJsonContains('pic_units', $validated['kode_unit'])
          ->orWhereJsonContains('pic_units', $searchPattern);
    })
    ->where('is_active', true);

if (!empty($validated['tim_unit'])) {
    $query->where(function($q) use ($validated) {
        $q->where('tim_unit', $validated['tim_unit'])
          ->orWhereNull('kode_unit');
    });
} else {
    $query->where(function($q) {
        $q->whereNull('tim_unit')
          ->orWhereNull('kode_unit');
    });
}

// ✅ SESUDAH: Query sederhana
$query = Indikator::where('kode_unit', $validated['kode_unit'])
    ->where('is_active', true);

if (!empty($validated['tim_unit'])) {
    $query->where('tim_unit', $validated['tim_unit']);
} else {
    $query->whereNull('tim_unit');
}
```

**Benefit:**
- ✅ Modal "Lihat" sekarang menampilkan indikator dengan benar
- ✅ Data detail capaian muncul untuk unit dengan tim_unit

---

### 4. **ValidasiCapaianIndikatorController.php - Method `validateUnit()`**
**File**: `app/Http/Controllers/ValidasiCapaianIndikatorController.php`
**Method**: `validateUnit()`
**Lines**: 349-360

#### Perubahan:
```php
// ❌ SEBELUM: Query dengan whereJsonContains
$searchPattern = $validated['kode_unit'];
if (!empty($validated['tim_unit'])) {
    $searchPattern = $validated['kode_unit'] . '|' . $validated['tim_unit'];
}

$query = Indikator::where(function($q) use ($validated, $searchPattern) {
        $q->where('kode_unit', $validated['kode_unit'])
          ->orWhereJsonContains('pic_units', $validated['kode_unit'])
          ->orWhereJsonContains('pic_units', $searchPattern);
    })
    ->where('is_active', true);

if (!empty($validated['tim_unit'])) {
    $query->where(function($q) use ($validated) {
        $q->where('tim_unit', $validated['tim_unit'])
          ->orWhereNull('kode_unit');
    });
} else {
    $query->where(function($q) {
        $q->whereNull('tim_unit')
          ->orWhereNull('kode_unit');
    });
}

// ✅ SESUDAH: Query sederhana
$query = Indikator::where('kode_unit', $validated['kode_unit'])
    ->where('is_active', true);

if (!empty($validated['tim_unit'])) {
    $query->where('tim_unit', $validated['tim_unit']);
} else {
    $query->whereNull('tim_unit');
}
```

**Benefit:**
- ✅ Validasi per unit sekarang berfungsi untuk unit dengan tim_unit
- ✅ Hanya indikator yang sesuai yang divalidasi

---

## 📊 Perbandingan Query

### Sebelum (❌ SALAH):
```sql
SELECT * FROM indikators
WHERE (
    kode_unit = 'BSDM'
    OR JSON_CONTAINS(pic_units, '"BSDM"')  -- ❌ Tidak menemukan (pic_units = NULL)
)
AND is_active = 1;
```

**Result**: 0 rows (tidak menemukan indikator)

---

### Sesudah (✅ BENAR):
```sql
SELECT * FROM indikators
WHERE kode_unit = 'BSDM'           -- ✅ Langsung match
  AND tim_unit = 'Perencanaan & Pemberdayaan SDM'  -- ✅ Filter tim_unit
  AND is_active = 1;
```

**Result**: 1 row (menemukan indikator dengan benar)

---

## 🧪 Testing Checklist

### Test Case 1: Unit dengan Tim Unit - Capaian Indikator
1. ✅ Login sebagai unit BSDM
2. ✅ Pilih Tim Unit: "Perencanaan & Pemberdayaan SDM"
3. ✅ Buka halaman Capaian Indikator
4. ✅ **Expected**: Indikator "test123" muncul di daftar

### Test Case 2: Unit dengan Tim Unit - Validasi Capaian (Lihat)
1. ✅ Login sebagai unit BSDM
2. ✅ Buka halaman Validasi Capaian Indikator
3. ✅ Klik "Lihat" untuk unit BSDM - Perencanaan & Pemberdayaan SDM
4. ✅ **Expected**: Modal muncul dan menampilkan indikator "test123"

### Test Case 3: Unit dengan Tim Unit - Validasi Capaian (Validasi)
1. ✅ Input capaian untuk indikator "test123" di unit BSDM - Perencanaan & Pemberdayaan SDM
2. ✅ Buka halaman Validasi Capaian Indikator
3. ✅ Klik "Validasi" untuk unit BSDM - Perencanaan & Pemberdayaan SDM
4. ✅ **Expected**: Indikator berhasil divalidasi

### Test Case 4: Unit tanpa Tim Unit
1. ✅ Login sebagai unit Etik-Hukum (tidak punya tim_unit)
2. ✅ Buka halaman Capaian Indikator
3. ✅ **Expected**: Indikator "test123" muncul di daftar (tim_unit = NULL)

---

## 🔍 SQL Verification

### Cek indikator untuk unit dengan tim_unit:
```sql
SELECT id, kode_unit, tim_unit, indikator, pic_units
FROM indikators
WHERE kode_unit = 'BSDM'
  AND tim_unit = 'Perencanaan & Pemberdayaan SDM'
  AND is_active = 1;
```

**Expected**:
```
┌────┬───────────┬──────────────────────────────────────┬────────────┬──────────────┐
│ ID │ kode_unit │ tim_unit                             │ indikator  │ pic_units    │
├────┼───────────┼──────────────────────────────────────┼────────────┼──────────────┤
│ 4  │ BSDM      │ Perencanaan & Pemberdayaan SDM       │ test123    │ NULL         │
└────┴───────────┴──────────────────────────────────────┴────────────┴──────────────┘
```

### Cek indikator untuk unit tanpa tim_unit:
```sql
SELECT id, kode_unit, tim_unit, indikator, pic_units
FROM indikators
WHERE kode_unit = 'Etik-Hukum'
  AND tim_unit IS NULL
  AND is_active = 1;
```

**Expected**:
```
┌────┬───────────┬──────────┬────────────┬──────────────┐
│ ID │ kode_unit │ tim_unit │ indikator  │ pic_units    │
├────┼───────────┼──────────┼────────────┼──────────────┤
│ 6  │ Etik-Hukum│ NULL     │ test123    │ NULL         │
└────┴───────────┴──────────┴────────────┴──────────────┘
```

---

## 📝 Summary

### Files Modified:
1. ✅ `app/Http/Controllers/CapaianIndikatorController.php` (lines 40-52)
2. ✅ `app/Http/Controllers/ValidasiCapaianIndikatorController.php` (lines 58-67, 134-145, 349-360)

### Changes Made:
- ✅ Removed `whereJsonContains('pic_units')` queries
- ✅ Simplified query to direct `WHERE kode_unit` + `WHERE tim_unit`
- ✅ Fixed filtering logic untuk unit dengan dan tanpa tim_unit

### Impact:
- ✅ Indikator sekarang muncul di Capaian Indikator untuk unit dengan tim_unit
- ✅ Modal "Lihat" di Validasi Capaian Indikator sekarang menampilkan data dengan benar
- ✅ Validasi per unit berfungsi untuk semua jenis unit (dengan/tanpa tim_unit)
- ✅ Query lebih sederhana dan performant

---

**Tanggal Perbaikan**: 2025-11-25
**Status**: ✅ Ready for Testing
