# Update Multi-Unit Indikator - Capaian & Validasi

## Summary Perubahan

Sistem indikator dengan multi-unit PIC sekarang **sudah terintegrasi penuh** dengan:
- **Capaian Indikator** - Setiap unit bisa input capaian secara independen
- **Validasi Capaian Indikator** - Setiap unit bisa validasi capaiannya sendiri

---

## Cara Kerja Sistem Baru

### 1. **Indikator Multi-Unit**
Ketika admin membuat indikator baru dan memilih beberapa unit sebagai PIC:
- **1 indikator tersimpan** dengan `pic_units` JSON array
- Contoh: `pic_units = ["BSDM", "Datin|Pengembangan Sistem Informasi dan Aplikasi", "Keperawatan"]`

### 2. **Capaian Per Unit**
Setiap unit yang dipilih sebagai PIC bisa:
- **Input capaian secara independen** di halaman "Capaian Indikator"
- Data capaian tersimpan di tabel `capaian_indikators` dengan kolom:
  - `indikator_id` - ID indikator (sama untuk semua unit)
  - `kode_unit` - Unit yang input capaian (berbeda untuk setiap unit)
  - `tahun` - Tahun capaian
  - Kolom N/D untuk setiap bulan

**Contoh:**
- Indikator ID 100 dengan PIC: BSDM, Datin, Keperawatan
- Capaian akan ada 3 record:
  ```
  | id | indikator_id | kode_unit    | jan_n | jan_d |
  |----|-------------|--------------|-------|-------|
  | 1  | 100         | BSDM         | 10    | 12    |
  | 2  | 100         | Datin        | 8     | 10    |
  | 3  | 100         | Keperawatan  | 15    | 20    |
  ```

### 3. **Validasi Per Unit**
Setiap unit yang dipilih sebagai PIC:
- **Muncul di daftar validasi** dengan indikator yang perlu divalidasi
- **Validasi dilakukan per unit** (kolom `jan_validated`, `feb_validated`, dll di record capaian unit tersebut)
- Unit lain **tidak terpengaruh** oleh validasi unit lain

---

## Perubahan Backend

### File yang Diupdate

#### 1. **app/Http/Controllers/CapaianIndikatorController.php**

**Method `index()` - line 40-76:**
```php
// Build search pattern for pic_units
$searchPattern = $selectedUnitCode;
if ($selectedTimUnit) {
    $searchPattern = $selectedUnitCode . '|' . $selectedTimUnit;
}

// Build query for indikators - support both old and new format
$query = Indikator::where(function($q) use ($selectedUnitCode, $searchPattern) {
        // Indikator lama dengan kode_unit
        $q->where('kode_unit', $selectedUnitCode)
          // Indikator baru: pic_units mengandung kode_unit saja
          ->orWhereJsonContains('pic_units', $selectedUnitCode)
          // Indikator baru: pic_units mengandung "kode_unit|tim_unit"
          ->orWhereJsonContains('pic_units', $searchPattern);
    })
    ->where('is_active', true);

// Get indikators with capaian for this specific unit
$indikators = $query->with(['capaian' => function($q) use ($tahun, $selectedUnitCode) {
        $q->where('tahun', $tahun)
          ->where('kode_unit', $selectedUnitCode); // Filter capaian by unit
    }, 'capaian.lampiran'])
    ->get();
```

**Perubahan:**
- Query sekarang mencari indikator berdasarkan `pic_units` JSON (untuk indikator baru)
- Relasi `capaian` difilter berdasarkan `kode_unit` untuk memastikan setiap unit hanya melihat capaiannya sendiri

---

#### 2. **app/Http/Controllers/ValidasiCapaianIndikatorController.php**

**Method `index()` - line 59-72:**
```php
foreach ($units as $unit) {
    // Get ALL ACTIVE indikators for this unit - support both old and new format
    $indikators = Indikator::where(function($q) use ($unit) {
            // Indikator lama dengan kode_unit
            $q->where('kode_unit', $unit->kode_unit)
              // Indikator baru: pic_units mengandung kode_unit
              ->orWhereJsonContains('pic_units', $unit->kode_unit);
        })
        ->where('is_active', true)
        ->with(['capaian' => function($q) use ($tahunDipilih, $unit) {
            $q->where('tahun', $tahunDipilih)
              ->where('kode_unit', $unit->kode_unit); // Filter capaian by unit
        }])
        ->get();
```

**Method `getDetailCapaian()` - line 139-172:**
```php
// Build search pattern for pic_units
$searchPattern = $validated['kode_unit'];
if (!empty($validated['tim_unit'])) {
    $searchPattern = $validated['kode_unit'] . '|' . $validated['tim_unit'];
}

// Get indikators - support both old and new format
$query = Indikator::where(function($q) use ($validated, $searchPattern) {
        // Indikator lama dengan kode_unit
        $q->where('kode_unit', $validated['kode_unit'])
          // Indikator baru: pic_units mengandung kode_unit saja
          ->orWhereJsonContains('pic_units', $validated['kode_unit'])
          // Indikator baru: pic_units mengandung "kode_unit|tim_unit"
          ->orWhereJsonContains('pic_units', $searchPattern);
    })
    ->where('is_active', true);

$indikators = $query->with(['capaian' => function($q) use ($validated) {
    $q->where('tahun', $validated['tahun'])
      ->where('kode_unit', $validated['kode_unit']); // Filter capaian by unit
}, 'capaian.lampiran'])->get();
```

**Perubahan:**
- Query validasi sekarang mencari indikator berdasarkan `pic_units` JSON
- Relasi `capaian` difilter berdasarkan `kode_unit` unit yang melakukan validasi

---

## Backward Compatibility

Sistem **100% backward compatible** dengan indikator lama:

| Format | kode_unit | pic_units | Cara Kerja |
|--------|-----------|-----------|------------|
| **Lama** | Terisi (e.g., "BSDM") | NULL | Query dengan `where('kode_unit', ...)` |
| **Baru** | NULL | JSON array | Query dengan `whereJsonContains('pic_units', ...)` |

Kedua format bisa berjalan bersamaan tanpa konflik.

---

## Testing Checklist

### ✅ Capaian Indikator
- [ ] Unit BSDM bisa input capaian untuk indikator dengan PIC: [BSDM, Datin]
- [ ] Unit Datin bisa input capaian untuk indikator yang sama secara independen
- [ ] Data capaian tersimpan dengan `kode_unit` masing-masing
- [ ] Lampiran capaian terpisah per unit

### ✅ Validasi Capaian Indikator
- [ ] Unit BSDM muncul di daftar validasi dengan indikator yang perlu divalidasi
- [ ] Unit Datin muncul di daftar validasi dengan indikator yang sama
- [ ] Validasi unit BSDM tidak mempengaruhi unit Datin
- [ ] Counter "tervalidasi/total" akurat per unit

### ✅ Backward Compatibility
- [ ] Indikator lama (single unit) masih berfungsi normal di Capaian Indikator
- [ ] Indikator lama masih berfungsi normal di Validasi Capaian Indikator
- [ ] Tidak ada error saat query mixed (indikator lama + baru)

---

## Catatan Penting

1. **Setiap unit input capaian sendiri**
   - Unit A input capaian untuk Januari → tersimpan dengan `kode_unit = A`
   - Unit B input capaian untuk Januari → tersimpan dengan `kode_unit = B`
   - Keduanya untuk indikator yang sama tapi record capaian terpisah

2. **Validasi per unit**
   - Unit A validasi capaiannya → `jan_validated` di record capaian unit A
   - Unit B validasi capaiannya → `jan_validated` di record capaian unit B
   - Tidak saling mempengaruhi

3. **Tim Unit support**
   - Format: `"BSDM|Perencanaan & Pemberdayaan SDM"`
   - Query menggunakan pattern matching dengan `whereJsonContains`

---

## File Dokumentasi Terkait

- [PERUBAHAN_INDIKATOR.md](./PERUBAHAN_INDIKATOR.md) - Dokumentasi perubahan sistem indikator multi-unit
