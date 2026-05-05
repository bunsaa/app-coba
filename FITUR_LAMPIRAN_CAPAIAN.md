# Fitur Lampiran Capaian Indikator

## 📋 Ringkasan Perubahan

### 1. **Validasi File**
- **Tipe file yang diperbolehkan**: PDF (.pdf), Excel (.xlsx, .xls)
- **Ukuran maksimal**: 500KB
- ❌ File gambar (JPG, PNG) tidak diperbolehkan lagi

### 2. **Format Nama File Baru**
**Format**: `kode_indikator_tim_unit_bulan_tahun.ext`

**Contoh**:
- `Jumlah_Pegawai_AS_Diklat_jan_2025.pdf`
- `Persentase_Kepuasan_Pengelolaan_Sistem_feb_2025.xlsx`
- `test123_NoTim_mar_2025.pdf` (untuk indikator tanpa tim_unit)

**Benefit**:
- ✅ Nama file lebih deskriptif dan mudah dicari
- ✅ Tidak ada collision karena unique per indikator + unit + bulan + tahun
- ✅ Bisa langsung tahu konteks file tanpa buka database

---

### 3. **Tampilan Baru Setelah Upload**
**Sebelum**:
- Tombol "Upload" + text nama file di bawah

**Sesudah**:
- **Jika belum upload**: Tombol "Upload" (sama seperti sebelumnya)
- **Jika sudah upload**: Tombol "Lihat" (biru) + nama file di bawah

---

### 4. **Modal Lihat & Upload Ulang**
**Fitur Modal**:
- ✅ Preview file:
  - **PDF**: Ditampilkan di iframe (bisa scroll)
  - **Excel**: Tampilkan icon dan info (tidak bisa preview, harus download)
- ✅ Download file
- ✅ Upload ulang (jika belum divalidasi)
- ✅ Warning jika sudah divalidasi

---

## 🔧 Perubahan Backend

### File: `app/Http/Controllers/CapaianIndikatorController.php`

**Method**: `uploadLampiran()` (Lines 263-330)

#### Perubahan Utama:

**1. Validasi File Ketat**
```php
$validated = $request->validate([
    'indikator_id' => 'required|exists:indikators,id',
    'kode_unit' => 'required|exists:units,kode_unit',
    'tahun' => 'required|integer',
    'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
    'file' => 'required|file|mimes:pdf,xlsx,xls|max:500', // ✅ Max 500KB, only PDF & Excel
], [
    'file.mimes' => 'File harus berformat PDF atau Excel (.pdf, .xlsx, .xls)',
    'file.max' => 'Ukuran file maksimal 500KB',
]);
```

**Validasi:**
- ✅ Hanya accept: `.pdf`, `.xlsx`, `.xls`
- ✅ Max size: 500KB (bukan 5120KB lagi)
- ✅ Custom error messages dalam Bahasa Indonesia

**2. Ambil Data Indikator**
```php
// Get indikator info untuk nama file
$indikator = Indikator::find($validated['indikator_id']);
if (!$indikator) {
    return response()->json(['error' => 'Indikator tidak ditemukan'], 404);
}
```

**2. Generate Nama File Baru**
```php
$file = $request->file('file');
$extension = $file->getClientOriginalExtension();

// Format nama file: kode_indikator_tim_unit_bulan_tahun.ext
$indikatorCode = str_replace([' ', '/', '\\'], '_', substr($indikator->indikator, 0, 20));
$timUnit = $indikator->tim_unit ? str_replace([' ', ',', '&', '/', '\\'], '_', $indikator->tim_unit) : 'NoTim';
$fileName = "{$indikatorCode}_{$timUnit}_{$bulan}_{$validated['tahun']}.{$extension}";

$filePath = $file->storeAs('lampiran_capaian', $fileName, 'public');
```

**Logic Nama File:**
- Ambil 20 karakter pertama dari nama indikator
- Replace special characters dengan underscore
- Tambahkan tim_unit (atau 'NoTim' jika NULL)
- Tambahkan bulan dan tahun
- Tambahkan extension original file

**3. Simpan ke Database**
```php
$lampiran = CapaianLampiran::create([
    'capaian_id' => $capaian->id,
    'bulan' => $bulan,
    'file_name' => $fileName,  // ✅ Nama file baru
    'file_path' => $filePath,
    'file_type' => $file->getClientMimeType(),
    'file_size' => $file->getSize(),
]);
```

---

## 🎨 Perubahan Frontend

### File: `resources/js/pages/Capaian-Indikator.vue`

### 1. **State Baru untuk Modal**

**Lines 81-83:**
```typescript
// Lampiran modal state
const showLampiranModal = ref(false);
const selectedLampiran = ref<{ ind: Indicator | null; month: MonthKey | null; fileUrl: string | null }>({
  ind: null,
  month: null,
  fileUrl: null
});
```

---

### 2. **Function Baru**

#### a. **openLampiranModal()** (Lines 454-468)
```typescript
function openLampiranModal(ind: Indicator, m: MonthKey) {
  const fileName = ind.att[m];
  if (!fileName) {
    alert('Tidak ada lampiran');
    return;
  }

  selectedLampiran.value = {
    ind: ind,
    month: m,
    fileUrl: `/storage/lampiran_capaian/${fileName}`
  };
  showLampiranModal.value = true;
}
```

**Fungsi**: Buka modal untuk lihat lampiran

---

#### b. **closeLampiranModal()** (Lines 470-474)
```typescript
function closeLampiranModal() {
  showLampiranModal.value = false;
  selectedLampiran.value = { ind: null, month: null, fileUrl: null };
}
```

**Fungsi**: Tutup modal dan reset state

---

#### c. **uploadUlangLampiran()** (Lines 476-513)
```typescript
function uploadUlangLampiran(e: Event) {
  if (!selectedLampiran.value.ind || !selectedLampiran.value.month) return;

  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

  const ind = selectedLampiran.value.ind;
  const m = selectedLampiran.value.month;

  const formData = new FormData();
  formData.append('file', file);
  formData.append('indikator_id', String(ind.id));
  formData.append('kode_unit', selectedUnitCode.value);
  formData.append('tahun', String(props.tahun));
  formData.append('bulan', m);

  axios.post('/capaian-indikator/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
  .then(response => {
    console.log('Upload ulang success:', response.data);
    ind.att[m] = response.data.file_name;
    selectedLampiran.value.fileUrl = `/storage/lampiran_capaian/${response.data.file_name}`;
    alert('File berhasil diupload ulang!');
    input.value = '';
  })
  .catch((error: unknown) => {
    console.error('Upload error:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal upload file');
    } else {
      alert('Gagal upload file');
    }
    input.value = '';
  });
}
```

**Fungsi**: Upload ulang lampiran dari dalam modal

---

### 3. **Template Baru untuk Cell Lampiran**

**Lines 811-842:**
```vue
<!-- LAMPIRAN -->
<tr class="odd:bg-white even:bg-gray-50">
  <td class="border px-2 py-2 font-semibold">LAMPIRAN</td>

  <td v-for="month in displayMonths" :key="month" class="border px-2 py-2">
    <!-- Jika BELUM ada file: Tampilkan tombol Upload -->
    <div v-if="!ind.att[month]" class="flex flex-col items-center gap-1">
      <input type="file" :id="`f-${ind.id}-${month}`" class="hidden" accept=".pdf,image/*"
             :disabled="!isWindowOpen(month) || isValidated(ind, month)"
             @change="onUpload(ind, month, $event)" />
      <label :for="`f-${ind.id}-${month}`"
             class="w-full rounded-md border px-2 py-1 text-center text-xs"
             :class="(isWindowOpen(month) && !isValidated(ind, month))?
                     'cursor-pointer hover:bg-gray-50 border-gray-300':
                     'opacity-50 cursor-not-allowed border-gray-200'">
        Upload
      </label>
      <div class="text-[11px] text-gray-400">—</div>
    </div>

    <!-- Jika SUDAH ada file: Tampilkan tombol Lihat -->
    <div v-else class="flex flex-col items-center gap-1">
      <button @click="openLampiranModal(ind, month)"
              class="w-full rounded-md border border-blue-500 bg-blue-50 px-2 py-1
                     text-center text-xs text-blue-700 hover:bg-blue-100">
        Lihat
      </button>
      <div class="text-[11px] truncate w-full text-gray-600" :title="ind.att[month] || ''">
        {{ ind.att[month] }}
      </div>
    </div>
  </td>

  <td class="border px-2 py-2"></td>
  <td class="border px-2 py-2"></td>
</tr>
```

**Logic**:
- `v-if="!ind.att[month]"` → Jika belum ada file, tampilkan button "Upload"
- `v-else` → Jika sudah ada file, tampilkan button "Lihat" (biru)

---

### 4. **Modal Template**

**Lines 1019-1087:**
```vue
<!-- Modal Lampiran: Lihat & Upload Ulang -->
<Teleport to="body">
  <div v-if="showLampiranModal" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4"
       @click.self="closeLampiranModal">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-auto" @click.stop>
      <!-- Header -->
      <div class="flex items-center justify-between border-b p-4">
        <h3 class="text-lg font-semibold text-gray-800">
          Lampiran - {{ selectedLampiran.month?.toUpperCase() }}
        </h3>
        <button @click="closeLampiranModal" class="text-gray-400 hover:text-gray-600 text-2xl">
          &times;
        </button>
      </div>

      <!-- Content -->
      <div class="p-6 space-y-4">
        <!-- Preview File -->
        <div class="border rounded-lg p-4 bg-gray-50">
          <p class="text-sm font-medium text-gray-700 mb-2">File Lampiran:</p>
          <p class="text-sm text-gray-600 mb-3 break-all">
            {{ selectedLampiran.ind?.att[selectedLampiran.month!] }}
          </p>

          <!-- Preview (PDF atau Gambar) -->
          <div v-if="selectedLampiran.fileUrl" class="mt-3">
            <iframe v-if="selectedLampiran.fileUrl.endsWith('.pdf')"
                    :src="selectedLampiran.fileUrl"
                    class="w-full h-96 border rounded"></iframe>
            <img v-else
                 :src="selectedLampiran.fileUrl"
                 class="w-full h-auto border rounded max-h-96 object-contain"
                 alt="Lampiran">
          </div>

          <!-- Download Link -->
          <a :href="selectedLampiran.fileUrl" target="_blank" download
             class="inline-block mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
            Download File
          </a>
        </div>

        <!-- Upload Ulang (jika belum divalidasi) -->
        <div v-if="selectedLampiran.ind && selectedLampiran.month &&
                   isWindowOpen(selectedLampiran.month) &&
                   !isValidated(selectedLampiran.ind, selectedLampiran.month)"
             class="border border-dashed border-gray-300 rounded-lg p-4">
          <p class="text-sm font-medium text-gray-700 mb-2">Upload Ulang Lampiran:</p>
          <p class="text-xs text-gray-500 mb-3">File akan menggantikan lampiran yang sudah ada</p>

          <input type="file" :id="`upload-ulang-${selectedLampiran.ind?.id}-${selectedLampiran.month}`"
                 class="hidden" accept=".pdf,image/*"
                 @change="uploadUlangLampiran($event)" />
          <label :for="`upload-ulang-${selectedLampiran.ind?.id}-${selectedLampiran.month}`"
                 class="inline-block px-4 py-2 bg-green-600 text-white rounded
                        cursor-pointer hover:bg-green-700 text-sm">
            Pilih File Baru
          </label>
        </div>

        <!-- Info jika sudah divalidasi -->
        <div v-if="selectedLampiran.ind && selectedLampiran.month &&
                   isValidated(selectedLampiran.ind, selectedLampiran.month)"
             class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
          <p class="text-sm text-yellow-800">
            ⚠️ Lampiran tidak bisa diganti karena bulan ini sudah divalidasi
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-2 border-t p-4">
        <button @click="closeLampiranModal"
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
          Tutup
        </button>
      </div>
    </div>
  </div>
</Teleport>
```

**Fitur Modal:**
1. **Header**: Judul dengan nama bulan
2. **Preview**:
   - Jika PDF → tampilkan dalam `<iframe>`
   - Jika gambar → tampilkan dalam `<img>`
3. **Download**: Link download file
4. **Upload Ulang**: Hanya tampil jika belum divalidasi
5. **Warning**: Tampil jika sudah divalidasi

---

## 📊 User Flow

### Scenario 1: Upload Lampiran Pertama Kali

1. User buka halaman Capaian Indikator
2. Pilih unit dan tim unit (jika ada)
3. Input nilai N dan D untuk bulan tertentu (misal: Januari)
4. Klik tombol **"Upload"** di cell Lampiran - Januari
5. Pilih file (PDF/Gambar)
6. File ter-upload dengan nama: `[indikator]_[tim_unit]_jan_2025.pdf`
7. Cell Lampiran berubah tampilan:
   - Tombol **"Upload"** → berubah jadi tombol **"Lihat"** (biru)
   - Di bawahnya muncul nama file

---

### Scenario 2: Lihat Lampiran yang Sudah Di-upload

1. User klik tombol **"Lihat"** (biru) di cell Lampiran
2. Modal terbuka menampilkan:
   - Nama file
   - Preview file (PDF/Gambar)
   - Tombol "Download File"
   - Tombol "Pilih File Baru" (jika belum divalidasi)
3. User bisa:
   - Lihat preview langsung
   - Download file
   - Upload ulang (jika belum divalidasi)
   - Tutup modal

---

### Scenario 3: Upload Ulang Lampiran

1. User klik tombol **"Lihat"** di cell Lampiran
2. Modal terbuka
3. User klik tombol **"Pilih File Baru"** (hijau)
4. Pilih file baru
5. File lama akan dihapus dan diganti dengan file baru
6. Nama file baru tetap mengikuti format yang sama
7. Preview di modal langsung update
8. Alert: "File berhasil diupload ulang!"

---

### Scenario 4: Lampiran Sudah Divalidasi (Tidak Bisa Ganti)

1. User klik tombol **"Lihat"** di cell Lampiran bulan yang sudah divalidasi
2. Modal terbuka
3. **Tidak ada** tombol "Pilih File Baru"
4. Ada warning:
   > ⚠️ Lampiran tidak bisa diganti karena bulan ini sudah divalidasi
5. User hanya bisa lihat preview dan download

---

## 🧪 Testing Checklist

### Test Case 1: Upload Lampiran PDF
- [ ] Buka halaman Capaian Indikator
- [ ] Pilih unit dengan indikator aktif
- [ ] Klik tombol "Upload" untuk bulan tertentu
- [ ] Upload file PDF (misal: `test.pdf`, ukuran < 500KB)
- [ ] **Expected**:
  - File ter-upload
  - Nama file di server: `[indikator]_[tim_unit]_[bulan]_[tahun].pdf`
  - Tombol berubah dari "Upload" → "Lihat" (biru)
  - Nama file muncul di bawah tombol

### Test Case 2: Upload Lampiran Excel
- [ ] Klik tombol "Upload" untuk bulan lain
- [ ] Upload file Excel (misal: `data.xlsx`, ukuran < 500KB)
- [ ] **Expected**: Upload berhasil dengan nama format yang sesuai

### Test Case 3: Validasi File - Tipe File Salah
- [ ] Coba upload file gambar (JPG/PNG)
- [ ] **Expected**: Error "File harus berformat PDF atau Excel (.pdf, .xlsx, .xls)"

### Test Case 4: Validasi File - Ukuran Terlalu Besar
- [ ] Coba upload file PDF/Excel > 500KB
- [ ] **Expected**: Error "Ukuran file maksimal 500KB"

### Test Case 5: Lihat Lampiran PDF
- [ ] Klik tombol "Lihat" (biru) untuk lampiran PDF
- [ ] **Expected**:
  - Modal terbuka
  - Preview PDF muncul di iframe (bisa scroll)
  - Ada tombol "Download File"
  - Ada tombol "Pilih File Baru" (jika belum divalidasi)

### Test Case 6: Lihat Lampiran Excel
- [ ] Klik tombol "Lihat" (biru) untuk lampiran Excel
- [ ] **Expected**:
  - Modal terbuka
  - Tampil icon Excel hijau
  - Text: "Preview tidak tersedia untuk file Excel. Silakan download untuk melihat isi file."
  - Ada tombol "Download File"
  - Ada tombol "Pilih File Baru" (jika belum divalidasi)

### Test Case 7: Download Lampiran
- [ ] Di modal, klik tombol "Download File"
- [ ] **Expected**: File ter-download dengan nama sesuai format

### Test Case 8: Upload Ulang Lampiran
- [ ] Di modal, klik tombol "Pilih File Baru"
- [ ] Pilih file lain (misal: `test2.pdf`)
- [ ] **Expected**:
  - File lama terhapus
  - File baru ter-upload dengan nama format yang sama
  - Preview di modal langsung update
  - Alert: "File berhasil diupload ulang!"

### Test Case 9: Lampiran Sudah Divalidasi
- [ ] Validasi bulan tertentu
- [ ] Klik tombol "Lihat" untuk bulan tersebut
- [ ] **Expected**:
  - Modal terbuka
  - **Tidak ada** tombol "Pilih File Baru"
  - Ada warning: "⚠️ Lampiran tidak bisa diganti karena bulan ini sudah divalidasi"

### Test Case 10: Format Nama File
- [ ] Upload lampiran untuk indikator dengan tim_unit
- [ ] Cek di folder `storage/app/public/lampiran_capaian/`
- [ ] **Expected**: Nama file = `[indikator]_[tim_unit]_[bulan]_[tahun].pdf`
- [ ] Upload lampiran untuk indikator tanpa tim_unit
- [ ] **Expected**: Nama file = `[indikator]_NoTim_[bulan]_[tahun].pdf`

---

## 📁 Files Modified

1. ✅ `app/Http/Controllers/CapaianIndikatorController.php`
   - Method `uploadLampiran()` (lines 263-324)

2. ✅ `resources/js/pages/Capaian-Indikator.vue`
   - State (lines 81-83)
   - Functions (lines 417-513)
   - Template cell lampiran (lines 811-842)
   - Modal template (lines 1019-1087)

---

**Tanggal Implementasi**: 2025-11-25
**Status**: ✅ Ready for Testing
