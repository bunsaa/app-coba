# Perubahan: Multi-Record untuk Multi-Unit Indikator

## 📋 Kebutuhan Baru

**Requirement dari User:**
> "1 indikator bisa untuk beberapa tim, dan saat disimpan di database dia menginsert indikatornya sesuai dengan jumlah tim."

**Contoh Kasus:**
```
Indikator: test123
PIC:
  - BSDM - Diklat, Pendidikan & Penelitian
  - DATIN - Pengelolaan Sistem dan Database
  - DEWAS - Dewan Pengawas

Hasil di Database (3 records terpisah):
┌────┬───────────┬──────────────────────────────────────┬────────────┬─────┐
│ ID │ kode_unit │ tim_unit                             │ indikator  │ pic │
├────┼───────────┼──────────────────────────────────────┼────────────┼─────┤
│ 1  │ BSDM      │ Diklat, Pendidikan & Penelitian      │ test123    │ ... │
│ 2  │ DATIN     │ Pengelolaan Sistem dan Database      │ test123    │ ... │
│ 3  │ DEWAS     │ NULL                                 │ test123    │ ... │
└────┴───────────┴──────────────────────────────────────┴────────────┴─────┘
```

**Benefit:**
- Setiap unit punya **record indikator sendiri**
- Mudah di-query saat penarikan data di Capaian Indikator & Validasi
- Tidak perlu parse JSON `pic_units`
- Backward compatible dengan indikator single-unit

---

## ✅ Perubahan yang Dilakukan

### 1. **Update Controller: Create Multiple Records**
**File**: `app/Http/Controllers/IndikatorsController.php`

**Method**: `store()`

#### Logic Baru:
```php
public function store(StoreIndikatorRequest $request)
{
    $data = $request->validated();
    $createdCount = 0;

    // Jika ada pic_units (multi-select), create multiple records
    if (isset($data['pic_units']) && is_array($data['pic_units']) && count($data['pic_units']) > 0) {

        foreach ($data['pic_units'] as $picUnit) {
            // Parse format: "KODE_UNIT|Tim Unit" atau "KODE_UNIT"
            $parts = explode('|', $picUnit, 2);
            $kodeUnit = trim($parts[0]);
            $timUnit = isset($parts[1]) ? trim($parts[1]) : null;

            // Create indikator record untuk setiap unit
            $indikatorData = [
                'kode_unit' => $kodeUnit,              // ✅ BSDM, DATIN, DEWAS
                'tim_unit' => $timUnit,                // ✅ "Diklat, Pendidikan & Penelitian" atau NULL
                'indikator' => $data['indikator'],     // ✅ Sama untuk semua record
                'standar' => $data['standar'],         // ✅ Sama untuk semua record
                'pic' => $timUnit ?: $kodeUnit,        // ✅ Tim Unit atau Kode Unit
                'pic_units' => null,                   // ✅ Tidak menggunakan JSON
                'numerator' => $data['numerator'],     // ✅ Sama untuk semua record
                'denominator' => $data['denominator'], // ✅ Sama untuk semua record
                'is_active' => true,
            ];

            Indikator::create($indikatorData);
            $createdCount++;
        }

        return redirect()->back()->with('success', "Indikator berhasil ditambahkan untuk {$createdCount} unit!");
    }

    // Fallback: single record untuk backward compatibility
    // ...
}
```

#### Parsing Format `pic_units`:

| Input Format | Hasil Parse |
|-------------|-------------|
| `"BSDM\|Diklat, Pendidikan & Penelitian"` | `kode_unit = "BSDM"`, `tim_unit = "Diklat, Pendidikan & Penelitian"` |
| `"DATIN\|Pengelolaan Sistem dan Database"` | `kode_unit = "DATIN"`, `tim_unit = "Pengelolaan Sistem dan Database"` |
| `"DEWAS"` | `kode_unit = "DEWAS"`, `tim_unit = NULL` |

---

### 2. **Update Validation: Allow Duplicate Indikator Name**
**File**: `app/Http/Requests/StoreIndikatorRequest.php`

**Perubahan:**
```php
// SEBELUM:
'indikator' => 'required|string|unique:indikators,indikator', // ❌ Tidak boleh duplikat

// SESUDAH:
'indikator' => 'required|string', // ✅ Boleh duplikat (untuk multi-unit)
```

**Alasan:**
- Karena sekarang **1 indikator bisa punya multiple records** (satu per unit)
- Nama indikator yang sama boleh muncul di beberapa record

---

### 3. **Frontend: Prevent Double Click (Tetap Dipertahankan)**
**File**: `resources/js/pages/Indikator.vue`

**Catatan:**
- `isSubmitting` flag **tetap diperlukan** untuk mencegah user double-click tombol "Simpan"
- Ini bukan untuk mencegah multiple records (yang memang diinginkan), tapi untuk mencegah request duplicate

```typescript
const isSubmitting = ref(false); // Prevent double click saat request sedang diproses

function addIndicatorSave() {
  if (isSubmitting.value) {
    console.log('Already submitting, ignoring duplicate request');
    return;
  }

  // ... validation

  isSubmitting.value = true;

  router.post('/indikator', { /* data */ }, {
    onFinish: () => {
      isSubmitting.value = false; // Reset after request completes
    }
  });
}
```

---

## 🔄 Alur Kerja Sistem

### Input dari Frontend:
```javascript
{
  indikator: "test123",
  standar: "100",
  pic_units: [
    "BSDM|Diklat, Pendidikan & Penelitian",
    "DATIN|Pengelolaan Sistem dan Database",
    "DEWAS"
  ],
  numerator: "test",
  denominator: "test"
}
```

### Proses di Backend:
1. ✅ Loop through `pic_units` array
2. ✅ Parse setiap item → extract `kode_unit` dan `tim_unit`
3. ✅ Create **1 record indikator** untuk setiap unit
4. ✅ Total: **3 records** dibuat di database

### Output di Database:
```sql
-- Record 1
INSERT INTO indikators (kode_unit, tim_unit, indikator, standar, pic, numerator, denominator, is_active)
VALUES ('BSDM', 'Diklat, Pendidikan & Penelitian', 'test123', '100', 'Diklat, Pendidikan & Penelitian', 'test', 'test', 1);

-- Record 2
INSERT INTO indikators (kode_unit, tim_unit, indikator, standar, pic, numerator, denominator, is_active)
VALUES ('DATIN', 'Pengelolaan Sistem dan Database', 'test123', '100', 'Pengelolaan Sistem dan Database', 'test', 'test', 1);

-- Record 3
INSERT INTO indikators (kode_unit, tim_unit, indikator, standar, pic, numerator, denominator, is_active)
VALUES ('DEWAS', NULL, 'test123', '100', 'DEWAS', 'test', 'test', 1);
```

---

## 📊 Keuntungan Approach Ini

### ✅ Pros:
1. **Query Sederhana**: Tidak perlu `whereJsonContains()` untuk cari indikator per unit
2. **Backward Compatible**: Indikator lama (single unit) tetap berfungsi
3. **Independent Records**: Setiap unit punya record sendiri
4. **Easy Filtering**: `WHERE kode_unit = 'BSDM'` langsung dapat semua indikator unit BSDM
5. **Sudah Terbaca di Validasi**: Query existing di `CapaianIndikatorController` & `ValidasiCapaianIndikatorController` langsung bisa baca data ini

### ⚠️ Cons:
1. **Duplicate Data**: Nama indikator, standar, numerator, denominator tersimpan berulang
2. **Update Challenge**: Jika ingin update indikator, harus update **semua records** dengan nama indikator yang sama
3. **Delete Challenge**: Jika ingin hapus indikator, harus hapus **semua records** dengan nama indikator yang sama

---

## 🧪 Testing Checklist

### Test Case 1: Create Multi-Unit Indikator
1. ✅ Buka form "Tambah Indikator"
2. ✅ Isi field:
   - Indikator: `test123`
   - Standar: `100`
   - PIC: Pilih 3 unit berbeda (misal: BSDM - Tim A, DATIN - Tim B, DEWAS)
   - Numerator: `test`
   - Denominator: `test`
3. ✅ Klik "Simpan"
4. ✅ **Expected**:
   - Success message: "Indikator berhasil ditambahkan untuk 3 unit!"
   - Di database ada **3 records baru**
   - Setiap record punya `kode_unit` dan `tim_unit` yang berbeda

### Test Case 2: Verify Query di Capaian Indikator
1. ✅ Login sebagai unit BSDM
2. ✅ Buka halaman Capaian Indikator
3. ✅ **Expected**: Indikator `test123` muncul di daftar unit BSDM

### Test Case 3: Verify Query di Validasi Capaian Indikator
1. ✅ Login sebagai unit BSDM
2. ✅ Input capaian untuk indikator `test123`
3. ✅ Buka halaman Validasi Capaian Indikator
4. ✅ **Expected**: Indikator `test123` muncul di daftar validasi unit BSDM

### Test Case 4: Verify di Database
```sql
SELECT * FROM indikators WHERE indikator = 'test123';
```
**Expected**:
- 3 rows returned
- Setiap row punya `kode_unit` berbeda
- Field `pic_units` = NULL (tidak menggunakan JSON array)

---

## 📝 SQL Query untuk Verify

### Cek semua indikator untuk unit BSDM:
```sql
SELECT id, kode_unit, tim_unit, indikator, standar, pic
FROM indikators
WHERE kode_unit = 'BSDM'
  AND is_active = 1;
```

### Cek semua records untuk indikator tertentu:
```sql
SELECT id, kode_unit, tim_unit, indikator, standar, pic
FROM indikators
WHERE indikator = 'test123'
  AND is_active = 1;
```

---

## 🔮 Future Considerations

### Jika Perlu Update/Delete Multiple Records:

Akan perlu tambahan logic di controller untuk:

1. **Update Indikator**: Update semua records dengan nama indikator yang sama
```php
// Contoh: Update semua "test123" ke standar baru
Indikator::where('indikator', 'test123')
    ->update(['standar' => '90']);
```

2. **Delete Indikator**: Soft delete semua records dengan nama indikator yang sama
```php
// Contoh: Nonaktifkan semua "test123"
Indikator::where('indikator', 'test123')
    ->update(['is_active' => false]);
```

3. **Toggle Active**: Toggle status semua records sekaligus
```php
// Perlu logic khusus di toggleActive() method
```

---

## 📂 Files Modified

1. ✅ `app/Http/Controllers/IndikatorsController.php` - Update `store()` method
2. ✅ `app/Http/Requests/StoreIndikatorRequest.php` - Remove `unique` rule
3. ✅ `resources/js/pages/Indikator.vue` - Keep prevent double submit

---

**Tanggal Perubahan**: 2025-11-25
**Status**: ✅ Ready for Testing
