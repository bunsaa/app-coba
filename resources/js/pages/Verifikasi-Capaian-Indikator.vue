<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted } from 'vue';
import axios from 'axios';
import { registerAutoSave } from '@/composables/useAutoSaveRegistry';
import AnalisisHistoryPopup, { type AnalisisHistoryEntry } from '@/components/AnalisisHistoryPopup.vue';
import RekomendasiAI from '@/components/RekomendasiAI.vue';
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
  Sparkles,
  Clock
} from 'lucide-vue-next';

interface UnitVerifikasi {
  unit_id: number;
  unit_kode: string;
  unit_nama: string;
  indikator_count: number;
  approved_count: number;
  validated_count: number;
  has_tim_unit: boolean;
  tim_units: string[];
  penilaian_pj: {
    id: number;
    nilai: string | null;
    catatan: string | null;
    aspek: Array<{ item: string; nilai: 'ya' | 'tidak' | null }> | null;
    temuan_kesesuaian: string | null;
    temuan_kelengkapan: string | null;
    temuan_ketepatan: string | null;
    temuan_validitas: string | null;
    rekomendasi: Array<{ rencana: string; penanggung_jawab: string; target_waktu: string; status: string }> | null;
  } | null;
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
  analisis_rtl_history?: AnalisisHistoryEntry[];
}

interface MonthOption {
  value: number;
  label: string;
  year: number;
}

interface Props {
  dataVerifikasi: UnitVerifikasi[];
  bulanDipilih: number;
  tahunDipilih: number;
  namaBulanDipilih: string;
  bulanSekarang: number;
  tahunSekarang: number;
  isBulanBerjalan: boolean;
  verifikasiTerbuka: boolean;
  tanggalBatas: string;
  monthOptions: MonthOption[];
  isAdmin: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Verifikasi Capaian Indikator', href: '/verifikasi-capaian-indikator' },
];

// State
const showDetailModal = ref(false);
const showKomentarModal = ref(false);
const selectedUnit = ref<UnitVerifikasi | null>(null);
const selectedTimUnitFilter = ref<string>(''); // Filter tim unit di modal
const filterVerifikasiStatus = ref<'' | 'validated' | 'not_validated'>(''); // Filter status verifikasi
const detailCapaian = ref<DetailCapaian[]>([]);
const allDetailCapaian = ref<DetailCapaian[]>([]); // Store all data
const loading = ref(false);
const selectedIndikator = ref<DetailCapaian | null>(null);
const komentarText = ref('');
const selectedMonth = ref(props.bulanDipilih);

// === PENILAIAN PJ DATA ===
const nilaiColorMap: Record<string, string> = {
  'Baik':   'bg-blue-100 text-blue-700 border-blue-300',
  'Cukup':  'bg-yellow-100 text-yellow-700 border-yellow-300',
  'Kurang': 'bg-red-100 text-red-700 border-red-300',
};

// 12 aspek tetap
const ASPEK_ITEMS = [
  'Petugas memahami definisi operasional indikator',
  'Terdapat dokumen definisi operasional di unit',
  'Data dikumpulkan sesuai metode yang ditetapkan',
  'Sumber data jelas (rekam medis/register/dll)',
  'Numerator sesuai dengan kriteria indikator',
  'Denominator sesuai dengan kriteria indikator',
  'Data lengkap (tidak ada yang kosong)',
  'Data akurat (sesuai dengan sumber data)',
  'Pengumpulan data dilakukan tepat waktu',
  'Data sudah direkap dan dihitung dengan benar',
  'Terdapat bukti verifikasi oleh Kepala Unit',
  'Terdapat analisis sederhana (trend/capaian)',
];

type AspekItem = { item: string; nilai: 'ya' | 'tidak' | null };
type RekomendasiRow = { rencana: string; penanggung_jawab: string; target_waktu: string; status: string };

const showPenilaianModal = ref(false);
const penilaianUnit = ref<UnitVerifikasi | null>(null);
const penilaianTab = ref<'aspek' | 'temuan' | 'rekomendasi'>('aspek');
const penilaianLoading = ref(false);
const penilaianCatatan = ref('');

// Tab 1: Aspek penilaian
const penilaianAspek = ref<AspekItem[]>([]);

// Tab 2: Temuan dan analisa
const temuanKesesuaian = ref<string | null>(null);
const temuanKelengkapan = ref<string | null>(null);
const temuanKetepatan  = ref<string | null>(null);
const temuanValiditas  = ref<string | null>(null);

// Tab 3: Rekomendasi perbaikan
const rekomendasiRows = ref<RekomendasiRow[]>([]);

// Auto-calculate nilai dari aspek
const nilaiHitung = computed<string | null>(() => {
  const total = penilaianAspek.value.length;
  if (total === 0) return null;
  const ya = penilaianAspek.value.filter(a => a.nilai === 'ya').length;
  const pct = (ya / total) * 100;
  if (pct >= 80) return 'Baik';
  if (pct >= 60) return 'Cukup';
  return 'Kurang';
});

const nilaiPct = computed<number>(() => {
  const total = penilaianAspek.value.length;
  if (total === 0) return 0;
  const ya = penilaianAspek.value.filter(a => a.nilai === 'ya').length;
  return Math.round((ya / total) * 100);
});

function openPenilaianModal(unit: UnitVerifikasi) {
  penilaianUnit.value = unit;
  penilaianTab.value = 'aspek';
  penilaianCatatan.value = unit.penilaian_pj?.catatan ?? '';

  // Load aspek dari data tersimpan (atau init kosong)
  const savedAspek = unit.penilaian_pj?.aspek;
  if (savedAspek && savedAspek.length > 0) {
    penilaianAspek.value = ASPEK_ITEMS.map((item, i) => ({
      item,
      nilai: (savedAspek[i]?.nilai ?? null) as 'ya' | 'tidak' | null,
    }));
  } else {
    penilaianAspek.value = ASPEK_ITEMS.map(item => ({ item, nilai: null }));
  }

  temuanKesesuaian.value = unit.penilaian_pj?.temuan_kesesuaian ?? null;
  temuanKelengkapan.value = unit.penilaian_pj?.temuan_kelengkapan ?? null;
  temuanKetepatan.value  = unit.penilaian_pj?.temuan_ketepatan ?? null;
  temuanValiditas.value  = unit.penilaian_pj?.temuan_validitas ?? null;

  rekomendasiRows.value = unit.penilaian_pj?.rekomendasi
    ? [...unit.penilaian_pj.rekomendasi.map(r => ({ ...r }))]
    : [];

  showPenilaianModal.value = true;
}

function addRekomendasiRow() {
  rekomendasiRows.value.push({ rencana: '', penanggung_jawab: '', target_waktu: '', status: 'Belum' });
}

function removeRekomendasiRow(i: number) {
  rekomendasiRows.value.splice(i, 1);
}

async function savePenilaian() {
  if (!penilaianUnit.value) return;
  penilaianLoading.value = true;
  try {
    const resp = await axios.post('/penilaian-pj-data/store', {
      kode_unit:            penilaianUnit.value.unit_kode,
      tahun:                props.tahunDipilih,
      bulan:                props.bulanDipilih,
      catatan:              penilaianCatatan.value || null,
      aspek:                penilaianAspek.value,
      temuan_kesesuaian:    temuanKesesuaian.value,
      temuan_kelengkapan:   temuanKelengkapan.value,
      temuan_ketepatan:     temuanKetepatan.value,
      temuan_validitas:     temuanValiditas.value,
      rekomendasi:          rekomendasiRows.value.filter(r => r.rencana.trim()),
    });
    // Update local data
    const idx = props.dataVerifikasi.findIndex(u => u.unit_kode === penilaianUnit.value!.unit_kode);
    if (idx !== -1) {
      props.dataVerifikasi[idx].penilaian_pj = resp.data.penilaian;
    }
    showPenilaianModal.value = false;
  } catch (e) {
    alert('Gagal menyimpan penilaian');
  } finally {
    penilaianLoading.value = false;
  }
}

// Auto-save: track analisis/RTL yang belum tersimpan
const analysisDirtySet = ref(new Set<number>())
// History popup state
const activeHistoryItemId = ref<number | null>(null)
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

// Deteksi indikator sentinel event: standar literalnya "0" atau "= 0"
function isSentinelIndicator(standar: string): boolean {
  const trimmed = standar.trim();
  if (trimmed === '0') return true;
  const p = parseStandar(trimmed);
  return p !== null && p.op === '=' && p.val === 0;
}

function buildRekomendasi(item: DetailCapaian): string {
  if (item.hasil === null) return '';

  const hasilDisplay = item.satuan === 'persen'
    ? Math.min(Math.ceil(item.hasil), 100)
    : Math.ceil(item.hasil);
  const suffix = item.satuan === 'persen' ? '%'
    : item.satuan === 'permil' ? '‰'
    : (item.satuan_waktu ? ` ${item.satuan_waktu}` : '');
  const capaianStr = `${hasilDisplay}${suffix}`;

  let teks = '';

  // Logika khusus untuk indikator sentinel event (standar = 0)
  if (isSentinelIndicator(item.standar)) {
    if (item.hasil === 0) {
      teks = `Capaian indikator "${item.indikator}" bulan ini sebesar 0 kejadian, telah memenuhi standar yang ditetapkan. ` +
        `Pertahankan kondisi ini dengan menjaga kepatuhan terhadap prosedur dan protokol yang berlaku. ` +
        `Lakukan evaluasi rutin dan edukasi berkala kepada staf untuk mencegah terjadinya kejadian serupa.`;
    } else if (item.hasil <= 10) {
      teks = `Capaian indikator "${item.indikator}" bulan ini sebesar ${capaianStr} tergolong kategori risiko rendah (0,01–10%). ` +
        `Meskipun masih dalam kategori rendah, setiap kejadian tetap harus diinvestigasi. ` +
        `Lakukan analisis akar masalah (root cause analysis), dokumentasikan temuan, dan susun langkah pencegahan agar tidak terjadi pengulangan. ` +
        `Laporkan hasil investigasi kepada pimpinan unit secara berkala.`;
    } else if (item.hasil <= 50) {
      teks = `Capaian indikator "${item.indikator}" bulan ini sebesar ${capaianStr} tergolong kategori risiko sedang (10–50%). ` +
        `Segera lakukan evaluasi mendalam terhadap prosedur pelayanan dan identifikasi faktor penyebab. ` +
        `Susun rencana tindak lanjut terstruktur, tingkatkan supervisi, dan lakukan pelatihan staf yang relevan. ` +
        `Pantau perkembangan secara intensif dan laporkan kepada pimpinan sebagai prioritas perbaikan mutu.`;
    } else {
      teks = `Capaian indikator "${item.indikator}" bulan ini sebesar ${capaianStr} tergolong kategori risiko tinggi (>50%). ` +
        `Kondisi ini memerlukan penanganan segera dan komprehensif. ` +
        `Lakukan investigasi menyeluruh, perbaikan sistem, dan terapkan langkah korektif secepat mungkin. ` +
        `Eskalasikan permasalahan kepada pimpinan unit sebagai prioritas utama dan laporkan rencana perbaikan secara tertulis sesuai ketentuan yang berlaku.`;
    }
  } else {
    // Logika umum untuk indikator non-sentinel
    const terpenuhi = meetStandar(item.hasil, item.standar);
    const parsed = parseStandar(item.standar);
    const selisihTeks = parsed && item.satuan === 'persen'
      ? ` (selisih ${Math.abs(Math.ceil(item.hasil) - parsed.val)}% dari standar)`
      : '';

    if (!terpenuhi) {
      teks = `Capaian indikator "${item.indikator}" bulan ini sebesar ${capaianStr}${selisihTeks} belum memenuhi standar ${item.standar} yang ditetapkan. ` +
        `Perlu dilakukan evaluasi menyeluruh terhadap proses pelayanan untuk mengidentifikasi faktor penghambat. ` +
        `Unit disarankan menyusun rencana tindak lanjut konkret dan melakukan pemantauan lebih intensif agar capaian mencapai standar ${item.standar} sesuai Pergub No. 20 Tahun 2016 dan Permenkes No. 30 Tahun 2022. ` +
        `Laporkan perkembangan secara berkala kepada pimpinan unit untuk mendukung perbaikan yang berkesinambungan.`;
    } else {
      teks = `Capaian indikator "${item.indikator}" bulan ini sebesar ${capaianStr} telah memenuhi standar ${item.standar} yang ditetapkan sesuai Pergub No. 20 Tahun 2016 dan Permenkes No. 30 Tahun 2022. ` +
        `Pertahankan kinerja yang baik ini dengan menjaga konsistensi proses pelayanan. ` +
        `Lakukan inovasi dan perbaikan berkelanjutan untuk meningkatkan kualitas pelayanan melebihi standar minimum yang ada. ` +
        `Dokumentasikan praktik terbaik sebagai referensi pengembangan mutu layanan di unit lain.`;
    }
  }

  if (!item.lampiran) {
    teks += ` Perhatian: Data lampiran pendukung untuk indikator ini belum dilaporkan. Harap segera mengunggah lampiran yang relevan sebagai bukti pelaporan.`;
  }

  return teks;
}

// Pagination & Search
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

// Filtered & Paginated Data
const filteredData = computed(() => {
  if (!searchQuery.value) return props.dataVerifikasi;
  
  const query = searchQuery.value.toLowerCase();
  return props.dataVerifikasi.filter(unit => 
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

// Indikator yang ditampilkan setelah filter status verifikasi
const displayedCapaian = computed(() => {
  if (!filterVerifikasiStatus.value) return detailCapaian.value;
  if (filterVerifikasiStatus.value === 'validated') return detailCapaian.value.filter(d => d.validated);
  return detailCapaian.value.filter(d => !d.validated);
});

// Computed
const totalUnit = computed(() => props.dataVerifikasi.length);
const totalIndikator = computed(() => 
  props.dataVerifikasi.reduce((sum, unit) => sum + unit.indikator_count, 0)
);
const totalValidated = computed(() => 
  props.dataVerifikasi.reduce((sum, unit) => sum + unit.validated_count, 0)
);

const statusMessage = computed(() => {
  if (!props.isBulanBerjalan) {
    return 'Mode Lihat History';
  }
  return props.verifikasiTerbuka ? 'Verifikasi Terbuka' : 'Periode Verifikasi Ditutup';
});

const statusColor = computed(() => {
  if (!props.isBulanBerjalan) return 'text-blue-600';
  return props.verifikasiTerbuka ? 'text-green-600' : 'text-red-600';
});

// Functions
function changeBulan() {
  const selected = props.monthOptions.find(m => m.value === selectedMonth.value);
  if (selected) {
    router.get('/verifikasi-capaian-indikator', {
      bulan: selectedMonth.value,
      tahun: selected.year,
    }, {
      preserveState: false,
      preserveScroll: false,
    });
  }
}

async function lihatCapaian(unit: UnitVerifikasi) {
  selectedUnit.value = unit;
  selectedTimUnitFilter.value = ''; // Reset filter
  filterVerifikasiStatus.value = '';
  loading.value = true;
  showDetailModal.value = true;

  try {
    const response = await axios.post('/verifikasi-capaian-indikator/get-detail', {
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
    await axios.post('/verifikasi-capaian-indikator/send-komentar', {
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
  if (!confirm('Hapus komentar dan aktifkan kembali tombol verifikasi?')) return;

  try {
    await axios.post('/verifikasi-capaian-indikator/clear-komentar', {
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
    const resp = await axios.post('/verifikasi-capaian-indikator/save-analisis', {
      indikator_id: indikator.id,
      kode_unit: selectedUnit.value?.unit_kode,
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
      analisis: indikator.analisis,
      rtl: indikator.rtl,
    });

    console.log('Analisis/RTL berhasil disimpan');
    analysisDirtySet.value.delete(indikator.id)
    if (resp.data.analisisHistory) {
      indikator.analisis_rtl_history = resp.data.analisisHistory;
    }
  } catch (error) {
    console.error('Error saving analisis:', error);
    alert('Gagal menyimpan analisis/RTL');
  }
}

async function verifikasiSingle(indikator: DetailCapaian) {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa melakukan verifikasi pada bulan berjalan');
    return;
  }

  if (!props.verifikasiTerbuka) {
    alert('Periode verifikasi sudah ditutup');
    return;
  }

  if (indikator.numerator === null || indikator.denominator === null) {
    alert('Data numerator dan denominator harus terisi terlebih dahulu');
    return;
  }

  if (!confirm(`Verifikasi indikator: ${indikator.indikator}?`)) return;

  try {
    await axios.post('/verifikasi-capaian-indikator/validate-single', {
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

    // Refresh validated_count di tabel utama
    router.reload({ only: ['dataVerifikasi'] });

    alert('Indikator berhasil diverifikasi');
  } catch (error) {
    console.error('Error validating:', error);
    alert('Gagal melakukan verifikasi');
  }
}

async function verifikasiUnit() {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa melakukan verifikasi pada bulan berjalan');
    return;
  }

  if (!props.verifikasiTerbuka) {
    alert('Periode verifikasi sudah ditutup');
    return;
  }

  if (!selectedUnit.value) return;

  const confirmMsg = selectedTimUnitFilter.value 
    ? `Verifikasi semua indikator di ${selectedUnit.value.unit_nama} - ${selectedTimUnitFilter.value}?`
    : `Verifikasi semua indikator di ${selectedUnit.value.unit_nama}?`;

  if (!confirm(confirmMsg)) return;

  try {
    const response = await axios.post('/verifikasi-capaian-indikator/validate-unit', {
      kode_unit: selectedUnit.value.unit_kode,
      tim_unit: selectedTimUnitFilter.value || null,
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
    });

    // Update local data
    detailCapaian.value = detailCapaian.value.map(d => ({ ...d, validated: true }));
    allDetailCapaian.value = allDetailCapaian.value.map(d => ({ ...d, validated: true }));

    // Refresh validated_count di tabel utama
    router.reload({ only: ['dataVerifikasi'] });

    alert(response.data.message);
  } catch (error) {
    console.error('Error validating unit:', error);
    alert('Gagal melakukan verifikasi');
  }
}

async function verifikasiSemuaUnit() {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa melakukan verifikasi pada bulan berjalan');
    return;
  }

  if (!props.verifikasiTerbuka) {
    alert('Periode verifikasi sudah ditutup');
    return;
  }

  if (!confirm('Verifikasi SEMUA indikator yang sudah di-approve di SEMUA unit?')) return;

  try {
    const response = await axios.post('/verifikasi-capaian-indikator/validate-all', {
      tahun: props.tahunDipilih,
      bulan: props.bulanDipilih,
    });

    alert(response.data.message);
    window.location.reload();
  } catch (error) {
    console.error('Error validating all:', error);
    alert('Gagal melakukan verifikasi');
  }
}

async function rejectSemuaUnit() {
  if (!props.isBulanBerjalan) {
    alert('Hanya bisa melakukan reject pada bulan berjalan');
    return;
  }

  if (!props.verifikasiTerbuka) {
    alert('Periode verifikasi sudah ditutup');
    return;
  }

  if (!confirm('Reject SEMUA indikator yang sudah di-approve di SEMUA unit? Indikator akan kembali ke status belum approve.')) return;

  try {
    const response = await axios.post('/verifikasi-capaian-indikator/reject-all', {
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
  filterVerifikasiStatus.value = '';
  detailCapaian.value = [];
  allDetailCapaian.value = [];
}

// ===== AUTO-SAVE SEBELUM AUTO-LOGOUT =====
const _unregisterAutoSave = registerAutoSave(async () => {
  // 1. Jika modal komentar terbuka dengan teks yang belum dikirim
  if (showKomentarModal.value && komentarText.value.trim() && selectedIndikator.value) {
    try {
      await axios.post('/verifikasi-capaian-indikator/send-komentar', {
        indikator_id: selectedIndikator.value.id,
        kode_unit: selectedUnit.value?.unit_kode,
        tahun: props.tahunDipilih,
        bulan: props.bulanDipilih,
        komentar: komentarText.value,
      })
    } catch (e) {
      console.error('Auto-save komentar gagal:', e)
    }
  }

  // 2. Simpan analisis/RTL yang masih dirty (belum tersimpan via @blur)
  for (const itemId of analysisDirtySet.value) {
    const item = detailCapaian.value.find(x => x.id === itemId)
    if (item) {
      try {
        await saveAnalisisAdmin(item)
      } catch (e) {
        console.error('Auto-save analisis gagal:', e)
      }
    }
  }
})

onUnmounted(_unregisterAutoSave)
</script>

<template>
  <Head title="Verifikasi Capaian Indikator" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-3 p-4 xl:p-6">
      <div class="flex flex-1 min-h-0 flex-col rounded-xl border border-l-4 border-sidebar-border/70 bg-white p-5 shadow-md dark:border-sidebar-border xl:p-6">
        <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 text-center mb-4">
          VERIFIKASI CAPAIAN INDIKATOR
        </h3>

        <!-- Compact Header Row -->
        <div class="mb-4 grid gap-3 md:grid-cols-3">
          <!-- Pilih Bulan Verifikasi -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
              <CalendarDays :size="14" class="inline mr-1" />
              Pilih Bulan Verifikasi
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

          <!-- Status Verifikasi -->
          <div class="rounded-lg border-l-4 border-purple-500 bg-purple-50 px-3 py-2">
            <p class="text-xs text-gray-600 mb-1">Status Verifikasi</p>
            <p class="text-sm font-semibold" :class="statusColor">
              {{ statusMessage }}
            </p>
            <p v-if="isBulanBerjalan" class="text-xs text-gray-500 mt-0.5">Batas: {{ tanggalBatas }}</p>
          </div>

          <!-- Progress Verifikasi -->
          <div class="rounded-lg border-l-4 border-orange-500 bg-orange-50 px-3 py-2">
            <p class="text-xs text-gray-600 mb-1">Progress Verifikasi</p>
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
              Verifikasi hanya bisa dilakukan pada bulan berjalan.
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
              @click="verifikasiSemuaUnit"
              :disabled="!verifikasiTerbuka || !isBulanBerjalan"
              class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white shadow-md transition-colors whitespace-nowrap"
              :class="(verifikasiTerbuka && isBulanBerjalan) ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'"
            >
              <CheckCheck :size="16" />
              Verifikasi All
            </button>
            <!-- <button
              @click="rejectSemuaUnit"
              :disabled="!verifikasiTerbuka || !isBulanBerjalan"
              class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white shadow-md transition-colors whitespace-nowrap"
              :class="(verifikasiTerbuka && isBulanBerjalan) ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-400 cursor-not-allowed'"
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
                <th class="border px-4 py-3 text-center font-semibold text-gray-700 bg-gray-50">Terverifikasi</th>
                <th class="border px-4 py-3 text-center font-semibold text-gray-700 bg-gray-50">Belum Diverifikasi</th>
                <th v-if="!isAdmin" class="border px-4 py-3 text-center font-semibold text-gray-700 bg-gray-50">Penilaian PJ Data</th>
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
                <!-- Penilaian PJ Data — hanya tampil untuk non-admin -->
                <td v-if="!isAdmin" class="border px-4 py-3 text-center">
                  <div class="flex flex-col items-center gap-1">
                    <span
                      v-if="unit.penilaian_pj"
                      class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold border"
                      :class="nilaiColorMap[unit.penilaian_pj.nilai ?? ''] ?? 'bg-gray-100 text-gray-600'"
                    >{{ unit.penilaian_pj.nilai }}</span>
                    <button
                      @click="openPenilaianModal(unit)"
                      class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs text-white transition-colors"
                      :class="unit.penilaian_pj ? 'bg-gray-500 hover:bg-gray-600' : 'bg-indigo-600 hover:bg-indigo-700'"
                    >
                      {{ unit.penilaian_pj ? 'Lihat / Edit' : 'Nilai' }}
                    </button>
                  </div>
                </td>
                <td class="border px-4 py-3 text-center">
                  <button
                    @click="lihatCapaian(unit)"
                    :disabled="unit.approved_count === 0"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white transition-colors"
                    :class="unit.approved_count > 0 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed'"
                  >
                    <Eye :size="14" />
                    {{ isBulanBerjalan ? 'Lihat & Verifikasi' : 'Lihat Detail' }}
                  </button>
                </td>
              </tr>
              
              <!-- Empty State for Search -->
              <tr v-if="paginatedData.length === 0">
                <td :colspan="isAdmin ? 7 : 8" class="border px-4 py-8 text-center text-gray-400">
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
                  @click="verifikasiUnit"
                  :disabled="!verifikasiTerbuka"
                  class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white transition-colors"
                  :class="verifikasiTerbuka ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'"
                >
                  <CheckCircle :size="16" />
                  Verifikasi All
                </button>
                <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
              </div>
            </div>

            <!-- Filter row: Tim Unit + Status Verifikasi -->
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

              <!-- Filter Status Verifikasi -->
              <div v-if="allDetailCapaian.length > 0 && (!selectedUnit?.has_tim_unit || selectedTimUnitFilter)" class="flex items-center gap-2">
                <label class="text-xs font-medium text-gray-600">Status:</label>
                <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs">
                  <button
                    @click="filterVerifikasiStatus = ''"
                    class="px-3 py-1.5 transition-colors"
                    :class="filterVerifikasiStatus === '' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                  >Semua</button>
                  <button
                    @click="filterVerifikasiStatus = 'not_validated'"
                    class="border-l border-gray-200 px-3 py-1.5 transition-colors"
                    :class="filterVerifikasiStatus === 'not_validated' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                  >Belum Diverifikasi</button>
                  <button
                    @click="filterVerifikasiStatus = 'validated'"
                    class="border-l border-gray-200 px-3 py-1.5 transition-colors"
                    :class="filterVerifikasiStatus === 'validated' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                  >Terverifikasi</button>
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
              <p v-if="filterVerifikasiStatus === 'validated'">Belum ada indikator yang terverifikasi</p>
              <p v-else>Belum ada indikator yang belum diverifikasi</p>
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
                    Terverifikasi
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
                    <div class="flex items-center justify-between mb-1">
                      <label class="block text-xs font-medium text-gray-700">Analisis</label>
                      <button
                        v-if="item.analisis_rtl_history?.length"
                        @click="activeHistoryItemId = item.id"
                        class="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 transition-colors"
                        title="Lihat riwayat perubahan"
                      >
                        <Clock :size="12" />
                        {{ item.analisis_rtl_history.length }}
                      </button>
                    </div>
                    <textarea
                      v-model="item.analisis"
                      @blur="saveAnalisisAdmin(item)"
                      @input="analysisDirtySet.add(item.id)"
                      rows="4"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                      placeholder="Tulis analisis..."
                    ></textarea>
                    <AnalisisHistoryPopup
                      v-if="activeHistoryItemId === item.id"
                      :history="item.analisis_rtl_history ?? []"
                      :bulan="String(props.bulanDipilih)"
                      @close="activeHistoryItemId = null"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">RTL (Rencana Tindak Lanjut)</label>
                    <textarea
                      v-model="item.rtl"
                      @blur="saveAnalisisAdmin(item)"
                      @input="analysisDirtySet.add(item.id)"
                      rows="4"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                      placeholder="Tulis RTL..."
                    ></textarea>
                  </div>
                  <div>
                    <RekomendasiAI
                      :indikator="item.indikator"
                      :standar="item.standar"
                      :satuan="item.satuan"
                      :n="item.numerator ?? undefined"
                      :d="item.denominator ?? undefined"
                      :bulan="props.namaBulanDipilih"
                      :unit="selectedUnit?.unit_nama"
                      :delay="index * 1500"
                      :validated="item.validated"
                      @apply="(a, r) => { item.analisis = a; item.rtl = r; saveAnalisisAdmin(item); }"
                    />
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
  @click="verifikasiSingle(item)"
  :disabled="!verifikasiTerbuka"
  class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-white transition-colors"
  :class="verifikasiTerbuka ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'"
>
  <CheckCircle :size="14" />
  Verifikasi
</button>

                  <div v-if="item.komentar && !item.komentar_dibaca && !item.validated" class="text-xs text-orange-600 italic">
  * Menunggu konfirmasi sudah direvisi dari tim unit
</div>
<div v-if="item.komentar && item.komentar_dibaca && !item.validated" class="text-xs text-green-600 italic flex items-center gap-1">
  <CheckCircle :size="12" />
  Catatan sudah direvisi, siap diverifikasi
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
    <!-- Modal Penilaian PJ Data — Tabbed (hanya untuk non-admin) -->
    <Teleport to="body">
      <div v-if="showPenilaianModal && !isAdmin" class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/50 p-3">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl flex flex-col max-h-[92vh]">

          <!-- Header -->
          <div class="flex items-center justify-between bg-gradient-to-r from-indigo-600 to-purple-600 rounded-t-2xl px-5 py-4 flex-shrink-0">
            <div>
              <h4 class="text-base font-bold text-white">Penilaian PJ Data</h4>
              <p class="text-xs text-indigo-200 mt-0.5">{{ penilaianUnit?.unit_nama }} &bull; {{ namaBulanDipilih }} {{ tahunDipilih }}</p>
            </div>
            <!-- Nilai badge (auto-hitung) -->
            <div class="flex items-center gap-3">
              <div v-if="nilaiHitung" class="text-center">
                <span
                  class="inline-block rounded-full px-3 py-1 text-xs font-bold border"
                  :class="nilaiColorMap[nilaiHitung] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
                >{{ nilaiHitung }}</span>
                <div class="text-[10px] text-indigo-200 mt-0.5">{{ nilaiPct }}% Ya</div>
              </div>
              <button @click="showPenilaianModal = false" class="rounded-lg p-1 text-white/70 hover:bg-white/20 hover:text-white transition-colors">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Tab bar -->
          <div class="flex border-b border-gray-200 flex-shrink-0 bg-gray-50">
            <button
              v-for="tab in [
                { key: 'aspek',       label: 'Aspek Penilaian', icon: '✓' },
                { key: 'temuan',      label: 'Temuan & Analisa', icon: '🔍' },
                { key: 'rekomendasi', label: 'Rekomendasi',      icon: '📋' },
              ]"
              :key="tab.key"
              @click="penilaianTab = tab.key as any"
              class="flex-1 py-2.5 text-xs font-semibold transition-all border-b-2"
              :class="penilaianTab === tab.key
                ? 'border-indigo-600 text-indigo-700 bg-white'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100'"
            >
              <span class="mr-1">{{ tab.icon }}</span>{{ tab.label }}
            </button>
          </div>

          <!-- Tab content (scrollable) -->
          <div class="flex-1 overflow-y-auto p-5">

            <!-- ===== TAB 1: ASPEK PENILAIAN ===== -->
            <div v-if="penilaianTab === 'aspek'">
              <p class="text-xs text-gray-500 mb-3 italic">Centang <strong>Ya</strong> jika terpenuhi, <strong>Tidak</strong> jika tidak. Nilai dihitung otomatis (≥80% = Baik, 60–79% = Cukup, &lt;60% = Kurang).</p>
              <div class="space-y-2">
                <div
                  v-for="(asp, i) in penilaianAspek"
                  :key="i"
                  class="flex items-center justify-between rounded-lg border px-3 py-2.5 text-sm transition-colors"
                  :class="asp.nilai === 'ya' ? 'border-green-200 bg-green-50' : asp.nilai === 'tidak' ? 'border-red-100 bg-red-50' : 'border-gray-200 bg-white'"
                >
                  <span class="flex-1 pr-3 text-gray-700">
                    <span class="font-medium text-gray-400 mr-1.5 text-xs">{{ i + 1 }}.</span>{{ asp.item }}
                  </span>
                  <div class="flex gap-2 flex-shrink-0">
                    <button
                      @click="penilaianAspek[i].nilai = 'ya'"
                      class="rounded-full px-3 py-1 text-xs font-semibold border transition-all"
                      :class="asp.nilai === 'ya' ? 'bg-green-500 text-white border-green-500 shadow-sm' : 'border-gray-300 text-gray-500 hover:border-green-400 hover:text-green-600'"
                    >Ya</button>
                    <button
                      @click="penilaianAspek[i].nilai = 'tidak'"
                      class="rounded-full px-3 py-1 text-xs font-semibold border transition-all"
                      :class="asp.nilai === 'tidak' ? 'bg-red-500 text-white border-red-500 shadow-sm' : 'border-gray-300 text-gray-500 hover:border-red-400 hover:text-red-600'"
                    >Tidak</button>
                  </div>
                </div>
              </div>
              <!-- Progress -->
              <div class="mt-4 rounded-lg bg-gray-50 border border-gray-200 p-3">
                <div class="flex items-center justify-between text-xs text-gray-600 mb-1.5">
                  <span>Progress pengisian</span>
                  <span>{{ penilaianAspek.filter(a => a.nilai !== null).length }} / {{ penilaianAspek.length }}</span>
                </div>
                <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="nilaiHitung === 'Baik' ? 'bg-blue-500' : nilaiHitung === 'Cukup' ? 'bg-yellow-500' : nilaiHitung ? 'bg-red-500' : 'bg-gray-300'"
                    :style="`width: ${nilaiPct}%`"
                  ></div>
                </div>
                <div class="mt-1 text-center text-xs font-semibold" :class="nilaiHitung === 'Baik' ? 'text-blue-600' : nilaiHitung === 'Cukup' ? 'text-yellow-600' : nilaiHitung ? 'text-red-600' : 'text-gray-400'">
                  {{ nilaiHitung ? `${nilaiPct}% → ${nilaiHitung}` : 'Belum ada penilaian' }}
                </div>
              </div>
            </div>

            <!-- ===== TAB 2: TEMUAN & ANALISA ===== -->
            <div v-if="penilaianTab === 'temuan'" class="space-y-4">
              <p class="text-xs text-gray-500 italic">Pilih kondisi yang sesuai berdasarkan hasil pemeriksaan data.</p>

              <div v-for="(row, ri) in [
                { label: 'Kesesuaian dengan sumber data', model: 'temuan_kesesuaian', opts: [{ val: 'sesuai', label: 'Sesuai', color: 'green' }, { val: 'tidak_sesuai', label: 'Tidak Sesuai', color: 'red' }] },
                { label: 'Tingkat kelengkapan data',       model: 'temuan_kelengkapan', opts: [{ val: 'lengkap', label: 'Lengkap', color: 'green' }, { val: 'tidak_lengkap', label: 'Tidak Lengkap', color: 'red' }] },
                { label: 'Ketepatan perhitungan',          model: 'temuan_ketepatan',  opts: [{ val: 'tepat', label: 'Tepat', color: 'green' }, { val: 'tidak_tepat', label: 'Tidak Tepat', color: 'red' }] },
                { label: 'Validitas data',                 model: 'temuan_validitas',  opts: [{ val: 'valid', label: 'Valid', color: 'green' }, { val: 'tidak_valid', label: 'Tidak Valid', color: 'red' }] },
              ]" :key="ri" class="rounded-lg border border-gray-200 p-3">
                <p class="text-sm font-medium text-gray-700 mb-2">{{ ri + 1 }}. {{ row.label }}</p>
                <div class="flex gap-3">
                  <button
                    v-for="opt in row.opts"
                    :key="opt.val"
                    @click="ri === 0 ? temuanKesesuaian = temuanKesesuaian === opt.val ? null : opt.val
                          : ri === 1 ? temuanKelengkapan = temuanKelengkapan === opt.val ? null : opt.val
                          : ri === 2 ? temuanKetepatan = temuanKetepatan === opt.val ? null : opt.val
                          : temuanValiditas = temuanValiditas === opt.val ? null : opt.val"
                    class="flex-1 rounded-lg border-2 py-2 text-sm font-semibold transition-all"
                    :class="(ri === 0 ? temuanKesesuaian : ri === 1 ? temuanKelengkapan : ri === 2 ? temuanKetepatan : temuanValiditas) === opt.val
                      ? (opt.color === 'green' ? 'bg-green-500 text-white border-green-500' : 'bg-red-500 text-white border-red-500')
                      : 'border-gray-200 text-gray-500 hover:border-gray-400'"
                  >{{ opt.label }}</button>
                </div>
              </div>

              <!-- Catatan -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan tambahan (opsional)</label>
                <textarea v-model="penilaianCatatan" rows="3"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                  placeholder="Catatan temuan atau kondisi khusus..."
                ></textarea>
              </div>
            </div>

            <!-- ===== TAB 3: REKOMENDASI PERBAIKAN ===== -->
            <div v-if="penilaianTab === 'rekomendasi'">
              <p class="text-xs text-gray-500 italic mb-3">Tambahkan rencana perbaikan berdasarkan temuan di atas.</p>
              <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-xs">
                  <thead class="bg-indigo-50">
                    <tr>
                      <th class="px-2 py-2 text-center text-gray-600 font-semibold w-8">No</th>
                      <th class="px-3 py-2 text-left text-gray-600 font-semibold">Rencana Perbaikan</th>
                      <th class="px-3 py-2 text-left text-gray-600 font-semibold w-32">Penanggung Jawab</th>
                      <th class="px-3 py-2 text-left text-gray-600 font-semibold w-28">Target Waktu</th>
                      <th class="px-3 py-2 text-center text-gray-600 font-semibold w-24">Status</th>
                      <th class="px-2 py-2 w-8"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, ri) in rekomendasiRows" :key="ri" class="border-t border-gray-100">
                      <td class="px-2 py-1.5 text-center text-gray-400">{{ ri + 1 }}</td>
                      <td class="px-2 py-1.5">
                        <input v-model="rekomendasiRows[ri].rencana" type="text"
                          class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-indigo-400 focus:outline-none"
                          placeholder="Deskripsikan rencana perbaikan..." />
                      </td>
                      <td class="px-2 py-1.5">
                        <input v-model="rekomendasiRows[ri].penanggung_jawab" type="text"
                          class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-indigo-400 focus:outline-none"
                          placeholder="Nama/jabatan" />
                      </td>
                      <td class="px-2 py-1.5">
                        <input v-model="rekomendasiRows[ri].target_waktu" type="text"
                          class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-indigo-400 focus:outline-none"
                          placeholder="mis. 1 bulan" />
                      </td>
                      <td class="px-2 py-1.5 text-center">
                        <select v-model="rekomendasiRows[ri].status"
                          class="rounded border border-gray-200 px-1 py-1 text-xs focus:border-indigo-400 focus:outline-none"
                        >
                          <option value="Belum">Belum</option>
                          <option value="Proses">Proses</option>
                          <option value="Selesai">Selesai</option>
                        </select>
                      </td>
                      <td class="px-2 py-1.5 text-center">
                        <button @click="removeRekomendasiRow(ri)" class="text-red-400 hover:text-red-600" title="Hapus baris">
                          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                          </svg>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="rekomendasiRows.length === 0">
                      <td colspan="6" class="py-6 text-center text-xs text-gray-400 italic">Belum ada rencana perbaikan. Klik "+ Tambah Baris" untuk menambahkan.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button @click="addRekomendasiRow" class="mt-3 flex items-center gap-1.5 rounded-lg border border-dashed border-indigo-300 px-3 py-1.5 text-xs text-indigo-600 hover:bg-indigo-50 transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Baris
              </button>
            </div>

          </div><!-- end scrollable content -->

          <!-- Footer -->
          <div class="flex items-center justify-between border-t border-gray-200 px-5 py-3 flex-shrink-0 bg-gray-50 rounded-b-2xl">
            <div class="flex gap-1">
              <button v-if="penilaianTab !== 'aspek'" @click="penilaianTab = penilaianTab === 'rekomendasi' ? 'temuan' : 'aspek'"
                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100">← Sebelumnya</button>
              <button v-if="penilaianTab !== 'rekomendasi'" @click="penilaianTab = penilaianTab === 'aspek' ? 'temuan' : 'rekomendasi'"
                class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs text-indigo-600 hover:bg-indigo-50">Selanjutnya →</button>
            </div>
            <div class="flex gap-2">
              <button @click="showPenilaianModal = false"
                class="rounded-lg border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
              <button @click="savePenilaian" :disabled="penilaianLoading"
                class="flex items-center gap-2 rounded-lg px-4 py-1.5 text-sm text-white transition-colors"
                :class="!penilaianLoading ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-400 cursor-not-allowed'"
              >
                <span v-if="penilaianLoading" class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                Simpan Penilaian
              </button>
            </div>
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