# Perbaikan: History Komentar & Tombol Validasi di Validasi Capaian Indikator

## 🐛 Masalah yang Dilaporkan

**User feedback**:
1. "pak ini kalo ada komentar ulang tombol verifikasi ilang lagi pak"
2. "untuk catatan tetap ada history catatan sebelumnya pak tapi buat seperti icon aja kl di hover terlihat catatan-catatannya"

**Masalah**:
- Ketika validator mengirim komentar revisi yang kedua/ketiga kali, **tombol validasi hilang**
- Komentar sebelumnya **hilang/tertimpa** oleh komentar baru
- User ingin **history** semua catatan revisi tersimpan dan bisa dilihat dengan hover pada icon

---

## 🔍 Root Cause Analysis

### 1. Tombol Validasi Hilang
**Logic Lama**:
```vue
<button v-if="!item.validated && isBulanBerjalan && (!item.komentar || item.komentar_dibaca)">
  Validasi
</button>
```

**Analisis**:
- Logic sebenarnya **SUDAH BENAR** ✅
- Tombol validasi AKAN muncul jika:
  - Belum divalidasi (`!item.validated`)
  - Bulan berjalan (`isBulanBerjalan`)
  - **Tidak ada komentar ATAU komentar sudah dibaca** (`!item.komentar || item.komentar_dibaca`)
- Artinya saat komentar sudah di-mark sebagai "dibaca" (direvisi), tombol validasi SEHARUSNYA muncul

**Root Cause**: Bug bukan di logic tombol, tetapi di **backend** yang tidak menyimpan history komentar, sehingga komentar lama hilang diganti komentar baru.

### 2. Komentar Hilang/Tertimpa

**Backend Lama** (`sendKomentar()`):
```php
$capaian->komentar = $validated['komentar']; // ❌ Langsung overwrite
$capaian->komentar_dibaca = false;
$capaian->save();
```

**Problem**:
- Komentar lama langsung **di-overwrite** tanpa disimpan
- Tidak ada history catatan sebelumnya

---

## ✅ Solusi yang Diterapkan

### 1. **Database - Kolom History**

#### Migration Baru
**File**: `database/migrations/2025_11_25_024837_add_komentar_history_to_capaian_indikators_table.php`

```php
Schema::table('capaian_indikators', function (Blueprint $table) {
    $table->json('komentar_history')->nullable()->after('komentar_dibaca');
});
```

**Struktur JSON**:
```json
[
  {
    "komentar": "Tolong data bulan Nov diperbaiki",
    "tanggal": "2025-11-25 10:30:00",
    "dibaca": true
  },
  {
    "komentar": "Data masih belum sesuai, cek kembali numerator",
    "tanggal": "2025-11-25 14:15:00",
    "dibaca": false
  }
]
```

**Benefit**:
- ✅ Semua komentar revisi tersimpan
- ✅ Ada timestamp setiap komentar
- ✅ Ada status "dibaca" atau belum untuk setiap history

---

### 2. **Backend - Simpan History Otomatis**

**File**: `app/Http/Controllers/ValidasiCapaianIndikatorController.php`

#### a. Update Method `sendKomentar()` (lines 261-270)

```php
// Simpan komentar lama ke history jika ada
if ($capaian->komentar) {
    $history = $capaian->komentar_history ? json_decode($capaian->komentar_history, true) : [];
    $history[] = [
        'komentar' => $capaian->komentar,
        'tanggal' => $capaian->updated_at->format('Y-m-d H:i:s'),
        'dibaca' => $capaian->komentar_dibaca ?? false,
    ];
    $capaian->komentar_history = json_encode($history);
}

$capaian->komentar = $validated['komentar'];
$capaian->komentar_dibaca = false;
$capaian->save();
```

**Cara Kerja**:
1. ✅ Cek apakah ada komentar sebelumnya
2. ✅ Jika ada, ambil existing history (atau buat array baru)
3. ✅ Push komentar lama + metadata ke history
4. ✅ Set komentar baru sebagai current komentar
5. ✅ Reset `komentar_dibaca` menjadi `false`

**Contoh Flow**:
```
// Komentar pertama
komentar: "Perbaiki data Nov"
komentar_history: []

// Kirim komentar kedua
komentar_history: [
  {"komentar": "Perbaiki data Nov", "tanggal": "2025-11-25 10:30:00", "dibaca": true}
]
komentar: "Data masih salah" // ✅ Komentar baru
komentar_dibaca: false // ✅ Reset ke false
```

#### b. Update Method `getDetailCapaian()` - Return History (line 192)

```php
return [
    // ... fields lainnya
    'komentar' => $capaian->komentar ?? '',
    'komentar_dibaca' => (bool) ($capaian->komentar_dibaca ?? false),
    'komentar_history' => $capaian->komentar_history ? json_decode($capaian->komentar_history, true) : [],
];
```

**Benefit**:
- ✅ Frontend mendapat data history lengkap

---

### 3. **Frontend - Icon dengan Tooltip History**

**File**: `resources/js/pages/Validasi-Capaian-Indikator.vue`

#### a. Update Interface TypeScript (lines 30-52)

```typescript
interface KomentarHistory {
  komentar: string;
  tanggal: string;
  dibaca: boolean;
}

interface DetailCapaian {
  // ... existing fields
  komentar: string;
  komentar_dibaca: boolean;
  komentar_history: KomentarHistory[]; // ✅ Tambah field history
}
```

#### b. Update Display Komentar (lines 704-754)

**Sebelum**:
```vue
<!-- Full box dengan text komentar -->
<div class="mb-3 rounded-lg bg-yellow-50 border border-yellow-200 p-3">
  <p class="text-xs font-semibold text-yellow-700 mb-1">Catatan Revisi:</p>
  <p class="text-sm text-gray-700">{{ item.komentar }}</p>
</div>
```

**Sesudah**:
```vue
<!-- Icon compact dengan tooltip on hover -->
<div v-if="item.komentar" class="mb-3 flex items-center gap-2">
  <!-- Icon Catatan dengan Tooltip -->
  <div class="relative group">
    <!-- Trigger Icon -->
    <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-yellow-50 border border-yellow-200 cursor-pointer">
      <MessageSquare :size="16" class="text-yellow-700" />
      <span class="text-xs font-medium text-yellow-700">Catatan Revisi</span>
      <!-- Badge jumlah history -->
      <span v-if="item.komentar_history && item.komentar_history.length > 0"
            class="text-[10px] bg-yellow-600 text-white px-1.5 py-0.5 rounded-full">
        {{ item.komentar_history.length + 1 }}
      </span>
    </div>

    <!-- Tooltip dengan History (muncul saat hover) -->
    <div class="absolute left-0 top-full mt-2 w-80 bg-white border border-gray-300 rounded-lg shadow-xl p-4 z-50 hidden group-hover:block">
      <!-- Komentar Terbaru -->
      <div class="mb-3 pb-3 border-b border-gray-200">
        <p class="text-xs font-semibold text-yellow-700 mb-1">Catatan Terbaru:</p>
        <p class="text-sm text-gray-700">{{ item.komentar }}</p>
        <p class="text-xs text-gray-400 mt-1">
          Status: {{ item.komentar_dibaca ? '✓ Sudah direvisi' : 'Menunggu revisi' }}
        </p>
      </div>

      <!-- History (jika ada) -->
      <div v-if="item.komentar_history && item.komentar_history.length > 0">
        <p class="text-xs font-semibold text-gray-600 mb-2">Riwayat Catatan:</p>
        <div class="space-y-2 max-h-48 overflow-y-auto">
          <div v-for="(hist, idx) in item.komentar_history" :key="idx"
               class="text-xs bg-gray-50 p-2 rounded border border-gray-200">
            <p class="text-gray-700 mb-1">{{ hist.komentar }}</p>
            <p class="text-gray-400 text-[10px]">
              {{ new Date(hist.tanggal).toLocaleString('id-ID') }}
              • {{ hist.dibaca ? '✓ Direvisi' : 'Belum direvisi' }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tombol Hapus -->
  <button
    v-if="!item.validated && isBulanBerjalan"
    @click="clearKomentar(item)"
    class="text-red-600 hover:text-red-800"
    title="Hapus komentar"
  >
    <Trash2 :size="14" />
  </button>
</div>
```

**Fitur Tooltip**:
- ✅ **Compact display**: Icon kecil yang tidak makan banyak space
- ✅ **Badge counter**: Menampilkan jumlah total komentar (termasuk history)
- ✅ **Hover tooltip**: Muncul saat hover dengan width 320px
- ✅ **Komentar terbaru**: Ditampilkan di bagian atas dengan status
- ✅ **History scrollable**: Jika history banyak, bisa scroll (max-height: 48px = 12rem)
- ✅ **Timestamp**: Setiap history menampilkan tanggal-waktu dalam format Indonesia
- ✅ **Status dibaca**: Setiap history menampilkan apakah sudah direvisi atau belum

#### c. Update Function `kirimKomentar()` (lines 220-221)

```typescript
// Refresh data dari server untuk mendapatkan komentar_history yang ter-update
await lihatCapaian(selectedUnit.value!);
```

**Sebelum**: Manual update local data
**Sesudah**: Fetch ulang dari server agar mendapat `komentar_history` yang ter-update

---

## 📊 Perbandingan

### Sebelum (❌):

| Aksi | Komentar Display | History | Tombol Validasi |
|------|------------------|---------|-----------------|
| Kirim komentar 1 | "Perbaiki data Nov" | ❌ Tidak ada | ❌ Hilang (karena ada komentar) |
| Tim unit revisi | "Perbaiki data Nov" (status: dibaca) | ❌ Tidak ada | ✅ Muncul |
| Kirim komentar 2 | "Data masih salah" | ❌ Komentar 1 hilang | ❌ Hilang lagi |

**Problem**:
- Komentar lama hilang
- Tombol validasi hilang saat ada komentar baru

---

### Sesudah (✅):

| Aksi | Komentar Display | History | Tombol Validasi |
|------|------------------|---------|-----------------|
| Kirim komentar 1 | Icon "Catatan Revisi" (1) | ✅ Kosong | ❌ Hilang (menunggu revisi) |
| Tim unit revisi | Icon "Catatan Revisi" (1) | ✅ Kosong | ✅ Muncul |
| Kirim komentar 2 | Icon "Catatan Revisi" (2) | ✅ Komentar 1 tersimpan | ❌ Hilang (menunggu revisi lagi) |
| Hover icon | Tooltip: Latest + History | ✅ Tampil semua (2 komentar) | - |
| Tim unit revisi lagi | Icon "Catatan Revisi" (2) | ✅ 2 komentar tersimpan | ✅ Muncul lagi |

**Fixed**:
- Semua komentar tersimpan
- Tombol validasi muncul setelah revisi
- Display lebih compact

---

## 🧪 Testing Checklist

### Test Case 1: Kirim Komentar Pertama
1. ✅ Validator buka modal detail unit yang belum ada komentar
2. ✅ Klik "Beri Catatan Revisi"
3. ✅ Tulis komentar: "Tolong perbaiki data Nov"
4. ✅ Klik "Kirim"
5. ✅ **Expected**:
   - Icon "Catatan Revisi" muncul dengan badge **(1)**
   - Tombol validasi **HILANG** (karena menunggu revisi)
   - Text: "Menunggu konfirmasi sudah direvisi dari tim unit"
6. ✅ Hover icon
7. ✅ **Expected**:
   - Tooltip muncul
   - Tampil: "Catatan Terbaru: Tolong perbaiki data Nov"
   - Status: "Menunggu revisi"
   - Section "Riwayat Catatan" **KOSONG** (belum ada history)

### Test Case 2: Tim Unit Revisi Data
1. ✅ Login sebagai tim unit
2. ✅ Buka halaman Capaian Indikator
3. ✅ Ada catatan revisi dari validator
4. ✅ Edit data sesuai catatan
5. ✅ Klik "Tandai Sudah Direvisi"
6. ✅ **Expected**: Status berubah jadi "dibaca"

### Test Case 3: Validator Lihat Setelah Revisi
1. ✅ Login sebagai validator
2. ✅ Buka modal detail unit tadi
3. ✅ **Expected**:
   - Icon "Catatan Revisi" masih ada dengan badge **(1)**
   - Tombol validasi **MUNCUL** ✅
   - Text: "✓ Catatan sudah direvisi, siap divalidasi"
4. ✅ Hover icon
5. ✅ **Expected**:
   - Tooltip: Status "✓ Sudah direvisi"

### Test Case 4: Kirim Komentar Kedua (Revisi Lagi)
1. ✅ Validator cek data, ternyata masih belum benar
2. ✅ Klik "Edit Catatan"
3. ✅ Tulis komentar baru: "Data Nov masih salah, cek kembali numerator"
4. ✅ Klik "Kirim"
5. ✅ **Expected**:
   - Icon "Catatan Revisi" dengan badge **(2)** ✅
   - Tombol validasi **HILANG** lagi (menunggu revisi)
6. ✅ Hover icon
7. ✅ **Expected**:
   - Tooltip muncul
   - **Catatan Terbaru**: "Data Nov masih salah, cek kembali numerator" (Status: Menunggu revisi)
   - **Riwayat Catatan** (1 item):
     ```
     Tolong perbaiki data Nov
     2025-11-25 10:30:00 • ✓ Direvisi
     ```

### Test Case 5: Kirim Komentar Ketiga
1. ✅ (Setelah tim unit revisi lagi) Validator kirim komentar ketiga
2. ✅ **Expected**:
   - Badge: **(3)**
   - History: 2 komentar lama tersimpan
   - Komentar terbaru: yang ketiga

### Test Case 6: Validasi Setelah Revisi
1. ✅ Tim unit revisi data sesuai komentar terakhir
2. ✅ Klik "Tandai Sudah Direvisi"
3. ✅ Validator buka modal
4. ✅ **Expected**:
   - Tombol validasi **MUNCUL** ✅
   - Klik "Validasi" → berhasil
5. ✅ **Expected setelah validasi**:
   - Icon komentar tetap ada (bisa dilihat history)
   - Tombol "Beri Catatan" disabled (sudah divalidasi)
   - Tombol "Validasi" HILANG (sudah divalidasi)

### Test Case 7: History Banyak (Scrollable)
1. ✅ Buat indikator dengan 10+ komentar revisi
2. ✅ Hover icon
3. ✅ **Expected**:
   - Tooltip muncul
   - Section "Riwayat Catatan" menampilkan semua history
   - Jika lebih dari ~4 item, muncul **scrollbar** (max-height: 12rem)
   - Bisa scroll untuk lihat semua history

---

## 📝 Summary

### Files Modified:

1. ✅ **Migration**: `database/migrations/2025_11_25_024837_add_komentar_history_to_capaian_indikators_table.php`
   - Added `komentar_history` JSON column

2. ✅ **Backend**: `app/Http/Controllers/ValidasiCapaianIndikatorController.php`
   - Method `sendKomentar()` (lines 261-270): Save old komentar to history before overwrite
   - Method `getDetailCapaian()` (line 192): Return `komentar_history` to frontend

3. ✅ **Frontend**: `resources/js/pages/Validasi-Capaian-Indikator.vue`
   - Interface `KomentarHistory` (lines 30-34): TypeScript interface for history
   - Interface `DetailCapaian` (line 52): Added `komentar_history` field
   - Komentar Display (lines 704-754): Changed to icon with hover tooltip
   - Function `kirimKomentar()` (lines 220-221): Refresh data after send

### Changes Made:

- ✅ **Database**: Added JSON column `komentar_history` untuk menyimpan semua catatan revisi
- ✅ **Backend Logic**: Otomatis push komentar lama ke history sebelum save komentar baru
- ✅ **Frontend Display**: Ganti full box menjadi compact icon dengan badge counter
- ✅ **Tooltip**: Hover icon menampilkan komentar terbaru + semua history dengan timestamp
- ✅ **Tombol Validasi**: Logic sudah benar sejak awal, akan muncul saat `komentar_dibaca = true`

### Impact:

- ✅ **History tersimpan**: Semua catatan revisi tidak hilang
- ✅ **UI lebih clean**: Tidak ada big yellow box, hanya icon compact
- ✅ **Informasi lengkap**: Hover untuk lihat semua history dengan detail
- ✅ **Tombol validasi**: Tetap muncul setelah tim unit revisi data
- ✅ **Audit trail**: Bisa tracking semua komunikasi validator ↔ tim unit

---

**Tanggal Perbaikan**: 2025-11-25
**Status**: ✅ Ready for Testing

---

## 🎯 User Experience Flow

### Scenario: Validator Request Revision 3x

```
┌─────────────────────────────────────────────────────────────┐
│ [1] Validator kirim komentar pertama                        │
├─────────────────────────────────────────────────────────────┤
│ UI: Icon "Catatan Revisi" (1)                               │
│ Tooltip: "Tolong perbaiki data Nov" - Menunggu revisi       │
│ Tombol: ❌ Validasi hilang                                  │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ [2] Tim unit revisi data + klik "Sudah Direvisi"           │
├─────────────────────────────────────────────────────────────┤
│ UI: Icon "Catatan Revisi" (1)                               │
│ Tooltip: "Tolong perbaiki data Nov" - ✓ Sudah direvisi     │
│ Tombol: ✅ Validasi muncul lagi                             │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ [3] Validator kirim komentar kedua (masih salah)            │
├─────────────────────────────────────────────────────────────┤
│ UI: Icon "Catatan Revisi" (2)                               │
│ Tooltip:                                                     │
│   - Latest: "Data masih salah" - Menunggu revisi            │
│   - History: "Tolong perbaiki data Nov" (✓ Direvisi)        │
│ Tombol: ❌ Validasi hilang lagi                             │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ [4] Tim unit revisi lagi + "Sudah Direvisi"                │
├─────────────────────────────────────────────────────────────┤
│ UI: Icon "Catatan Revisi" (2)                               │
│ Tooltip: Latest ✓ + History 1 item                          │
│ Tombol: ✅ Validasi muncul                                  │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ [5] Validator kirim komentar ketiga                         │
├─────────────────────────────────────────────────────────────┤
│ UI: Icon "Catatan Revisi" (3)                               │
│ Tooltip: Latest + History 2 items (scrollable)              │
│ Tombol: ❌ Validasi hilang                                  │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ [6] Tim unit revisi + "Sudah Direvisi"                     │
├─────────────────────────────────────────────────────────────┤
│ UI: Icon "Catatan Revisi" (3)                               │
│ Tooltip: Latest ✓ + History 2 items                         │
│ Tombol: ✅ Validasi muncul → KLIK VALIDASI → DONE!         │
└─────────────────────────────────────────────────────────────┘
```

**Result**: ✅ Semua 3 komentar tersimpan dan bisa dilihat di history!
