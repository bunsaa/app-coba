<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import {
  Eye,
  CheckCircle,
  Download,
  MessageSquare,
  AlertCircle,
  CheckCheck,
  CalendarDays,
  Info,
  Send,
  Trash2,
  Sparkles
} from 'lucide-vue-next';

interface UnitValidasi {
  unit_id: number;
  unit_kode: string;
  unit_nama: string;
  indikator_count: number;
  approved_count: number;
  validated_count: number;
  has_tim_unit: boolean;
  tim_units: string[];
}

interface KomentarHistory {
  komentar: string;
  tanggal: string;
  dibaca: boolean;
}

interface DetailCapaian {
  id: number;
  capaian_id: number | null;
  indikator: string;
  standar: string;
  satuan: 'persen' | 'rata-rata' | 'permil';
  satuan_waktu: string | null;
  tim_unit: string | null;
  numerator: number | null;
  denominator: number | null;
  hasil: number | null;
  validated: boolean;
  lampiran: {
    file_name: string;
    file_url: string;
    download_url: string;
  } | null;
  komentar: string;
  analisis: string;
  rtl: string;
  rekomendasi: string;
  komentar_dibaca: boolean;
  komentar_history: KomentarHistory[];
}

interface MonthOption {
  value: number;
  label: string;
  year: number;
}

interface Props {
  dataValidasi: UnitValidasi[];
  bulanDipilih: number;
  tahunDipilih: number;
  namaBulanDipilih: string;
  bulanSekarang: number;
  tahunSekarang: number;
  isBulanBerjalan: boolean;
  validasiTerbuka: boolean;
  tanggalBatas: string;
  monthOptions: MonthOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Validasi Capaian Indikator', href: '/validasi-capaian-indikator' },
];

// State
const showDetailModal = ref(false);
const showKomentarModal = ref(false);
const selectedUnit = ref<UnitValidasi | null>(null);
const selectedTimUnitFilter = ref<string>(''); // Filter tim unit di modal
const filterValidasiStatus = ref<'' | 'validated' | 'not_validated'>(''); // Filter status validasi
const detailCapaian = ref<DetailCapaian[]>([]);
const allDetailCapaian = ref<DetailCapaian[]>([]); // Store all data
const loading = ref(false);
const selectedIndikator = ref<DetailCapaian | null>(null);
const komentarText = ref('');
const selectedMonth = ref(props.bulanDipilih);
const selectedYear = ref(props.tahunDipilih);

// Rekomendasi: generate template berdasarkan hasil vs standar
function parseStandar(standar: string): { op: string; val: number } | null {
  const m = standar.match(/(≥|≤|>=|<=|>|<|=)\s*(\d+(?:[.,]\d+)?)/);
  if (m) return { op: m[1], val: parseFloat(m[2].replace(',', '.')) };
  // Fallback: standar hanya angka tanpa operator → default ke ≥
  const n = standar.match(/(\d+(?:[.,]\d+)?)/);
  if (n) return { op: '≥', val: parseFloat(n[1].replace(',', '.')) };
  return null;
}

function meetStandar(hasil: number, standar: string): boolean {
  const p = parseStandar(standar);
  if (!p) return true;
  switch (p.op) {
    case '≥': case '>=': return hasil >= p.val;
    case '≤': case '<=': return hasil <= p.val;
    case '>': return hasil > p.val;
    case '<': return hasil < p.val;
    case '=': return Math.abs(hasil - p.val) < 0.01;
  }
  return true;
}

function buildRekomendasi(item: DetailCapaian): string {
  if (item.hasil === null) return '';

  // Gunakan nilai asli untuk perbandingan, tampilan dikap 100% untuk persen
  const terpenuhi = meetStandar(item.hasil, item.standar);
  const hasilDisplay = item.satuan === 'persen'
    ? Math.min(Math.ceil(item.hasil), 100)
    : Math.ceil(item.hasil);
  const suffix = item.satuan === 'persen' ? '%'
    : item.satuan === 'permil' ? '‰'
    : (item.satuan_waktu ? ` ${item.satuan_waktu}` : '');
  const capaianStr = `${hasilDisplay}${suffix}`;

  // Hitung selisih untuk teks rekomendasi yang lebih informatif
  const parsed = parseStandar(item.standar);
  const selisihTeks = parsed && item.satuan === 'persen'
    ? ` (selisih ${Math.abs(Math.ceil(item.hasil) - parsed.val)}% dari standar)`
    : '';

  if (!terpenuhi) {
    return `Capaian indikator "${item.indikator}" bulan ini sebesar ${capaianStr}${selisihTeks} belum memenuhi standar ${item.standar} yang ditetapkan. ` +
      `Perlu dilakukan evaluasi menyeluruh terhadap proses pelayanan untuk mengidentifikasi faktor penghambat. ` +
      `Unit disarankan menyusun rencana tindak lanjut konkret dan melakukan pemantauan lebih intensif agar capaian mencapai standar ${item.standar} sesuai Pergub No. 20 Tahun 2016 dan Permenkes No. 30 Tahun 2022. ` +
      `Laporkan perkembangan secara berkala kepada pimpinan unit untuk mendukung perbaikan yang berkesinambungan.`;
  } else {
    return `Capaian indikator "${item.indikator}" bulan ini sebesar ${capaianStr} telah memenuhi standar ${item.standar} yang ditetapkan sesuai Pergub No. 20 Tahun 2016 dan Permenkes No. 30 Tahun 2022. ` +
      `Pertahankan kinerja yang baik ini dengan menjaga konsistensi proses pelayanan. ` +
      `Lakukan inovasi dan perbaikan berkelanjutan untuk meningkatkan kualitas pelayanan melebihi standar minimum yang ada. ` +
      `Dokumentasikan praktik terbaik sebagai referensi pengembangan mutu layanan di unit lain.`;
  }
}

// Pagination & Search
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

// Filtered & Paginated Data
const filteredData = computed(() => {
  if (!searchQuery.value) return props.dataValidasi;
  
  const query = searchQuery.value.toLowerCase();
  return props.dataValidasi.filter(unit => 
    unit.unit_nama.toLowerCase().includes(query)
  );
});

const totalPages = computed(() => Math.ceil(filteredData.value.length / itemsPerPage));

const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return filteredData.value.slice(start, end);
});

function goToPage(page: number) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
}

// Reset page when search changes
watch(searchQuery, () => {
  currentPage.value = 1;
});

// Filter detail capaian by tim unit
watch(selectedTimUnitFilter, (newVal) => {
  if (newVal) {
    detailCapaian.value = allDetailCapaian.value.filter(d => d.tim_unit === newVal);
  } else {
    // Jika unit punya tim dan filter dikosongkan, sembunyikan indikator lagi
    detailCapaian.value = selectedUnit.value?.has_tim_unit ? [] : allDetailCapaian.value;
  }
});

// Indikator yang ditampilkan setelah filter status validasi
const displayedCapaian = computed(() => {
  if (!filterValidasiStatus.value) return detailCapaian.value;
  if (filterValidasiStatus.value === 'validated') return detailCapaian.value.filter(d => d.validated);
  return detailCapaian.value.filter(d => !d.validated);
});

// Computed
const totalUnit = computed(() => props.dataValidasi.length);
const totalIndikator = computed(() => 
  props.dataValidasi.reduce((sum, unit) => sum + unit.indikator_count, 0)
);
const totalValidated = computed(() => 
  props.dataValidasi.reduce((sum, unit) => sum + unit.validated_count, 0)
);

const statusMessage = computed(() => {
  if (!props.isBulanBerjalan) {
    return 'Mode Lihat History';
  }
  return props.validasiTerbuka ? 'Validasi Terbuka' : 'Periode Validasi Ditutup';
});

const statusColor = computed(() => {
  if (!props.isBulanBerjalan) return 'text-blue-600';
  return props.validasiTerbuka ? 'text-green-600' : 'text-red-600';
});

// Functions
function changeBulan() {
  const selected = props.monthOptions.find(m => m.value === selectedMonth.value);
  if (selected) {
    router.get('/validasi-capaian-indikator', {
      bulan: selectedMonth.value,
      tahun: selected.year,
    }, {
      preserveState: false,
      preserveScroll: false,
    });
  }
}

async function lihatCapaian(unit: UnitValidasi) {
  selectedUnit.value = unit;
  selectedTimUnitFilter.value = ''; // Reset filter
  filterValidasiStatus.value = '';
  loading.value = true;
  showDetailModal.value = true;

  try {
    const response = await axios.post('/validasi-capaian-indikator/get-detail', {
      kode_unit: unit.unit_kode,
      tim_unit: null, // Get all tim units
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
    });
    
    const data = response.data.map((item: DetailCapaian) => ({
      ...item,
      rekomendasi: item.rekomendasi || buildRekomendasi(item),
    }));
    allDetailCapaian.value = data;
    // Jika unit punya tim, tunggu user pilih tim dulu sebelum tampilkan indikator
    detailCapaian.value = unit.has_tim_unit ? [] : data;
  } catch (error) {
    console.error('Error loading detail:', error);
    alert('Gagal memuat detail capaian');
  } finally {
    loading.value = false;
  }
}

function openKomentarModal(indikator: DetailCapaian) {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa menambahkan komentar pada bulan berjalan');
    return;
  }
  
  selectedIndikator.value = indikator;
  komentarText.value = indikator.komentar || '';
  showKomentarModal.value = true;
}

async function kirimKomentar() {
  if (!selectedIndikator.value || !komentarText.value.trim()) {
    alert('Komentar tidak boleh kosong');
    return;
  }

  try {
    await axios.post('/validasi-capaian-indikator/send-komentar', {
      indikator_id: selectedIndikator.value.id,
      kode_unit: selectedUnit.value?.unit_kode,
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
      komentar: komentarText.value,
    });

    // Refresh data dari server untuk mendapatkan komentar_history yang ter-update
    await lihatCapaian(selectedUnit.value!);

    alert('Komentar berhasil dikirim');
    showKomentarModal.value = false;
    komentarText.value = '';
  } catch (error) {
    console.error('Error sending comment:', error);
    alert('Gagal mengirim komentar');
  }
}

async function clearKomentar(indikator: DetailCapaian) {
  if (!confirm('Hapus komentar dan aktifkan kembali tombol validasi?')) return;

  try {
    await axios.post('/validasi-capaian-indikator/clear-komentar', {
      indikator_id: indikator.id,
      kode_unit: selectedUnit.value?.unit_kode,
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
    });

    // Update local data
    const index = detailCapaian.value.findIndex(d => d.id === indikator.id);
    if (index !== -1) {
      detailCapaian.value[index].komentar = '';
    }

    // Update allDetailCapaian too
    const allIndex = allDetailCapaian.value.findIndex(d => d.id === indikator.id);
    if (allIndex !== -1) {
      allDetailCapaian.value[allIndex].komentar = '';
    }

    alert('Komentar berhasil dihapus');
  } catch (error) {
    console.error('Error clearing comment:', error);
    alert('Gagal menghapus komentar');
  }
}

async function saveAnalisisAdmin(indikator: DetailCapaian) {
  try {
    await axios.post('/validasi-capaian-indikator/save-analisis', {
      indikator_id: indikator.id,
      kode_unit: selectedUnit.value?.unit_kode,
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
      analisis: indikator.analisis,
      rtl: indikator.rtl,
    });

    console.log('Analisis/RTL berhasil disimpan');
  } catch (error) {
    console.error('Error saving analisis:', error);
    alert('Gagal menyimpan analisis/RTL');
  }
}

async function validasiSingle(indikator: DetailCapaian) {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa melakukan validasi pada bulan berjalan');
    return;
  }

  if (!props.validasiTerbuka) {
    alert('Periode validasi sudah ditutup');
    return;
  }

  if (indikator.numerator === null || indikator.denominator === null) {
    alert('Data numerator dan denominator harus terisi terlebih dahulu');
    return;
  }

  if (!confirm(`Validasi indikator: ${indikator.indikator}?`)) return;

  try {
    await axios.post('/validasi-capaian-indikator/validate-single', {
      indikator_id: indikator.id,
      kode_unit: selectedUnit.value?.unit_kode,
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
    });

    // Update local data
    const index = detailCapaian.value.findIndex(d => d.id === indikator.id);
    if (index !== -1) {
      detailCapaian.value[index].validated = true;
    }
    
    // Update allDetailCapaian too
    const allIndex = allDetailCapaian.value.findIndex(d => d.id === indikator.id);
    if (allIndex !== -1) {
      allDetailCapaian.value[allIndex].validated = true;
    }

    alert('Indikator berhasil divalidasi');
  } catch (error) {
    console.error('Error validating:', error);
    alert('Gagal melakukan validasi');
  }
}

async function validasiUnit() {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa melakukan validasi pada bulan berjalan');
    return;
  }

  if (!props.validasiTerbuka) {
    alert('Periode validasi sudah ditutup');
    return;
  }

  if (!selectedUnit.value) return;

  const confirmMsg = selectedTimUnitFilter.value 
    ? `Validasi semua indikator di ${selectedUnit.value.unit_nama} - ${selectedTimUnitFilter.value}?`
    : `Validasi semua indikator di ${selectedUnit.value.unit_nama}?`;

  if (!confirm(confirmMsg)) return;

  try {
    const response = await axios.post('/validasi-capaian-indikator/validate-unit', {
      kode_unit: selectedUnit.value.unit_kode,
      tim_unit: selectedTimUnitFilter.value || null,
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
    });

    // Update local data
    detailCapaian.value = detailCapaian.value.map(d => ({
      ...d,
      validated: true
    }));
    
    allDetailCapaian.value = allDetailCapaian.value.map(d => ({
      ...d,
      validated: true
    }));

    alert(response.data.message);
  } catch (error) {
    console.error('Error validating unit:', error);
    alert('Gagal melakukan validasi');
  }
}

async function validasiSemuaUnit() {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa melakukan validasi pada bulan berjalan');
    return;
  }

  if (!props.validasiTerbuka) {
    alert('Periode validasi sudah ditutup');
    return;
  }

  if (!confirm('Validasi SEMUA indikator yang sudah di-approve di SEMUA unit?')) return;

  try {
    const response = await axios.post('/validasi-capaian-indikator/validate-all', {
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
    });

    alert(response.data.message);
    window.location.reload();
  } catch (error) {
    console.error('Error validating all:', error);
    alert('Gagal melakukan validasi');
  }
}

async function rejectSemuaUnit() {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa melakukan reject pada bulan berjalan');
    return;
  }

  if (!props.validasiTerbuka) {
    alert('Periode validasi sudah ditutup');
    return;
  }

  if (!confirm('Reject SEMUA indikator yang sudah di-approve di SEMUA unit? Indikator akan kembali ke status belum approve.')) return;

  try {
    const response = await axios.post('/validasi-capaian-indikator/reject-all', {
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
    });

    alert(response.data.message);
    window.location.reload();
  } catch (error) {
    console.error('Error rejecting all:', error);
    alert('Gagal melakukan reject');
  }
}


function formatHasil(item: DetailCapaian, value: number | null): string {
  if (value === null) return '-';

  const satuan = item.satuan || 'persen';

  if (satuan === 'rata-rata') {
    // Format: angka + satuan waktu (jika ada)
    const suffix = item.satuan_waktu ? ` ${item.satuan_waktu}` : '';
    return `${Math.ceil(value)}${suffix}`;
  } else if (satuan === 'persen') {
    return `${Math.ceil(value)}%`;
  } else if (satuan === 'permil') {
    return `${Math.ceil(value)}‰`;
  }

  return `${Math.ceil(value)}%`; // default
}

function getDisplayHasil(item: DetailCapaian, value: number | null): string {
  if (value === null) return '-';

  const satuan = item.satuan || 'persen';

  // Hanya untuk persen yang perlu cap di 100%
  if (satuan === 'persen' && value > 100) {
    return '100%';
  }

  return formatHasil(item, value);
}

function shouldShowOriginalValue(item: DetailCapaian, value: number | null): boolean {
  if (value === null) return false;
  const satuan = item.satuan || 'persen';
  // Hanya tampilkan nilai asli jika persen dan > 100%
  return satuan === 'persen' && value > 100;
}

function closeDetailModal() {
  showDetailModal.value = false;
  selectedUnit.value = null;
  selectedTimUnitFilter.value = '';
  filterValidasiStatus.value = '';
  detailCapaian.value = [];
  allDetailCapaian.value = [];
}
</script>

<template>
  <Head title="Validasi Capaian Indikator" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-3 p-4 xl:p-6">
      <div class="flex flex-1 min-h-0 flex-col rounded-xl border border-l-4 border-sidebar-border/70 bg-white p-5 shadow-md dark:border-sidebar-border xl:p-6">
        <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 text-center mb-4">
          VALIDASI CAPAIAN INDIKATOR
        </h3>

        <!-- Compact Header Row -->
        <div class="mb-4 grid gap-3 md:grid-cols-3">
          <!-- Pilih Bulan Validasi -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
              <CalendarDays :size="14" class="inline mr-1" />
              Pilih Bulan Validasi
            </label>
            <select 
              v-model="selectedMonth"
              @change="changeBulan"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            >
              <option v-for="option in monthOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <!-- Status Validasi -->
          <div class="rounded-lg border-l-4 border-purple-500 bg-purple-50 px-3 py-2">
            <p class="text-xs text-gray-600 mb-1">Status Validasi</p>
            <p class="text-sm font-semibold" :class="statusColor">
              {{ statusMessage }}
            </p>
            <p v-if="isBulanBerjalan" class="text-xs text-gray-500 mt-0.5">Batas: {{ tanggalBatas }}</p>
          </div>

          <!-- Progress Validasi -->
          <div class="rounded-lg border-l-4 border-orange-500 bg-orange-50 px-3 py-2">
            <p class="text-xs text-gray-600 mb-1">Progress Validasi</p>
            <p class="text-sm font-semibold text-orange-700">
              {{ totalValidated }} / {{ totalIndikator }}
            </p>
            <div class="mt-1 h-1.5 rounded-full bg-gray-200">
              <div 
                class="h-1.5 rounded-full bg-orange-500 transition-all"
                :style="{ width: totalIndikator > 0 ? `${(totalValidated / totalIndikator) * 100}%` : '0%' }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Warning jika bukan bulan berjalan -->
        <div v-if="!isBulanBerjalan" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 flex items-start gap-2">
          <Info :size="16" class="text-blue-600 flex-shrink-0 mt-0.5" />
          <div>
            <p class="text-xs font-semibold text-blue-800">Mode Lihat History</p>
            <p class="text-xs text-blue-600 mt-0.5">
              Anda sedang melihat data bulan {{ namaBulanDipilih }} {{ tahunDipilih }}. 
              Validasi hanya bisa dilakukan pada bulan berjalan.
            </p>
          </div>
        </div>

        <!-- Search Bar -->
        <div class="mb-3 flex items-center gap-3">
          <div class="flex-1 relative">
            <input 
              v-model="searchQuery"
              type="text"
              placeholder="Cari Bagian/Unit..."
              class="w-full rounded-lg border border-gray-300 px-4 py-2 pl-10 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            />
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          
          <div class="flex items-center gap-2">
            
            <button
              @click="validasiSemuaUnit"
              :disabled="!validasiTerbuka || !isBulanBerjalan"
              class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white shadow-md transition-colors whitespace-nowrap"
              :class="(validasiTerbuka && isBulanBerjalan) ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'"
            >
              <CheckCheck :size="16" />
              Validasi All
            </button>
            <!-- <button
              @click="rejectSemuaUnit"
              :disabled="!validasiTerbuka || !isBulanBerjalan"
              class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white shadow-md transition-colors whitespace-nowrap"
              :class="(validasiTerbuka && isBulanBerjalan) ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-400 cursor-not-allowed'"
            >
              <AlertCircle :size="16" />
              Reject
            </button> -->
          </div>
        </div>

        <!-- Table with Scroll (NO TIM UNIT COLUMN) -->
        <div class="flex-1 min-h-0 overflow-auto rounded-lg border border-gray-200" style="min-height: 300px;">
          <table class="w-full text-sm border-collapse">
            <thead class="sticky top-0 z-10 bg-gray-50">
              <tr>
                <th class="border px-4 py-3 text-left font-semibold text-gray-700 bg-gray-50">No</th>
                <th class="border px-4 py-3 text-left font-semibold text-gray-700 bg-gray-50">Unit/Bagian</th>
                <th class="border px-4 py-3 text-center font-semibold text-gray-700 bg-gray-50">Jumlah Indikator</th>
                <th class="border px-4 py-3 text-center font-semibold text-gray-700 bg-gray-50">Sudah Approve</th>
                <th class="border px-4 py-3 text-center font-semibold text-gray-700 bg-gray-50">Tervalidasi</th>
                <th class="border px-4 py-3 text-center font-semibold text-gray-700 bg-gray-50">Belum Divalidasi</th>
                <th class="border px-4 py-3 text-center font-semibold text-gray-700 bg-gray-50">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(unit, index) in paginatedData" 
                :key="index"
                class="hover:bg-gray-50 transition-colors"
              >
                <td class="border px-4 py-3 text-center">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                <td class="border px-4 py-3">
                  <div>
                    <p class="font-medium">{{ unit.unit_nama }}</p>
                    <p v-if="unit.has_tim_unit" class="text-xs text-gray-500 mt-1">
                      {{ unit.tim_units.length }} Tim Unit
                    </p>
                  </div>
                </td>
                <td class="border px-4 py-3 text-center">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-semibold text-xs">
                    {{ unit.indikator_count }}
                  </span>
                </td>
                <td class="border px-4 py-3 text-center">
                  <span
                    class="inline-flex items-center justify-center w-8 h-8 rounded-full font-semibold text-xs"
                    :class="unit.approved_count > 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'"
                  >
                    {{ unit.approved_count }}
                  </span>
                </td>
                <td class="border px-4 py-3 text-center">
                  <span
                    class="inline-flex items-center justify-center w-8 h-8 rounded-full font-semibold text-xs"
                    :class="unit.validated_count === unit.approved_count && unit.approved_count > 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'"
                  >
                    {{ unit.validated_count }}
                  </span>
                </td>
                <td class="border px-4 py-3 text-center">
                  <span
                    class="inline-flex items-center justify-center w-8 h-8 rounded-full font-semibold text-xs"
                    :class="(unit.approved_count - unit.validated_count) > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400'"
                  >
                    {{ unit.approved_count - unit.validated_count }}
                  </span>
                </td>
                <td class="border px-4 py-3 text-center">
                  <button
                    @click="lihatCapaian(unit)"
                    :disabled="unit.approved_count === 0"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white transition-colors"
                    :class="unit.approved_count > 0 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed'"
                  >
                    <Eye :size="14" />
                    {{ isBulanBerjalan ? 'Lihat & Validasi' : 'Lihat Detail' }}
                  </button>
                </td>
              </tr>
              
              <!-- Empty State for Search -->
              <tr v-if="paginatedData.length === 0">
                <td colspan="7" class="border px-4 py-8 text-center text-gray-400">
                  <p class="text-sm">Tidak ada data yang sesuai dengan pencarian "{{ searchQuery }}"</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between">
          <p class="text-sm text-gray-600">
            Menampilkan {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredData.length) }} dari {{ filteredData.length }} data
          </p>
          
          <div class="flex items-center gap-2">
            <button 
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-3 py-1 rounded-lg border text-sm transition-colors"
              :class="currentPage === 1 ? 'border-gray-200 text-gray-400 cursor-not-allowed' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            >
              ‹ Prev
            </button>
            
            <div class="flex gap-1">
              <button 
                v-for="page in totalPages" 
                :key="page"
                @click="goToPage(page)"
                class="px-3 py-1 rounded-lg text-sm transition-colors"
                :class="page === currentPage ? 'bg-indigo-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
              >
                {{ page }}
              </button>
            </div>
            
            <button 
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="px-3 py-1 rounded-lg border text-sm transition-colors"
              :class="currentPage === totalPages ? 'border-gray-200 text-gray-400 cursor-not-allowed' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            >
              Next ›
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail Capaian -->
    <Teleport to="body">
      <div v-if="showDetailModal" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="closeDetailModal">
        <div class="w-full max-w-7xl rounded-xl bg-white shadow-2xl max-h-[90vh] overflow-hidden flex flex-col">
          <!-- Header -->
          <div class="border-b border-gray-200 px-6 py-4 bg-gray-50 flex-shrink-0">
            <div class="flex items-center justify-between mb-3">
              <div>
                <h4 class="text-lg font-semibold text-gray-800">Detail Capaian Indikator</h4>
                <p class="text-sm text-gray-600 mt-1">
                  {{ selectedUnit?.unit_nama }}
                  <span class="text-gray-400 ml-2">• {{ namaBulanDipilih }} {{ tahunDipilih }}</span>
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  v-if="isBulanBerjalan && (!selectedUnit?.has_tim_unit || selectedTimUnitFilter)"
                  @click="validasiUnit"
                  :disabled="!validasiTerbuka"
                  class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white transition-colors"
                  :class="validasiTerbuka ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'"
                >
                  <CheckCircle :size="16" />
                  Validasi All
                </button>
                <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
              </div>
            </div>

            <!-- Filter row: Tim Unit + Status Validasi -->
            <div class="flex items-center flex-wrap gap-4">
              <!-- Filter Tim Unit (Jika ada) - wajib dipilih dulu -->
              <div v-if="selectedUnit?.has_tim_unit" class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">
                  Tim Unit:
                  <span v-if="!selectedTimUnitFilter" class="ml-1 text-xs text-red-500">* wajib dipilih</span>
                </label>
                <select
                  v-model="selectedTimUnitFilter"
                  class="rounded-lg border px-3 py-1.5 text-sm focus:ring-2"
                  :class="selectedTimUnitFilter ? 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-200' : 'border-orange-400 focus:border-orange-500 focus:ring-orange-200'"
                >
                  <option value="" disabled>-- Pilih Tim Unit --</option>
                  <option v-for="tim in selectedUnit?.tim_units" :key="tim" :value="tim">
                    {{ tim }}
                  </option>
                </select>
              </div>

              <!-- Filter Status Validasi -->
              <div v-if="allDetailCapaian.length > 0 && (!selectedUnit?.has_tim_unit || selectedTimUnitFilter)" class="flex items-center gap-2">
                <label class="text-xs font-medium text-gray-600">Status:</label>
                <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs">
                  <button
                    @click="filterValidasiStatus = ''"
                    class="px-3 py-1.5 transition-colors"
                    :class="filterValidasiStatus === '' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                  >Semua</button>
                  <button
                    @click="filterValidasiStatus = 'not_validated'"
                    class="border-l border-gray-200 px-3 py-1.5 transition-colors"
                    :class="filterValidasiStatus === 'not_validated' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                  >Belum Divalidasi</button>
                  <button
                    @click="filterValidasiStatus = 'validated'"
                    class="border-l border-gray-200 px-3 py-1.5 transition-colors"
                    :class="filterValidasiStatus === 'validated' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                  >Tervalidasi</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Content with Scroll -->
          <div class="overflow-y-auto p-6 flex-1">
            <div v-if="loading" class="text-center py-12">
              <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-indigo-600 border-r-transparent"></div>
              <p class="mt-4 text-gray-600">Memuat data...</p>
            </div>

            <div v-else-if="selectedUnit?.has_tim_unit && !selectedTimUnitFilter" class="text-center py-14 text-gray-500">
              <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-orange-100">
                <AlertCircle :size="28" class="text-orange-500" />
              </div>
              <p class="font-semibold text-gray-700">Pilih Tim Unit Terlebih Dahulu</p>
              <p class="mt-1 text-sm text-gray-400">Silakan pilih tim unit di bagian atas untuk melihat indikator capaian</p>
            </div>

            <div v-else-if="detailCapaian.length === 0" class="text-center py-12 text-gray-400">
              <AlertCircle :size="48" class="mx-auto mb-3 opacity-50" />
              <p>Tidak ada data capaian</p>
            </div>

            <div v-else-if="displayedCapaian.length === 0" class="text-center py-12 text-gray-400">
              <AlertCircle :size="48" class="mx-auto mb-3 opacity-50" />
              <p v-if="filterValidasiStatus === 'validated'">Belum ada indikator yang tervalidasi</p>
              <p v-else>Belum ada indikator yang belum divalidasi</p>
            </div>

            <div v-else class="space-y-4">
              <div
                v-for="(item, index) in displayedCapaian"
                :key="item.id"
                class="rounded-lg border p-4 transition-shadow hover:shadow-md"
                :class="item.validated ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'"
              >
                <div class="flex items-start justify-between mb-3">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                        {{ index + 1 }}
                      </span>
                      <h5 class="font-semibold text-gray-800">{{ item.indikator }}</h5>
                      <span v-if="item.tim_unit" class="ml-2 inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                        {{ item.tim_unit }}
                      </span>
                    </div>
                    <p class="text-sm text-gray-600">Standar: {{ item.standar }}</p>
                  </div>
                  
                  <div v-if="item.validated" class="flex items-center gap-2 text-green-600 text-sm font-medium">
                    <CheckCircle :size="16" />
                    Tervalidasi
                  </div>
                </div>

                <div class="grid grid-cols-4 gap-4 mb-3">
                  <div class="rounded-lg bg-blue-50 p-3">
                    <p class="text-xs text-gray-600 mb-1">Numerator</p>
                    <p class="text-lg font-bold text-blue-700">{{ item.numerator ?? '-' }}</p>
                  </div>
                  <div class="rounded-lg bg-purple-50 p-3">
                    <p class="text-xs text-gray-600 mb-1">Denominator</p>
                    <p class="text-lg font-bold text-purple-700">{{ item.denominator ?? '-' }}</p>
                  </div>
                  <div class="rounded-lg bg-green-50 p-3">
                    <p class="text-xs text-gray-600 mb-1">Hasil</p>
                    <p class="text-lg font-bold text-green-700">{{ getDisplayHasil(item, item.hasil) }}</p>
                    <p v-if="shouldShowOriginalValue(item, item.hasil)" class="text-[10px] text-gray-400 mt-0.5">
                      ({{ formatHasil(item, item.hasil) }})
                    </p>
                  </div>
                  <div class="rounded-lg bg-gray-50 p-3">
                    <p class="text-xs text-gray-600 mb-1">Lampiran</p>
                    <a
                      v-if="item.lampiran"
                      :href="item.lampiran.download_url"
                      class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                    >
                      <Download :size="14" />
                      Download
                    </a>
                    <p v-else class="text-sm text-gray-400">-</p>
                  </div>
                </div>

                <!-- Komentar Display - Icon dengan Tooltip -->
                <div v-if="item.komentar" class="mb-3 flex items-center gap-2">
                  <!-- Icon Catatan dengan Tooltip -->
                  <div class="relative group">
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-yellow-50 border border-yellow-200 cursor-pointer">
                      <MessageSquare :size="16" class="text-yellow-700" />
                      <span class="text-xs font-medium text-yellow-700">Catatan Revisi</span>
                      <span v-if="item.komentar_history && item.komentar_history.length > 0"
                            class="text-[10px] bg-yellow-600 text-white px-1.5 py-0.5 rounded-full">
                        {{ item.komentar_history.length + 1 }}
                      </span>
                    </div>

                    <!-- Tooltip dengan History -->
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

                <!-- Analisis, RTL & Rekomendasi Section -->
                <div class="mt-4 grid grid-cols-3 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Analisis</label>
                    <textarea
                      v-model="item.analisis"
                      @blur="saveAnalisisAdmin(item)"
                      rows="4"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                      placeholder="Tulis analisis..."
                    ></textarea>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">RTL (Rencana Tindak Lanjut)</label>
                    <textarea
                      v-model="item.rtl"
                      @blur="saveAnalisisAdmin(item)"
                      rows="4"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                      placeholder="Tulis RTL..."
                    ></textarea>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                      <Sparkles :size="12" class="inline mr-1 text-purple-500" />
                      Rekomendasi
                    </label>
                    <div
                      v-if="item.rekomendasi"
                      class="rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-xs text-gray-700 leading-relaxed"
                      style="min-height: 88px; white-space: pre-wrap;"
                    >{{ item.rekomendasi }}</div>
                    <div
                      v-else
                      class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-400 italic flex items-center justify-center"
                      style="min-height: 88px;"
                    >
                      Data numerator/denominator belum terisi
                    </div>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 mt-4">
                  <button
                    @click="openKomentarModal(item)"
                    :disabled="item.validated || !isBulanBerjalan"
                    class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                    :class="(item.validated || !isBulanBerjalan) ? 'border-gray-300 text-gray-400 cursor-not-allowed' : 'border-orange-300 text-orange-700 hover:bg-orange-50'"
                  >
                    <MessageSquare :size="14" />
                    {{ item.komentar && item.komentar_dibaca ? 'Tambah Catatan' : (item.komentar ? 'Edit Catatan' : 'Beri Catatan Revisi') }}
                  </button>

                  <button
  v-if="!item.validated && isBulanBerjalan && (!item.komentar || item.komentar_dibaca)"
  @click="validasiSingle(item)"
  :disabled="!validasiTerbuka"
  class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white transition-colors"
  :class="validasiTerbuka ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'"
>
  <CheckCircle :size="14" />
  Validasi
</button>

                  <div v-if="item.komentar && !item.komentar_dibaca && !item.validated" class="text-xs text-orange-600 italic">
  * Menunggu konfirmasi sudah direvisi dari tim unit
</div>
<div v-if="item.komentar && item.komentar_dibaca && !item.validated" class="text-xs text-green-600 italic flex items-center gap-1">
  <CheckCircle :size="12" />
  Catatan sudah direvisi, siap divalidasi
</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal Komentar -->
    <Teleport to="body">
      <div v-if="showKomentarModal" class="fixed inset-0 z-[99999] grid place-items-center bg-black/40 p-4" @click.self="showKomentarModal = false">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
          <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <MessageSquare :size="20" class="text-orange-600" />
            Catatan Revisi
          </h4>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Tulis catatan untuk tim unit
            </label>
            <textarea 
              v-model="komentarText"
              rows="4"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-200"
              placeholder="Contoh: Data numerator bulan ini perlu dikoreksi, silakan periksa kembali..."
            ></textarea>
          </div>

          <div class="flex justify-end gap-2">
            <button 
              @click="showKomentarModal = false"
              class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
              Batal
            </button>
            <button 
              @click="kirimKomentar"
              :disabled="!komentarText.trim()"
              class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white transition-colors"
              :class="komentarText.trim() ? 'bg-orange-600 hover:bg-orange-700' : 'bg-gray-400 cursor-not-allowed'"
            >
              <Send :size="14" />
              Kirim Catatan
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
th, td { 
  border: 1px solid #e5e7eb; 
}

thead th {
  position: sticky;
  top: 0;
  z-index: -10;
  box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
}

/* Custom scrollbar */
.overflow-auto::-webkit-scrollbar,
.overflow-y-auto::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.overflow-auto::-webkit-scrollbar-track,
.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

.overflow-auto::-webkit-scrollbar-thumb,
.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.overflow-auto::-webkit-scrollbar-thumb:hover,
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>