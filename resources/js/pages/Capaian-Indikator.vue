<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { ChevronLeft, ChevronRight, MessageSquare, Upload, Eye } from 'lucide-vue-next';

interface TimUnit {
  id: number;
  kode_unit: string;
  nama_tim: string;
}

interface Unit {
  id: number;
  kode_unit: string;
  nama_unit: string;
  alias: string;
  tim_units?: TimUnit[];
}

interface Komentar {
  id: number;
  indikator_id: number;
  indikator_nama: string;
  komentar: string;
  dibaca: boolean;
  tanggal: string;
}

interface RejectedItem {
  nama: string;
  reason: string;
  rejected_at?: string | null;
}

interface TimApprovalSummary {
  nama_tim: string;
  total_indikator: number;
  approved: number;
  not_approved: number;
  rejected: number;
  rejected_list: RejectedItem[];
  by_jenis?: Record<string, number>;
  by_jenis_approved?: Record<string, number>;
  by_jenis_not_approved?: Record<string, number>;
}

interface Rekomendasi {
  status: 'Tercapai' | 'Tidak Tercapai' | 'Data Belum Lengkap' | 'Data Tersedia';
  color: 'green' | 'red' | 'gray';
  achievement: number | null;
  gap: string | null;
  recommendation: string;
  source: string | null;
}

interface TimIndikatorDetail {
  id: number;
  indikator: string;
  standar: string;
  n: number | null;
  d: number | null;
  has_data: boolean;
  approved: boolean;
  validated: boolean;
  rejected: boolean;
  reject_reason: string | null;
  rejected_n: number | null;
  rejected_d: number | null;
  rejected_at: string | null;
  komentar: string | null;
  revised: boolean;
  can_approve: boolean;
  can_reject: boolean;
  recommendation?: Rekomendasi;
}

interface Props {
  units: Unit[];
  selectedUnit: Unit | null;
  selectedTimUnit: string | null;
  capaianData: Indicator[];
  tahun: number;
  quarter: number;
  currentMonth: number;
  currentQuarter: number;
  currentYear: number;
  currentDay: number;
  canInput: boolean;
  komentarData: Record<number, { komentar: string; dibaca: boolean }>;
  isAdmin: boolean;
  canApprove: boolean;
  userRole: string;
  timApprovalSummary: TimApprovalSummary[];
  viewMonth: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Capaian Indikator', href: '/capaian-indikator' },
];

type MonthKey = 'jan'|'feb'|'mar'|'apr'|'may'|'jun'|'jul'|'aug'|'sep'|'oct'|'nov'|'des';
type MonthData = { N: number | null; D: number | null; lampiran?: string | null; N_prev?: number | null; D_prev?: number | null };
type MonthStatus = { validated: boolean };
type MonthApproval = {
  approved: boolean;
  rejected: boolean;
  reject_reason: string | null;
  rejected_n: number | null;
  rejected_d: number | null;
  rejected_at: string | null;
};
type MonthKomentar = { komentar: string | null; dibaca: boolean; revised: boolean };
type MonthAnalisisRtl = { analisis: string; rtl: string };
type RejectionHistoryEntry = {
  bulan: string;
  n: number | null;
  d: number | null;
  reason: string;
  at: string;
};
type Indicator = {
  id: number;
  capaian_id: number | null;
  jenis_indikator: string;
  is_prioritas: boolean;
  indikator: string;
  standar: string;
  satuan: 'rata-rata' | 'persen' | 'permil';
  satuan_waktu?: 'hari' | 'jam' | 'menit' | null;
  numeratorDesc: string;
  denominatorDesc: string;
  m: Record<MonthKey, MonthData>;
  att: Record<MonthKey, string | null>;
  v: Record<MonthKey, MonthStatus>;
  a: Record<MonthKey, MonthApproval>;
  komentar: Record<MonthKey, MonthKomentar>;
  analisisRtl: Record<MonthKey, MonthAnalisisRtl>;
  rejectionHistory: RejectionHistoryEntry[];
};

/* ===== Util bulan & bulan berjalan ===== */
const monthOrder: MonthKey[] = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','des'];
const idxToKey = (i:number) => monthOrder[i] as MonthKey;

// PENTING: props.currentMonth dari Laravel adalah 1-12, sedangkan array index adalah 0-11
// Jadi kita kurangi 1 untuk mendapatkan index yang benar
const currentMonthKey = ref<MonthKey>(idxToKey(props.currentMonth - 1));
const viewMonthKey = computed<MonthKey>(() => idxToKey((props.viewMonth || props.currentMonth) - 1));
const isViewingCurrentMonth = computed(() => Number(props.viewMonth) === Number(props.currentMonth) && Number(props.tahun) === Number(props.currentYear));
const selectedUnitCode = ref(props.selectedUnit?.kode_unit || '');
const selectedTimUnitRef = ref(props.selectedTimUnit || '');
const data = ref<Indicator[]>(props.capaianData);

// Searchable unit dropdown
const unitSearchQuery = ref('');
const unitDropdownOpen = ref(false);
const filteredUnitsSearch = computed(() => {
  if (!unitSearchQuery.value) return props.units;
  const q = unitSearchQuery.value.toLowerCase();
  return props.units.filter((u: any) => u.nama_unit.toLowerCase().includes(q) || u.kode_unit.toLowerCase().includes(q));
});
const unitDisplayName = computed(() =>
  props.units.find((u: any) => u.kode_unit === selectedUnitCode.value)?.nama_unit || ''
);
function openUnitDropdown() {
  if (!props.isAdmin) return;
  unitSearchQuery.value = '';
  unitDropdownOpen.value = true;
}
function closeUnitDropdown() {
  setTimeout(() => { unitDropdownOpen.value = false; unitSearchQuery.value = ''; }, 150);
}
function selectUnitFromSearch(unit: any) {
  selectedUnitCode.value = unit.kode_unit;
  unitDropdownOpen.value = false;
  unitSearchQuery.value = '';
  changeUnit();
}

// Searchable tim unit dropdown
const timSearchQuery = ref('');
const timDropdownOpen = ref(false);
const filteredTimUnitsSearch = computed(() => {
  if (!timSearchQuery.value) return availableTimUnits.value;
  const q = timSearchQuery.value.toLowerCase();
  return availableTimUnits.value.filter((t: any) => t.nama_tim.toLowerCase().includes(q));
});
const timDisplayName = computed(() =>
  availableTimUnits.value.find((t: any) => t.nama_tim === selectedTimUnitRef.value)?.nama_tim || selectedTimUnitRef.value || ''
);
function openTimDropdown() {
  timSearchQuery.value = '';
  timDropdownOpen.value = true;
}
function closeTimDropdown() {
  setTimeout(() => { timDropdownOpen.value = false; timSearchQuery.value = ''; }, 150);
}
function selectTimFromSearch(tim: any) {
  selectedTimUnitRef.value = tim.nama_tim;
  timDropdownOpen.value = false;
  timSearchQuery.value = '';
  changeTimUnit();
}

// Helper to ensure analisisRtl object exists for current month
function ensureAnalisisRtl(ind: Indicator): void {
  if (!ind.analisisRtl) {
    ind.analisisRtl = {} as Record<MonthKey, MonthAnalisisRtl>;
  }
  if (!ind.analisisRtl[currentMonthKey.value]) {
    ind.analisisRtl[currentMonthKey.value] = { analisis: '', rtl: '' };
  }
}

// Initialize analisisRtl for all indicators
data.value.forEach(ind => ensureAnalisisRtl(ind));

// Komentar modal state
const showKomentarPopup = ref(false);
const selectedKomentar = ref<{ indikator_id: number; indikator: string; komentar: string; dibaca: boolean; revised: boolean; bulan?: MonthKey } | null>(null);

// Lampiran modal state
const showLampiranModal = ref(false);
const selectedLampiran = ref<{ ind: Indicator | null; month: MonthKey | null; fileUrl: string | null }>({ ind: null, month: null, fileUrl: null });

// Pagination & Search for Indicators
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 3;

// Modal approve indikator per tim
const showApproveModal = ref(false);
const selectedTimForApprove = ref<string>('');
const timIndikatorList = ref<TimIndikatorDetail[]>([]);
const selectedIndikatorIds = ref<number[]>([]);
const loadingTimIndikator = ref(false);
const approvingSelected = ref(false);

// Flag untuk menampilkan tabel indikator untuk unit tanpa tim (setelah klik View di modal)
const showIndicatorTableForNoTim = ref(false);

// View detail indikator dalam modal
const viewingIndicatorDetail = ref<TimIndikatorDetail | null>(null);
const viewingIndicatorFullData = ref<Indicator | null>(null);
const detailAnalisis = ref('');
const detailRtl = ref('');
const savingDetailAnalisis = ref(false);

// Filter by jenis indikator
const filterJenis = ref<string>('');
const availableJenis = computed(() => {
  const order = ['INM', 'SPM', 'PRIORITAS', 'IMUT_RS', 'IMUT_UNIT'];
  const found = [...new Set(data.value.map((d: any) => d.jenis_indikator).filter(Boolean))];
  // Also add PRIORITAS if any INM indicator has is_prioritas=true
  const hasPrioritasFlag = data.value.some((d: any) => d.jenis_indikator === 'INM' && d.is_prioritas);
  if (hasPrioritasFlag && !found.includes('PRIORITAS')) found.push('PRIORITAS');
  return order.filter(j => found.includes(j));
});

// Data filtered by jenis only (used for approval summary counts)
const dataByJenis = computed(() => {
  if (!filterJenis.value) return data.value;
  if (filterJenis.value === 'PRIORITAS') {
    return data.value.filter((ind: any) => ind.jenis_indikator === 'PRIORITAS' || (ind.jenis_indikator === 'INM' && ind.is_prioritas));
  }
  return data.value.filter((ind: any) => ind.jenis_indikator === filterJenis.value);
});

// Helper: display jenis (virtual PRIORITAS for is_prioritas INM)
function displayJenis(ind: any): string {
  return ind._displayJenis ?? ind.jenis_indikator;
}

// Filtered & Paginated Indicators
const filteredIndicators = computed(() => {
  const jenisOrder: Record<string, number> = { INM: 0, SPM: 1, PRIORITAS: 2, IMUT_RS: 3, IMUT_UNIT: 4 };
  let result: any[] = data.value as any[];

  if (filterJenis.value) {
    if (filterJenis.value === 'PRIORITAS') {
      result = result.filter((ind: any) => ind.jenis_indikator === 'PRIORITAS' || (ind.jenis_indikator === 'INM' && ind.is_prioritas));
      // Show these as PRIORITAS group
      result = result.map((ind: any) => ind.jenis_indikator === 'INM' && ind.is_prioritas
        ? { ...ind, _displayJenis: 'PRIORITAS', _virtualKey: `vp_${ind.id}` }
        : ind
      );
    } else {
      result = result.filter((ind: any) => ind.jenis_indikator === filterJenis.value);
    }
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter((ind: any) =>
      ind.indikator.toLowerCase().includes(query) ||
      ind.standar.toLowerCase().includes(query)
    );
  }

  // When "Semua": add virtual PRIORITAS rows for is_prioritas=true INM indicators
  if (!filterJenis.value) {
    const virtualRows = result
      .filter((ind: any) => ind.jenis_indikator === 'INM' && ind.is_prioritas)
      .map((ind: any) => ({ ...ind, _displayJenis: 'PRIORITAS', _virtualKey: `vp_${ind.id}` }));
    result = [...result, ...virtualRows];
    // Re-sort by display jenis order then name
    result = result.slice().sort((a: any, b: any) => {
      const oa = jenisOrder[displayJenis(a)] ?? 99;
      const ob = jenisOrder[displayJenis(b)] ?? 99;
      if (oa !== ob) return oa - ob;
      return (a.indikator ?? '').localeCompare(b.indikator ?? '');
    });
  }

  return result;
});

const totalPages = computed(() => Math.ceil(filteredIndicators.value.length / itemsPerPage));

const paginatedIndicators = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return filteredIndicators.value.slice(start, end);
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

watch(filterJenis, () => {
  currentPage.value = 1;
});

// Komentar notification
const komentarList = ref<Komentar[]>([]);

// Load komentar saat component mount
onMounted(async () => {
  console.log('🚀 Component mounted');
  console.log('Selected Unit Code:', selectedUnitCode.value);
  console.log('Props Tahun:', props.tahun);
  console.log('Is Admin:', props.isAdmin);
  console.log('Has Tim Units:', hasTimUnits.value);

  if (selectedUnitCode.value) {
    await loadKomentar();
  } else {
    console.log('⚠️ Unit belum dipilih saat mount');
  }
});

// Watch untuk reload komentar saat unit berubah
watch(selectedUnitCode, async (newVal, oldVal) => {
  console.log('👀 Unit berubah dari', oldVal, 'ke', newVal);
  if (newVal) {
    await loadKomentar();
  } else {
    komentarList.value = [];
  }
});

// Watch tahun juga
watch(() => props.tahun, async (newVal, oldVal) => {
  console.log('📅 Tahun berubah dari', oldVal, 'ke', newVal);
  if (selectedUnitCode.value) {
    await loadKomentar();
  }
});

// Watch capaianData to reinitialize analisisRtl when data changes
watch(() => props.capaianData, (newData) => {
  data.value = newData;
  data.value.forEach(ind => ensureAnalisisRtl(ind));
}, { deep: true });

async function loadKomentar() {
  if (!selectedUnitCode.value) {
    console.log('❌ selectedUnitCode kosong, tidak load komentar');
    return;
  }
  
  console.log('🔍 Loading komentar...');
  console.log('   - kode_unit:', selectedUnitCode.value);
  console.log('   - tahun:', props.tahun);
  
  try {
    const response = await axios.post('/capaian-indikator/get-komentar', {
      tahun: props.tahun,
      kode_unit: selectedUnitCode.value
    });
    
    console.log('✅ Komentar loaded:', response.data);
    console.log('   - Jumlah:', response.data.length);
    
    komentarList.value = response.data;
    
    if (response.data.length === 0) {
      console.log('⚠️ Tidak ada komentar untuk unit ini');
    } else {
      console.log('📝 Komentar:', response.data.map((k: Komentar) => ({
        id: k.id,
        indikator: k.indikator_nama,
        komentar: k.komentar,
        dibaca: k.dibaca
      })));
    }
  } catch (error) {
    console.error('❌ Error loading komentar:', error);
    if (axios.isAxiosError(error) && error.response) {
      console.error('   - Status:', error.response.status);
      console.error('   - Data:', error.response.data);
    }
  }
}

const unreadKomentarCount = computed(() => {
  const count = komentarList.value.filter((k: Komentar) => !k.dibaca).length;
  console.log('🔔 Unread count:', count, 'dari total:', komentarList.value.length);
  return count;
});

function openKomentarPopup(ind: Indicator) {
  const komentarInfo = props.komentarData[ind.id];
  if (!komentarInfo) return;

  // Find which month has the comment in current quarter
  const quarterMonthsMap: Record<number, MonthKey[]> = {
    1: ['jan', 'feb', 'mar'],
    2: ['apr', 'may', 'jun'],
    3: ['jul', 'aug', 'sep'],
    4: ['oct', 'nov', 'des']
  };
  const monthsInQuarter = quarterMonthsMap[props.quarter];
  let foundMonth: MonthKey | undefined;

  for (const month of monthsInQuarter) {
    if (ind.komentar[month]?.komentar) {
      foundMonth = month;
      break;
    }
  }

  selectedKomentar.value = {
    indikator_id: ind.id,
    indikator: ind.indikator,
    komentar: komentarInfo.komentar,
    dibaca: komentarInfo.dibaca,
    revised: !!(foundMonth && ind.komentar[foundMonth]?.revised),
    bulan: foundMonth
  };
  showKomentarPopup.value = true;
}

function openKomentarPopupForMonth(ind: Indicator, month: MonthKey) {
  const komentarInfo = ind.komentar[month];
  if (!komentarInfo?.komentar) return;

  selectedKomentar.value = {
    indikator_id: ind.id,
    indikator: ind.indikator,
    komentar: komentarInfo.komentar,
    dibaca: komentarInfo.dibaca,
    revised: komentarInfo.revised ?? false,
    bulan: month
  };
  showKomentarPopup.value = true;
}

async function markKomentarAsDibaca() {
  if (!selectedKomentar.value) return;

  try {
    // Mark as read
    await axios.post('/capaian-indikator/mark-komentar-dibaca', {
      capaian_id: selectedKomentar.value.indikator_id,
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: selectedKomentar.value.bulan,
    });

    // Mark as revised
    if (selectedKomentar.value.bulan) {
      await axios.post('/capaian-indikator/mark-revised', {
        indikator_id: selectedKomentar.value.indikator_id,
        kode_unit: selectedUnitCode.value,
        tahun: props.tahun,
        bulan: selectedKomentar.value.bulan,
      });
    }

    // Update local state
    if (selectedKomentar.value.bulan) {
      const ind = data.value.find(i => i.id === selectedKomentar.value!.indikator_id);
      if (ind && ind.komentar[selectedKomentar.value.bulan]) {
        ind.komentar[selectedKomentar.value.bulan].dibaca = true;
        ind.komentar[selectedKomentar.value.bulan].revised = true;
      }
    } else {
      if (props.komentarData[selectedKomentar.value.indikator_id]) {
        props.komentarData[selectedKomentar.value.indikator_id].dibaca = true;
      }
    }

    selectedKomentar.value.revised = true;
    showKomentarPopup.value = false;

    // Reload page untuk refresh data
    setTimeout(() => {
      window.location.reload();
    }, 500);
  } catch (error) {
    console.error('Error marking as revised:', error);
    alert('Gagal menandai sudah direvisi');
  }
}

async function markAsDibaca(komentarId: number) {
  console.log('✅ Marking as read:', komentarId);
  
  try {
    await axios.post('/capaian-indikator/mark-komentar-dibaca', {
      capaian_id: komentarId
    });
    
    console.log('✅ Marked as read successfully');
    
    // Update local state
    const index = komentarList.value.findIndex((k: Komentar) => k.id === komentarId);
    if (index !== -1) {
      komentarList.value[index].dibaca = true;
      console.log('✅ Local state updated');
    }
    
    // Reload data after marking as read
    setTimeout(async () => {
      console.log('🔄 Reloading komentar...');
      await loadKomentar();
    }, 500);
  } catch (error) {
    console.error('❌ Error marking as read:', error);
  }
}

// Computed: apakah unit punya tim units
const hasTimUnits = computed(() => {
  return props.selectedUnit?.tim_units && props.selectedUnit.tim_units.length > 0;
});

// Computed: list tim units untuk dropdown
const availableTimUnits = computed(() => {
  return props.selectedUnit?.tim_units || [];
});

// Computed: apakah unit sudah dipilih
const isUnitSelected = computed(() => {
  return selectedUnitCode.value !== '';
});

// Computed: summary approval per tim
const timApprovalSummary = computed(() => {
  const all = props.timApprovalSummary || [];
  if (!filterJenis.value) return all;
  // When jenis filter active, only show tims that have indicators of that jenis
  return all.filter(tim => (tim.by_jenis?.[filterJenis.value] ?? 0) > 0);
});

// Filter approve status for tim approval summary
const timApprovalStatusFilter = ref<'semua' | 'sudah' | 'belum'>('semua');

const timApprovalFiltered = computed(() => {
  const list = timApprovalSummary.value;
  // Sudah: semua indikator sudah di-approve (approved >= total)
  if (timApprovalStatusFilter.value === 'sudah')
    return list.filter(t => timFilteredTotal(t) > 0 && timFilteredApproved(t) >= timFilteredTotal(t));
  // Belum: ada indikator yang sudah terisi data tapi belum di-approve (not_approved > 0)
  if (timApprovalStatusFilter.value === 'belum')
    return list.filter(t => timFilteredNotApproved(t) > 0);
  return list;
});

// Totals across ALL tims (not filtered by status, but filtered by jenis)
const timApprovalTotals = computed(() => ({
  indikator:    timApprovalSummary.value.reduce((s, t) => s + timFilteredTotal(t), 0),
  approved:     timApprovalSummary.value.reduce((s, t) => s + timFilteredApproved(t), 0),
  not_approved: timApprovalSummary.value.reduce((s, t) => s + timFilteredNotApproved(t), 0),
  rejected:     timApprovalSummary.value.reduce((s, t) => s + t.rejected, 0),
}));

// Pagination for tim approval summary
const timApprovalPage = ref(1);
const timApprovalPerPage = 5;
const timApprovalTotalPages = computed(() => Math.max(1, Math.ceil(timApprovalFiltered.value.length / timApprovalPerPage)));
const timApprovalPaginated = computed(() => {
  const start = (timApprovalPage.value - 1) * timApprovalPerPage;
  return timApprovalFiltered.value.slice(start, start + timApprovalPerPage);
});
// Reset page when filter changes
watch([filterJenis, timApprovalStatusFilter], () => { timApprovalPage.value = 1; });

function timFilteredTotal(tim: TimApprovalSummary): number {
  if (!filterJenis.value) {
    // Add virtual PRIORITAS count (INM+is_prioritas counted twice)
    return tim.total_indikator + (tim.by_jenis?.['PRIORITAS_VIRTUAL'] ?? 0);
  }
  return tim.by_jenis?.[filterJenis.value] ?? 0;
}
function timFilteredApproved(tim: TimApprovalSummary): number {
  if (!filterJenis.value) return tim.approved + (tim.by_jenis_approved?.['PRIORITAS_VIRTUAL'] ?? 0);
  return tim.by_jenis_approved?.[filterJenis.value] ?? 0;
}
function timFilteredNotApproved(tim: TimApprovalSummary): number {
  if (!filterJenis.value) return tim.not_approved + (tim.by_jenis_not_approved?.['PRIORITAS_VIRTUAL'] ?? 0);
  return tim.by_jenis_not_approved?.[filterJenis.value] ?? 0;
}

// Computed: summary approval untuk unit tanpa tim (single row)
const unitApprovalSummary = computed(() => {
  if (hasTimUnits.value || !isUnitSelected.value || data.value.length === 0) {
    return null;
  }

  const monthKey = viewMonthKey.value;
  let approved = 0;
  let notApproved = 0;
  let rejected = 0;
  const rejectedList: RejectedItem[] = [];

  // Build approval base: jenis-filtered + virtual PRIORITAS rows (same as indicator table, no search filter)
  let approvalBase: any[] = data.value as any[];
  if (filterJenis.value === 'PRIORITAS') {
    approvalBase = approvalBase.filter(i => i.jenis_indikator === 'PRIORITAS' || (i.jenis_indikator === 'INM' && i.is_prioritas));
  } else if (filterJenis.value) {
    approvalBase = approvalBase.filter(i => i.jenis_indikator === filterJenis.value);
  } else {
    // "Semua": include virtual PRIORITAS rows (is_prioritas=true INM counted twice)
    const virtualPri = approvalBase.filter(i => i.jenis_indikator === 'INM' && i.is_prioritas);
    approvalBase = [...approvalBase, ...virtualPri];
  }

  approvalBase.forEach(ind => {
    const monthData = ind.a?.[monthKey];
    const hasData = ind.m?.[monthKey]?.N !== null && ind.m?.[monthKey]?.D !== null;
    const hasRejectionHistory = monthData?.rejected_n !== null || monthData?.rejected_d !== null;

    if (monthData?.approved) {
      approved++;
    } else if (hasData || (monthData?.rejected && !hasData)) {
      // Has data waiting for approval OR rejected with data cleared (needs re-entry)
      notApproved++;
    }

    // Count rejection history (or active rejection for backward compatibility)
    if (hasRejectionHistory || monthData?.rejected) {
      rejected++;
      let historyInfo = '';
      if (hasRejectionHistory) {
        historyInfo = ` (N:${monthData?.rejected_n ?? '-'}, D:${monthData?.rejected_d ?? '-'})`;
      }
      rejectedList.push({
        nama: ind.indikator + historyInfo,
        reason: monthData?.reject_reason || 'Tidak ada alasan',
        rejected_at: monthData?.rejected_at || null
      });
    }
  });

  return {
    nama_unit: props.units?.find(u => u.kode_unit === selectedUnitCode.value)?.nama_unit || 'Unit',
    total_indikator: approvalBase.length,
    approved,
    not_approved: notApproved,
    rejected,
    rejected_list: rejectedList
  };
});

// Mapping quarter ke bulan-bulan
const quarterMonths: Record<number, MonthKey[]> = {
  1: ['jan', 'feb', 'mar'],
  2: ['apr', 'may', 'jun'],
  3: ['jul', 'aug', 'sep'],
  4: ['oct', 'nov', 'des']
};

// Months to display based on current quarter
const displayMonths = computed(() => quarterMonths[props.quarter]);

// Check if Analisis/RTL editable - HANYA aktif di bulan berjalan
function isAnalisisEditable(): boolean {
  // PENTING: Konversi tahun ke number karena props.tahun bisa jadi string dari URL
  const tahunDipilih = Number(props.tahun);
  const tahunSekarang = Number(props.currentYear);

  // Harus di tahun yang sama
  if (tahunDipilih !== tahunSekarang) {
    return false;
  }

  // Harus masih dalam batas waktu (sampai tanggal 29)
  if (props.currentDay && props.currentDay > 29) {
    return false;
  }

  // Cek apakah bulan berjalan ada di triwulan yang ditampilkan
  const quarterMonthsMap: Record<number, number[]> = {
    1: [1, 2, 3],
    2: [4, 5, 6],
    3: [7, 8, 9],
    4: [10, 11, 12]
  };

  const monthsInQuarter = quarterMonthsMap[props.quarter];
  return monthsInQuarter.includes(props.currentMonth);
}

// Helper: cek apakah ada komentar dari admin mutu untuk bulan tertentu
function hasKomentarAdmin(ind: Indicator, month: MonthKey): boolean {
  return !!(ind.komentar && ind.komentar[month]?.komentar);
}

// Helper: cek apakah sudah ditandai direvisi
function isRevised(ind: Indicator, month: MonthKey): boolean {
  return !!(ind.komentar && ind.komentar[month]?.revised);
}

const markingRevised = ref<number | null>(null);
async function doMarkRevised(ind: Indicator) {
  markingRevised.value = ind.id;
  try {
    await axios.post('/capaian-indikator/mark-revised', {
      indikator_id: ind.id,
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: currentMonthKey.value,
    });
    if (ind.komentar && ind.komentar[currentMonthKey.value]) {
      ind.komentar[currentMonthKey.value].revised = true;
    }
  } catch (e) {
    alert('Gagal menandai sudah direvisi');
  } finally {
    markingRevised.value = null;
  }
}

// Check if Analisis/RTL can be edited for specific indicator
// Must be editable period AND indicator not yet approved for current month
function canEditAnalisis(ind: Indicator): boolean {
  // Kepala unit tidak boleh input analisis/RTL
  if (isKepalaUnit.value) return false;
  if (!isAnalisisEditable()) return false;
  // Jika ada komentar admin, izinkan edit meski sudah approve
  if (hasKomentarAdmin(ind, currentMonthKey.value)) return true;
  // If approved for current month, cannot edit
  if (ind.a && ind.a[currentMonthKey.value]?.approved) return false;
  return true;
}

function monthLabel(m: MonthKey){
  const map: Record<MonthKey,string> = {
    jan:'JAN', feb:'FEB', mar:'MAR', apr:'APR', may:'MAY', jun:'JUN',
    jul:'JUL', aug:'AUG', sep:'SEP', oct:'OCT', nov:'NOV', des:'DES'
  };
  return map[m];
}

// Kepala unit hanya boleh lihat & approve, tidak boleh input data
const isKepalaUnit = computed(() => props.userRole === 'kepala_unit');

function isWindowOpen(m: MonthKey){
  // Kepala unit tidak boleh input data
  if (isKepalaUnit.value) return false;

  // Hanya bisa input jika:
  // 1. Bulan sama dengan bulan berjalan
  // 2. Tahun sama dengan tahun berjalan
  // 3. Tanggal masih <= 29

  // PENTING: Konversi tahun ke number karena props.tahun bisa jadi string dari URL
  const tahunDipilih = Number(props.tahun);
  const tahunSekarang = Number(props.currentYear);

  return m === currentMonthKey.value &&
         tahunDipilih === tahunSekarang &&
         props.currentDay <= 29;
}

function cellClassWithValidation(ind: Indicator, m: MonthKey){
  const validated = isValidated(ind, m);
  const approved = isApproved(ind, m);
  const hasKomentar = hasKomentarAdmin(ind, m);
  // Bisa diedit jika: window buka, belum validated, dan (belum approved ATAU ada komentar admin)
  const editable = isWindowOpen(m) && !validated && (!approved || hasKomentar);
  let classes = 'px-2 py-2 text-center';
  if (editable) classes += ' cursor-pointer hover:bg-amber-50';
  else classes += ' opacity-60 cursor-not-allowed';
  if (validated) classes += ' validated-cell';
  if (approved && !validated && !hasKomentar) classes += ' approved-cell';
  return classes;
}

function hasil(ind: Indicator, md: MonthData): number | null {
  if (!md || md.N == null || md.D == null || md.D === 0) return null;

  const satuan = ind.satuan || 'persen';

  if (satuan === 'rata-rata') {
    // Rata-rata = N / D (hanya angka)
    return md.N / md.D;
  } else if (satuan === 'persen') {
    // Persen = (N / D) * 100
    return (md.N / md.D) * 100;
  } else if (satuan === 'permil') {
    // Permil = (N / D) * 1000
    return (md.N / md.D) * 1000;
  }

  return (md.N / md.D) * 100; // default persen
}

function rata2Quarter(ind: Indicator): number | null {
  const arr = displayMonths.value.map(k => hasil(ind, ind.m[k])).filter(v => v != null) as number[];
  if (!arr.length) return null;
  return arr.reduce((a,b)=>a+b,0) / arr.length;
}

function fmtPct(ind: Indicator, v: number | null): string {
  if (v == null) return '';

  const satuan = ind.satuan || 'persen';

  if (satuan === 'rata-rata') {
    // Format: angka + satuan waktu (jika ada) - bulatkan ke atas
    const suffix = ind.satuan_waktu ? ` ${ind.satuan_waktu}` : '';
    return `${Math.ceil(v)}${suffix}`;
  } else if (satuan === 'persen') {
    // Bulatkan ke atas, tanpa desimal
    return `${Math.ceil(v)}%`;
  } else if (satuan === 'permil') {
    // Bulatkan ke atas
    return `${Math.ceil(v)}‰`;
  }

  return `${Math.ceil(v)}%`; // default
}

function fmtPctCapped(ind: Indicator, v: number | null): string {
  if (v == null) return '';
  const satuan = ind.satuan || 'persen';
  if (satuan === 'persen' && v > 100) return '100%';
  return fmtPct(ind, v);
}

function isOverCap(ind: Indicator, v: number | null): boolean {
  if (v == null) return false;
  return (ind.satuan || 'persen') === 'persen' && v > 100;
}

const jenisBadgeClass: Record<string, string> = {
  INM: 'bg-blue-100 text-blue-700',
  SPM: 'bg-green-100 text-green-700',
  PRIORITAS: 'bg-purple-100 text-purple-700',
  IMUT_RS: 'bg-orange-100 text-orange-700',
  IMUT_UNIT: 'bg-teal-100 text-teal-700',
};
function jenisLabel(jenis: string): string {
  if (jenis === 'IMUT_RS') return 'IMUT RS';
  if (jenis === 'IMUT_UNIT') return 'IMUT UNIT';
  return jenis;
}

const showModal = ref(false);
const modalTitle = ref('');
const infoBulan = ref<MonthKey>('jan');
const infoIndikator = ref('');
const infoNumerator = ref('');
const infoDenominator = ref('');
const targetField = ref<'N'|'D'>('N');
const editingId = ref<number | null>(null);
const inputNilai = ref<number | null>(null);

const warn = ref<{show:boolean; message:string}>({ show:false, message:'Jadwal input capaian indikator tidak dibuka pada tanggal bulan dipilih' });
function openWarn(msg?:string){ warn.value.message = msg || warn.value.message; warn.value.show=true; }
function closeWarn(){ warn.value.show=false; }

function isValidated(ind: Indicator, m: MonthKey){
  return !!(ind.v && ind.v[m] && ind.v[m].validated);
}

function displayVal(ind: Indicator, m: MonthKey, field: 'N'|'D'){
  return ind.m[m][field] == null ? '—' : String(ind.m[m][field]);
}

function openCell(ind: Indicator, month: MonthKey, field: 'N'|'D'){
  if (!isWindowOpen(month)) { openWarn(); return; }
  if (isValidated(ind, month)) { openWarn('Data bulan ini sudah divalidasi, tidak dapat diubah.'); return; }
  // Izinkan edit jika ada komentar dari admin mutu (meski sudah approve)
  if (isApproved(ind, month) && !hasKomentarAdmin(ind, month)) { openWarn('Data bulan ini sudah di-approve, tidak dapat diubah.'); return; }
  editingId.value   = ind.id;
  infoBulan.value   = month;
  infoIndikator.value = ind.indikator;
  infoNumerator.value = ind.numeratorDesc;
  infoDenominator.value = ind.denominatorDesc;
  targetField.value  = field;
  inputNilai.value   = ind.m[month][field];
  modalTitle.value   = `Input Capaian — ${ind.indikator} (${monthLabel(month)})`;
  showModal.value    = true;
}

function saveCell(){
  if (editingId.value==null) return;
  const ind = data.value.find(x=>x.id===editingId.value);
  if (!ind) return;
  if (inputNilai.value==null || Number.isNaN(inputNilai.value)){ alert('Nilai wajib diisi'); return; }
  if (!isWindowOpen(infoBulan.value)) { openWarn(); return; }
  if (isValidated(ind, infoBulan.value)) { openWarn('Data bulan ini sudah divalidasi, tidak dapat diubah.'); return; }
  if (isApproved(ind, infoBulan.value) && !hasKomentarAdmin(ind, infoBulan.value)) { openWarn('Data bulan ini sudah di-approve, tidak dapat diubah.'); return; }

  axios.post('/capaian-indikator/save', {
    indikator_id: ind.id,
    kode_unit: selectedUnitCode.value,
    tahun: props.tahun,
    bulan: infoBulan.value,
    field: targetField.value,
    nilai: inputNilai.value,
  })
  .then(response => {
    ind.m[infoBulan.value][targetField.value] = Number(inputNilai.value);
    if (ind.a && ind.a[infoBulan.value]?.rejected) {
      ind.a[infoBulan.value].rejected = false;
    }
    // Auto-fill indikator lain dengan nama sama tapi jenis berbeda
    const autoIds: number[] = response.data.autoFilledIds ?? [];
    if (autoIds.length > 0) {
      autoIds.forEach((autoId: number) => {
        const autoInd = data.value.find(x => x.id === autoId);
        if (autoInd) {
          if (!autoInd.m[infoBulan.value]) {
            autoInd.m[infoBulan.value] = { N: null, D: null };
          }
          autoInd.m[infoBulan.value][targetField.value] = Number(inputNilai.value);
          if (autoInd.a && autoInd.a[infoBulan.value]?.rejected) {
            autoInd.a[infoBulan.value].rejected = false;
          }
        }
      });
    }
    showModal.value = false;
    const autoMsg = autoIds.length > 0 ? `\n(${autoIds.length} indikator sejenis ter-isi otomatis)` : '';
    alert('Data berhasil disimpan!' + autoMsg);
  })
  .catch((error: unknown) => {
    console.error('Error saving:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal menyimpan data');
    } else {
      alert('Gagal menyimpan data');
    }
  });
}

function onUpload(ind: Indicator, m: MonthKey, e: Event){
  if (!isWindowOpen(m)) { openWarn(); return; }
  if (isValidated(ind, m)) { openWarn('Lampiran tidak bisa diganti karena bulan ini sudah divalidasi.'); return; }

  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

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
    console.log('Upload success:', response.data);
    ind.att[m] = response.data.file_name;
    // Pesan berbeda untuk upload baru vs upload ulang
    const message = response.data.is_reupload
      ? 'File berhasil diupload ulang! File lama telah diganti.'
      : 'File berhasil diupload!';
    alert(message);
    // Reset input
    input.value = '';
  })
  .catch((error: unknown) => {
    console.error('Upload error:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal upload file');
    } else {
      alert('Gagal upload file');
    }
    // Reset input
    input.value = '';
  });
}

// Open modal untuk lihat lampiran
function openLampiranModal(ind: Indicator, m: MonthKey) {
  const fileName = ind.att[m];
  if (!fileName) {
    alert('Tidak ada lampiran');
    return;
  }

  selectedLampiran.value = {
    ind: ind,
    month: m,
    fileUrl: `/capaian-indikator/lampiran/${fileName}`
  };
  showLampiranModal.value = true;
}

// Close modal lampiran
function closeLampiranModal() {
  showLampiranModal.value = false;
  selectedLampiran.value = { ind: null, month: null, fileUrl: null };
}

function saveAnalisis(ind: Indicator){
  if (!isAnalisisEditable()) {
    alert('Pengisian analisis/RTL hanya dapat dilakukan di bulan berjalan (sampai tanggal 29)');
    return;
  }

  axios.post('/capaian-indikator/analisis', {
    indikator_id: ind.id,
    kode_unit: selectedUnitCode.value,
    tahun: props.tahun,
    bulan: currentMonthKey.value,
    analisis: ind.analisisRtl[currentMonthKey.value]?.analisis || '',
    rtl: ind.analisisRtl[currentMonthKey.value]?.rtl || '',
  })
  .then(response => {
    console.log('Analisis saved:', response.data);
  })
  .catch((error: unknown) => {
    console.error('Error saving analisis:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal menyimpan analisis');
    } else {
      alert('Gagal menyimpan analisis');
    }
  });
}

// Approve state
const approvingIndicator = ref<number | null>(null);
const approvingAll = ref(false);

// Check if indicator can be approved for current month
function canApprove(ind: Indicator, month: MonthKey): boolean {
  // Must have N and D values
  if (ind.m[month].N === null || ind.m[month].D === null) return false;
  // Must not already be approved
  if (ind.a && ind.a[month]?.approved) return false;
  // Must not be validated
  if (ind.v[month]?.validated) return false;
  return true;
}

// Check if indicator is approved
function isApproved(ind: Indicator, month: MonthKey): boolean {
  return ind.a && ind.a[month]?.approved === true;
}

// Approve single indicator for current month
async function approveIndicator(ind: Indicator) {
  if (!canApprove(ind, currentMonthKey.value)) {
    alert('Data tidak dapat di-approve. Pastikan N dan D sudah diisi.');
    return;
  }

  approvingIndicator.value = ind.id;

  try {
    await axios.post('/capaian-indikator/approve', {
      indikator_id: ind.id,
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: currentMonthKey.value,
    });

    // Update local state
    if (!ind.a) {
      ind.a = {} as Record<MonthKey, MonthApproval>;
    }
    ind.a[currentMonthKey.value] = { approved: true, rejected: false, reject_reason: null, rejected_n: null, rejected_d: null, rejected_at: null };

    alert('Capaian berhasil di-approve!');
  } catch (error: unknown) {
    console.error('Error approving:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal approve capaian');
    } else {
      alert('Gagal approve capaian');
    }
  } finally {
    approvingIndicator.value = null;
  }
}

// Approve all indicators for current month (bisa approve semua tim atau tim tertentu)
async function approveAllIndicators(approveAllTims: boolean = false) {
  // Jika approve semua tim (dari panel sebelum pilih tim)
  const isApprovingAllTims = approveAllTims || (!selectedTimUnitRef.value && hasTimUnits.value);

  let confirmMessage = '';
  if (isApprovingAllTims) {
    confirmMessage = `Approve SEMUA indikator dari SEMUA TIM untuk bulan ${monthLabel(currentMonthKey.value)}?`;
  } else {
    const approvableCount = data.value.filter(ind => canApprove(ind, currentMonthKey.value)).length;
    if (approvableCount === 0) {
      alert('Tidak ada indikator yang dapat di-approve. Pastikan semua N dan D sudah diisi.');
      return;
    }
    confirmMessage = `Approve ${approvableCount} indikator untuk bulan ${monthLabel(currentMonthKey.value)}?`;
  }

  if (!confirm(confirmMessage)) {
    return;
  }

  approvingAll.value = true;

  try {
    const response = await axios.post('/capaian-indikator/approve-all', {
      kode_unit: selectedUnitCode.value,
      tim_unit: isApprovingAllTims ? '__ALL__' : (selectedTimUnitRef.value || null),
      tahun: props.tahun,
      bulan: currentMonthKey.value,
    });

    // Update local state for all approved indicators (jika ada data ditampilkan)
    if (data.value.length > 0) {
      data.value.forEach(ind => {
        if (canApprove(ind, currentMonthKey.value)) {
          if (!ind.a) {
            ind.a = {} as Record<MonthKey, MonthApproval>;
          }
          ind.a[currentMonthKey.value] = { approved: true, rejected: false, reject_reason: null, rejected_n: null, rejected_d: null, rejected_at: null };
        }
      });
    }

    alert(`${response.data.updated} indikator berhasil di-approve!`);

    // Reload halaman jika approve semua tim
    if (isApprovingAllTims) {
      window.location.reload();
    }
  } catch (error: unknown) {
    console.error('Error approving all:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal approve semua capaian');
    } else {
      alert('Gagal approve semua capaian');
    }
  } finally {
    approvingAll.value = false;
  }
}

// Check if any indicator can be approved
const hasApprovableIndicators = computed(() => {
  return data.value.some(ind => canApprove(ind, currentMonthKey.value));
});

// Fungsi untuk menentukan warna baris indikator berdasarkan status approval
// Hijau: sudah di-approve, Orange: ada komentar admin (perlu revisi), Kuning: diisi tapi belum approve, Abu-abu: belum diisi
function getIndicatorRowClass(ind: Indicator): string {
  const month = currentMonthKey.value;
  const isApproved = ind.a && ind.a[month]?.approved;
  const isRejected = ind.a && ind.a[month]?.rejected;
  const hasData = ind.m && ind.m[month] && ind.m[month].N !== null && ind.m[month].D !== null;
  const hasKomentarAdmin = !!(ind.komentar && ind.komentar[month]?.komentar);

  const isRevisedFlag = !!(ind.komentar && ind.komentar[month]?.revised);

  if (isApproved) {
    return 'bg-green-50'; // Hijau - sudah di-approve
  } else if (hasKomentarAdmin && hasData) {
    return 'bg-yellow-100'; // Kuning - ada komentar (sudah/belum direvisi), sama seperti belum approve
  } else if (hasData) {
    return 'bg-yellow-100'; // Kuning - sudah diisi tapi belum approve
  } else if (isRejected) {
    return 'bg-gray-100'; // Abu-abu - perlu diisi ulang (reject dari kepala unit)
  } else {
    return 'bg-gray-100'; // Abu-abu - belum diisi
  }
}

// Fungsi untuk mendapatkan alasan reject
function getRejectReason(ind: Indicator): string | null {
  const month = currentMonthKey.value;
  return ind.a && ind.a[month]?.reject_reason || null;
}

// Check if indicator has any rejected history
function hasRejectedHistory(ind: Indicator): boolean {
  // Check JSON rejection history array
  if (ind.rejectionHistory && ind.rejectionHistory.length > 0) return true;
  // Backward compat: check single-column data
  if (!ind.a) return false;
  return displayMonths.value.some(month => {
    const approval = ind.a[month];
    return approval && (approval.rejected_n !== null || approval.rejected_d !== null || approval.rejected);
  });
}

// Get rejection history entries for displayed months
function getMonthRejectionHistory(ind: Indicator, month: MonthKey): RejectionHistoryEntry[] {
  if (!ind.rejectionHistory) return [];
  return ind.rejectionHistory.filter(entry => entry.bulan === month);
}

// ===== Fungsi untuk Modal Approve Indikator per Tim =====
async function openApproveModal(namaTim: string) {
  selectedTimForApprove.value = namaTim;
  selectedIndikatorIds.value = [];
  loadingTimIndikator.value = true;
  showApproveModal.value = true;

  try {
    const response = await axios.post('/capaian-indikator/get-tim-indikator', {
      kode_unit: selectedUnitCode.value,
      tim_unit: namaTim,
      tahun: props.tahun,
      bulan: viewMonthKey.value,
    });
    timIndikatorList.value = response.data;
  } catch (error) {
    console.error('Error loading tim indikator:', error);
    alert('Gagal memuat data indikator');
    showApproveModal.value = false;
  } finally {
    loadingTimIndikator.value = false;
  }
}

function closeApproveModal() {
  showApproveModal.value = false;
  selectedTimForApprove.value = '';
  timIndikatorList.value = [];
  selectedIndikatorIds.value = [];
  rejectReason.value = '';
  viewingIndicatorDetail.value = null;
  viewingIndicatorFullData.value = null;
  expandedRecRows.value = new Set();
  showRejectInput.value = false;
  activeRecInd.value = null;
  detailRejectMode.value = false;
  detailRejectReason.value = '';
}

// Loading state untuk detail view
const loadingIndicatorDetail = ref(false);

// Expanded recommendation rows in approve modal list
const expandedRecRows = ref<Set<number>>(new Set());
function toggleRecExpand(id: number): void {
  const next = new Set(expandedRecRows.value);
  if (next.has(id)) { next.delete(id); } else { next.add(id); }
  expandedRecRows.value = next;
}

// View detail indikator dalam modal (tidak keluar dari modal)
async function viewIndicatorDetail(ind: TimIndikatorDetail) {
  // Set the indicator to view
  viewingIndicatorDetail.value = ind;
  loadingIndicatorDetail.value = true;
  viewingIndicatorFullData.value = null;

  try {
    // Fetch full indicator data from server
    const response = await axios.post('/capaian-indikator/get-indicator-detail', {
      indikator_id: ind.id,
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: viewMonthKey.value,
    });

    // Transform response to match Indicator interface
    const indData = response.data;
    viewingIndicatorFullData.value = {
      id: indData.id,
      capaian_id: indData.capaian_id || null,
      indikator: indData.indikator,
      standar: indData.standar,
      numeratorDesc: indData.numerator_desc,
      denominatorDesc: indData.denominator_desc,
      satuan: indData.satuan,
      polaritas: indData.polaritas,
      jenis_indikator: indData.jenis_indikator,
      is_prioritas: indData.is_prioritas,
      m: indData.months || {},
      att: indData.attachments || {},
      a: indData.approvals || {},
      v: indData.validations || {},
      komentar: indData.komentars || {},
      analisisRtl: indData.analisisRtl || {},
      rejectionHistory: indData.rejectionHistory || [],
    } as Indicator;

    // Attach recommendation from detail response
    if (indData.recommendation && viewingIndicatorDetail.value) {
      viewingIndicatorDetail.value = { ...viewingIndicatorDetail.value, recommendation: indData.recommendation };
    }

    // Populate analisis/RTL for viewed month
    const mk = viewMonthKey.value;
    detailAnalisis.value = indData.analisisRtl?.[mk]?.analisis || '';
    detailRtl.value = indData.analisisRtl?.[mk]?.rtl || '';
  } catch (error) {
    console.error('Error loading indicator detail:', error);
    alert('Gagal memuat detail indikator');
    viewingIndicatorDetail.value = null;
  } finally {
    loadingIndicatorDetail.value = false;
  }
}

// Kembali ke daftar indikator dari view detail
function backToIndicatorList() {
  viewingIndicatorDetail.value = null;
  viewingIndicatorFullData.value = null;
  loadingIndicatorDetail.value = false;
  detailRejectMode.value = false;
  detailRejectReason.value = '';
}

// Save analisis/RTL dari modal detail (kepala unit)
async function saveDetailAnalisis() {
  if (!viewingIndicatorFullData.value || !isViewingCurrentMonth.value) return;
  savingDetailAnalisis.value = true;
  try {
    await axios.post('/capaian-indikator/analisis', {
      indikator_id: viewingIndicatorFullData.value.id,
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: viewMonthKey.value,
      analisis: detailAnalisis.value,
      rtl: detailRtl.value,
    });
    console.log('Detail analisis/RTL saved');
  } catch (error: unknown) {
    console.error('Error saving analisis:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal menyimpan analisis/RTL');
    } else {
      alert('Gagal menyimpan analisis/RTL');
    }
  } finally {
    savingDetailAnalisis.value = false;
  }
}

function toggleIndikatorSelection(id: number) {
  const idx = selectedIndikatorIds.value.indexOf(id);
  if (idx === -1) {
    selectedIndikatorIds.value.push(id);
  } else {
    selectedIndikatorIds.value.splice(idx, 1);
  }
}

// Untuk admin/canApprove: hanya tampilkan indikator yang capaiannya sudah terisi (has_data)
const displayedTimIndikatorList = computed(() => {
  if (!props.canApprove) return timIndikatorList.value;
  return timIndikatorList.value.filter(ind => ind.has_data);
});

function toggleSelectAll() {
  const approvableIds = displayedTimIndikatorList.value
    .filter(ind => ind.can_approve)
    .map(ind => ind.id);

  if (selectedIndikatorIds.value.length === approvableIds.length) {
    selectedIndikatorIds.value = [];
  } else {
    selectedIndikatorIds.value = [...approvableIds];
  }
}

const isAllSelected = computed(() => {
  const approvableIds = displayedTimIndikatorList.value.filter(ind => ind.can_approve);
  return approvableIds.length > 0 && selectedIndikatorIds.value.length === approvableIds.length;
});

async function approveSelectedIndicators() {
  if (!isViewingCurrentMonth.value) {
    alert('Approve hanya bisa dilakukan di bulan berjalan');
    return;
  }
  if (selectedIndikatorIds.value.length === 0) {
    alert('Pilih minimal 1 indikator untuk di-approve');
    return;
  }

  if (!confirm(`Approve ${selectedIndikatorIds.value.length} indikator yang dipilih?`)) {
    return;
  }

  approvingSelected.value = true;

  try {
    const response = await axios.post('/capaian-indikator/approve-selected', {
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: viewMonthKey.value,
      indikator_ids: selectedIndikatorIds.value,
    });

    alert(`${response.data.updated} indikator berhasil di-approve!`);
    closeApproveModal();
    window.location.reload();
  } catch (error: unknown) {
    console.error('Error approving selected:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal approve indikator');
    } else {
      alert('Gagal approve indikator');
    }
  } finally {
    approvingSelected.value = false;
  }
}

// Reject functionality
const rejectReason = ref('');
const rejectingSelected = ref(false);

// Show reject reason input only when reject button clicked (list view)
const showRejectInput = ref(false);

// Indicator whose recommendation is shown in the bottom panel
const activeRecInd = ref<TimIndikatorDetail | null>(null);

// Approve/Reject from detail view
const approvingFromDetail = ref(false);
const rejectingFromDetail = ref(false);
const detailRejectMode = ref(false);
const detailRejectReason = ref('');

async function approveFromDetail() {
  if (!viewingIndicatorDetail.value || !isViewingCurrentMonth.value) return;
  if (!confirm(`Approve indikator "${viewingIndicatorDetail.value.indikator}"?`)) return;
  approvingFromDetail.value = true;
  try {
    const response = await axios.post('/capaian-indikator/approve-selected', {
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: viewMonthKey.value,
      indikator_ids: [viewingIndicatorDetail.value.id],
    });
    alert(`${response.data.updated} indikator berhasil di-approve!`);
    closeApproveModal();
    window.location.reload();
  } catch (error: unknown) {
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal approve indikator');
    } else {
      alert('Gagal approve indikator');
    }
  } finally {
    approvingFromDetail.value = false;
  }
}

async function rejectFromDetail() {
  if (!viewingIndicatorDetail.value || !isViewingCurrentMonth.value) return;
  const reason = detailRejectReason.value.trim() || 'Ditolak oleh kepala unit';
  rejectingFromDetail.value = true;
  try {
    const response = await axios.post('/capaian-indikator/reject-selected', {
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: viewMonthKey.value,
      indikator_ids: [viewingIndicatorDetail.value.id],
      reason: reason,
    });
    alert(`${response.data.updated} indikator berhasil di-reject!`);
    closeApproveModal();
    window.location.reload();
  } catch (error: unknown) {
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal reject indikator');
    } else {
      alert('Gagal reject indikator');
    }
  } finally {
    rejectingFromDetail.value = false;
  }
}

async function rejectSelectedIndicators() {
  if (!isViewingCurrentMonth.value) {
    alert('Reject hanya bisa dilakukan di bulan berjalan');
    return;
  }
  if (selectedIndikatorIds.value.length === 0) {
    alert('Pilih minimal 1 indikator untuk di-reject');
    return;
  }

  const reason = rejectReason.value.trim() || 'Ditolak oleh kepala unit';

  rejectingSelected.value = true;

  try {
    const response = await axios.post('/capaian-indikator/reject-selected', {
      kode_unit: selectedUnitCode.value,
      tahun: props.tahun,
      bulan: viewMonthKey.value,
      indikator_ids: selectedIndikatorIds.value,
      reason: reason,
    });

    alert(`${response.data.updated} indikator berhasil di-reject!`);
    closeApproveModal();
    window.location.reload();
  } catch (error: unknown) {
    console.error('Error rejecting selected:', error);
    if (axios.isAxiosError(error)) {
      alert(error.response?.data?.error || 'Gagal reject indikator');
    } else {
      alert('Gagal reject indikator');
    }
  } finally {
    rejectingSelected.value = false;
  }
}

function changeUnit(){
  if (!selectedUnitCode.value) return;
  selectedTimUnitRef.value = '';
  showIndicatorTableForNoTim.value = false; // Reset flag saat ganti unit
  router.get('/capaian-indikator', {
    unit: selectedUnitCode.value,
    tahun: props.tahun,
    quarter: props.quarter
  }, {
    preserveState: false,
    preserveScroll: false,
  });
}

function changeTimUnit(){
  router.get('/capaian-indikator', {
    unit: selectedUnitCode.value,
    tim_unit: selectedTimUnitRef.value,
    tahun: props.tahun,
    quarter: props.quarter
  }, {
    preserveState: false,
    preserveScroll: false,
  });
}

// Navigasi bulan untuk approval summary
function navigateMonth(direction: 'prev' | 'next') {
  let newMonth = props.viewMonth || props.currentMonth;
  let newYear = Number(props.tahun);
  if (direction === 'next') {
    newMonth++;
    if (newMonth > 12) { newMonth = 1; newYear++; }
  } else {
    newMonth--;
    if (newMonth < 1) { newMonth = 12; newYear--; }
  }
  // Hitung quarter dari bulan yang dilihat
  const newQuarter = Math.ceil(newMonth / 3);
  router.get('/capaian-indikator', {
    unit: selectedUnitCode.value,
    tahun: newYear,
    quarter: newQuarter,
    viewMonth: newMonth,
  }, {
    preserveState: false,
    preserveScroll: false,
  });
}

// Label bulan lengkap untuk navigator
function monthLabelFull(m: MonthKey): string {
  const map: Record<MonthKey,string> = {
    jan:'Januari', feb:'Februari', mar:'Maret', apr:'April', may:'Mei', jun:'Juni',
    jul:'Juli', aug:'Agustus', sep:'September', oct:'Oktober', nov:'November', des:'Desember'
  };
  return map[m];
}

// Kembali ke tampilan unit (tanpa pilih tim)
function goBackToUnit(){
  selectedTimUnitRef.value = '';
  router.get('/capaian-indikator', {
    unit: selectedUnitCode.value,
    tahun: props.tahun,
    quarter: props.quarter
  }, {
    preserveState: false,
    preserveScroll: false,
  });
}

// Kembali ke tabel approval (untuk unit tanpa tim)
function goBackToApprovalTable() {
  showIndicatorTableForNoTim.value = false;
}

function navigateQuarter(direction: 'prev' | 'next') {
  if (!isUnitSelected.value) return;
  
  let newQuarter = props.quarter;
  let newYear = props.tahun;
  
  if (direction === 'next') {
    newQuarter++;
    if (newQuarter > 4) {
      newQuarter = 1;
      newYear++;
    }
  } else {
    newQuarter--;
    if (newQuarter < 1) {
      newQuarter = 4;
      newYear--;
    }
  }
  
  router.get('/capaian-indikator', {
    unit: selectedUnitCode.value,
    tim_unit: selectedTimUnitRef.value || undefined,
    tahun: newYear,
    quarter: newQuarter
  }, {
    preserveState: false,
    preserveScroll: false,
  });
}
</script>

<template>
  <Head title="Capaian Indikator" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-3 p-4 xl:p-6">
      <div class="flex flex-1 min-h-0 flex-col rounded-xl border border-l-4 border-sidebar-border/70 bg-white p-3 dark:border-sidebar-border xl:p-4">
        <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 text-center mb-3">
          CAPAIAN INDIKATOR
        </h3>

        <!-- Dropdown Pilih Unit & Tim Unit -->
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-black-800 text-sm flex-shrink-0">
          <div class="grid gap-3" :class="hasTimUnits ? 'grid-cols-1 md:grid-cols-3' : 'grid-cols-1 md:grid-cols-2'">
            <!-- Searchable Unit Dropdown -->
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Pilih Bagian/Unit</label>
              <div class="relative">
                <div class="relative">
                  <input
                    :value="unitDropdownOpen ? unitSearchQuery : unitDisplayName"
                    @input="unitSearchQuery = ($event.target as HTMLInputElement).value"
                    @focus="openUnitDropdown"
                    @blur="closeUnitDropdown"
                    :disabled="!props.isAdmin"
                    :placeholder="unitDisplayName || '-- Pilih atau cari unit --'"
                    class="w-full rounded-lg border border-blue-300 px-3 py-2 pr-8 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
                    :class="!props.isAdmin ? 'bg-gray-100 cursor-not-allowed text-gray-500' : 'bg-white cursor-text'"
                  />
                  <svg class="pointer-events-none absolute right-2 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
                <div
                  v-if="unitDropdownOpen && props.isAdmin"
                  class="absolute z-50 mt-1 w-full rounded-lg border border-blue-200 bg-white shadow-lg max-h-52 overflow-y-auto"
                >
                  <div
                    v-for="unit in filteredUnitsSearch"
                    :key="unit.kode_unit"
                    @mousedown.prevent="selectUnitFromSearch(unit)"
                    class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
                    :class="unit.kode_unit === selectedUnitCode ? 'bg-blue-100 font-medium text-blue-700' : 'text-gray-700'"
                  >
                    {{ unit.nama_unit }}
                  </div>
                  <div v-if="filteredUnitsSearch.length === 0" class="px-3 py-2 text-xs text-gray-400 italic">
                    Tidak ada hasil
                  </div>
                </div>
              </div>
            </div>

            <!-- Searchable Tim Unit Dropdown -->
            <div v-if="hasTimUnits">
              <label class="block text-xs font-medium text-gray-600 mb-1">Pilih Tim Unit</label>
              <div class="relative">
                <div class="relative">
                  <input
                    :value="timDropdownOpen ? timSearchQuery : timDisplayName"
                    @input="timSearchQuery = ($event.target as HTMLInputElement).value"
                    @focus="openTimDropdown"
                    @blur="closeTimDropdown"
                    :placeholder="timDisplayName || '-- Pilih atau cari tim --'"
                    class="w-full rounded-lg border border-blue-300 px-3 py-2 pr-8 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none bg-white cursor-text"
                  />
                  <svg class="pointer-events-none absolute right-2 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
                <div
                  v-if="timDropdownOpen"
                  class="absolute z-50 mt-1 w-full rounded-lg border border-blue-200 bg-white shadow-lg max-h-52 overflow-y-auto"
                >
                  <div
                    v-for="tim in filteredTimUnitsSearch"
                    :key="tim.nama_tim"
                    @mousedown.prevent="selectTimFromSearch(tim)"
                    class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
                    :class="tim.nama_tim === selectedTimUnitRef ? 'bg-blue-100 font-medium text-blue-700' : 'text-gray-700'"
                  >
                    {{ tim.nama_tim }}
                  </div>
                  <div v-if="filteredTimUnitsSearch.length === 0" class="px-3 py-2 text-xs text-gray-400 italic">
                    Tidak ada hasil
                  </div>
                </div>
              </div>
            </div>

            <div v-if="isUnitSelected" class="flex flex-col items-end justify-end">
              <!-- Back Button untuk unit dengan tim -->
              <button
                v-if="hasTimUnits && selectedTimUnitRef"
                @click="goBackToUnit"
                class="mb-1 flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-r from-indigo-500 to-purple-500 text-white hover:from-indigo-600 hover:to-purple-600 transition-all shadow-sm hover:shadow-md"
                title="Kembali ke daftar approval"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <!-- Back Button untuk unit tanpa tim (admin/kepala setelah klik View) -->
              <button
                v-if="!hasTimUnits && props.canApprove && showIndicatorTableForNoTim"
                @click="goBackToApprovalTable"
                class="mb-1 flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-r from-indigo-500 to-purple-500 text-white hover:from-indigo-600 hover:to-purple-600 transition-all shadow-sm hover:shadow-md"
                title="Kembali ke daftar approval"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <!-- Navigator bulan (untuk canApprove) atau label bulan biasa -->
              <div v-if="props.canApprove && !selectedTimUnitRef" class="flex items-center gap-1">
                <button @click="navigateMonth('prev')" class="p-1 rounded-md hover:bg-blue-100 text-blue-600 transition-colors" title="Bulan sebelumnya">
                  <ChevronLeft :size="18" />
                </button>
                <div class="text-center min-w-[120px]">
                  <div class="text-blue-700 text-sm font-semibold">{{ monthLabelFull(viewMonthKey) }} {{ props.tahun }}</div>
                  <div v-if="!isViewingCurrentMonth" class="text-[10px] text-amber-600 font-medium">View only</div>
                  <div v-else class="text-[10px] text-green-600 font-medium">Bulan berjalan</div>
                </div>
                <button @click="navigateMonth('next')" class="p-1 rounded-md hover:bg-blue-100 text-blue-600 transition-colors" title="Bulan selanjutnya">
                  <ChevronRight :size="18" />
                </button>
              </div>
              <div v-else class="text-blue-700 text-sm">
                Bulan berjalan: <span class="font-medium">{{ monthLabel(currentMonthKey) }}</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Tabel Daftar Tim dengan Status Approval (sebelum pilih tim) - hanya untuk Admin Mutu dan Kepala Unit -->
        <div v-if="props.canApprove && hasTimUnits && !selectedTimUnitRef && isUnitSelected && timApprovalSummary.length > 0" class="mb-4">
          <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-4 py-3 flex items-center justify-between flex-wrap gap-2">
              <h4 class="text-sm font-semibold text-white flex items-center gap-2">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Daftar Approval Indikator per Tim - Bulan {{ monthLabel(viewMonthKey) }}
              </h4>
              <!-- Filter approve status -->
              <div class="flex items-center gap-1.5">
                <button @click="timApprovalStatusFilter = 'semua'"
                  class="px-3 py-1 rounded-full text-xs font-semibold border transition-all"
                  :class="timApprovalStatusFilter === 'semua'
                    ? 'bg-white text-purple-700 border-white shadow-sm'
                    : 'bg-transparent text-white border-white/40 hover:border-white hover:bg-white/10'">
                  Semua
                  <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px]"
                    :class="timApprovalStatusFilter === 'semua' ? 'bg-purple-100 text-purple-600' : 'bg-white/20 text-white'">
                    {{ timApprovalSummary.length }}
                  </span>
                </button>
                <button @click="timApprovalStatusFilter = 'sudah'"
                  class="px-3 py-1 rounded-full text-xs font-semibold border transition-all"
                  :class="timApprovalStatusFilter === 'sudah'
                    ? 'bg-green-500 text-white border-green-500 shadow-sm'
                    : 'bg-transparent text-white border-white/40 hover:border-green-300 hover:bg-green-500/20'">
                  ✓ Sudah Approve
                  <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px]"
                    :class="timApprovalStatusFilter === 'sudah' ? 'bg-green-600 text-white' : 'bg-white/20 text-white'">
                    {{ timApprovalSummary.filter(t => timFilteredTotal(t) > 0 && timFilteredApproved(t) >= timFilteredTotal(t)).length }}
                  </span>
                </button>
                <button @click="timApprovalStatusFilter = 'belum'"
                  class="px-3 py-1 rounded-full text-xs font-semibold border transition-all"
                  :class="timApprovalStatusFilter === 'belum'
                    ? 'bg-amber-400 text-white border-amber-400 shadow-sm'
                    : 'bg-transparent text-white border-white/40 hover:border-amber-300 hover:bg-amber-500/20'">
                  ⏳ Belum Approve
                  <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px]"
                    :class="timApprovalStatusFilter === 'belum' ? 'bg-amber-500 text-white' : 'bg-white/20 text-white'">
                    {{ timApprovalSummary.filter(t => timFilteredNotApproved(t) > 0).length }}
                  </span>
                </button>
              </div>
            </div>
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-12">#</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Tim</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Indikator</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Sudah Approve</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Belum Approve</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Rejected</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="(tim, idx) in timApprovalPaginated" :key="tim.nama_tim" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <button
                      @click="openApproveModal(tim.nama_tim)"
                      class="p-2 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                      title="Detail Validasi Indikator"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                      </svg>
                    </button>
                  </td>
                  <td class="px-4 py-3 text-center text-gray-600">{{ (timApprovalPage - 1) * timApprovalPerPage + idx + 1 }}</td>
                  <td class="px-4 py-3 font-medium text-gray-800">{{ tim.nama_tim }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-semibold text-sm">
                      {{ timFilteredTotal(tim) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-700 font-semibold text-sm">
                      {{ timFilteredApproved(tim) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button
                      v-if="timFilteredNotApproved(tim) > 0"
                      @click="openApproveModal(tim.nama_tim)"
                      class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-full bg-amber-400 text-white font-semibold text-sm hover:bg-amber-500 transition-colors cursor-pointer"
                      title="Klik untuk approve"
                    >
                      {{ timFilteredNotApproved(tim) }}
                    </button>
                    <span v-else class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-400 font-semibold text-sm">
                      0
                    </span>
                  </td>
                  <!-- Rejected Column with hover tooltip -->
                  <td class="px-4 py-3 text-center">
                    <div v-if="tim.rejected > 0" class="relative group inline-block">
                      <span class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-full bg-red-500 text-white font-semibold text-sm cursor-help">
                        {{ tim.rejected }}
                      </span>
                      <!-- Hover tooltip -->
                      <div class="absolute z-50 hidden group-hover:block w-72 p-3 bg-white rounded-lg shadow-xl border border-gray-200 -left-28 top-10">
                        <div class="absolute -top-2 left-1/2 -translate-x-1/2 w-4 h-4 bg-white border-l border-t border-gray-200 transform rotate-45"></div>
                        <p class="text-xs font-semibold text-red-600 mb-2 border-b pb-2">Daftar Indikator Ditolak:</p>
                        <ul class="space-y-2 max-h-48 overflow-y-auto">
                          <li v-for="(item, i) in tim.rejected_list" :key="i" class="text-xs border-b border-gray-100 pb-2 last:border-0">
                            <p class="font-medium text-gray-800 truncate" :title="item.nama">{{ item.nama }}</p>
                            <p class="text-gray-500 text-[10px] italic">{{ item.reason }}</p>
                            <p class="text-gray-400 text-[10px]">{{ item.rejected_at || 'Waktu tidak tercatat' }}</p>
                          </li>
                        </ul>
                      </div>
                    </div>
                    <span v-else class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-400 font-semibold text-sm">
                      0
                    </span>
                  </td>
                </tr>
                <!-- Empty state -->
                <tr v-if="timApprovalPaginated.length === 0">
                  <td colspan="7" class="px-4 py-8 text-center">
                    <div class="flex flex-col items-center gap-2 text-gray-400">
                      <svg class="w-10 h-10 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                      </svg>
                      <p class="text-sm font-medium">
                        <span v-if="timApprovalStatusFilter === 'sudah'">Belum ada tim yang sudah approve semua indikator</span>
                        <span v-else-if="timApprovalStatusFilter === 'belum'">Belum ada data</span>
                        <span v-else>Tidak ada data</span>
                      </p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <!-- Pagination tim approval -->
            <div v-if="timApprovalTotalPages > 1 && timApprovalFiltered.length > 0" class="flex items-center justify-between px-4 py-2 border-t border-gray-100 bg-gray-50">
              <span class="text-xs text-gray-500">
                Tim {{ (timApprovalPage - 1) * timApprovalPerPage + 1 }}–{{ Math.min(timApprovalPage * timApprovalPerPage, timApprovalFiltered.length) }} dari {{ timApprovalFiltered.length }}
              </span>
              <div class="flex items-center gap-1">
                <button @click="timApprovalPage--" :disabled="timApprovalPage === 1"
                  class="px-2 py-1 text-xs rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-100 transition-colors">‹ Prev</button>
                <button v-for="p in timApprovalTotalPages" :key="p" @click="timApprovalPage = p"
                  class="w-7 h-7 text-xs rounded border transition-colors"
                  :class="p === timApprovalPage ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 hover:bg-gray-100'">
                  {{ p }}
                </button>
                <button @click="timApprovalPage++" :disabled="timApprovalPage === timApprovalTotalPages"
                  class="px-2 py-1 text-xs rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-100 transition-colors">Next ›</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabel Daftar Approval untuk Unit TANPA Tim - hanya untuk Admin Mutu dan Kepala Unit -->
        <div v-if="props.canApprove && !hasTimUnits && isUnitSelected && unitApprovalSummary" class="mb-4">
          <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-4 py-3">
              <h4 class="text-sm font-semibold text-white flex items-center gap-2">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Daftar Approval Indikator - Bulan {{ monthLabel(viewMonthKey) }}
              </h4>
            </div>
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Unit</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Indikator</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Sudah Approve</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Belum Approve</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Rejected</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <button
                      @click="openApproveModal('all')"
                      class="p-2 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                      title="Detail Validasi Indikator"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                      </svg>
                    </button>
                  </td>
                  <td class="px-4 py-3 font-medium text-gray-800">{{ unitApprovalSummary.nama_unit }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-semibold text-sm">
                      {{ unitApprovalSummary.total_indikator }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-700 font-semibold text-sm">
                      {{ unitApprovalSummary.approved }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button
                      v-if="unitApprovalSummary.not_approved > 0"
                      @click="openApproveModal('all')"
                      class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-full bg-amber-400 text-white font-semibold text-sm hover:bg-amber-500 transition-colors cursor-pointer"
                      title="Klik untuk approve"
                    >
                      {{ unitApprovalSummary.not_approved }}
                    </button>
                    <span v-else class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-400 font-semibold text-sm">
                      0
                    </span>
                  </td>
                  <!-- Rejected Column with hover tooltip -->
                  <td class="px-4 py-3 text-center">
                    <div v-if="unitApprovalSummary.rejected > 0" class="relative group inline-block">
                      <span class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-full bg-red-500 text-white font-semibold text-sm cursor-help">
                        {{ unitApprovalSummary.rejected }}
                      </span>
                      <!-- Hover tooltip -->
                      <div class="absolute z-50 hidden group-hover:block w-72 p-3 bg-white rounded-lg shadow-xl border border-gray-200 -left-28 top-10">
                        <div class="absolute -top-2 left-1/2 -translate-x-1/2 w-4 h-4 bg-white border-l border-t border-gray-200 transform rotate-45"></div>
                        <p class="text-xs font-semibold text-red-600 mb-2 border-b pb-2">Daftar Indikator Ditolak:</p>
                        <ul class="space-y-2 max-h-48 overflow-y-auto">
                          <li v-for="(item, i) in unitApprovalSummary.rejected_list" :key="i" class="text-xs border-b border-gray-100 pb-2 last:border-0">
                            <p class="font-medium text-gray-800 truncate" :title="item.nama">{{ item.nama }}</p>
                            <p class="text-gray-500 text-[10px] italic">{{ item.reason }}</p>
                            <p class="text-gray-400 text-[10px]">{{ item.rejected_at || 'Waktu tidak tercatat' }}</p>
                          </li>
                        </ul>
                      </div>
                    </div>
                    <span v-else class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-400 font-semibold text-sm">
                      0
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Catatan Revisi - PINDAH KE BAWAH DROPDOWN UNIT -->
        <!-- <div v-if="isUnitSelected && komentarList.length > 0" class="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-4">
          <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-orange-800 text-sm flex items-center gap-2">
              <MessageSquare :size="16" />
              Catatan Revisi dari Admin ({{ unreadKomentarCount }} belum dibaca)
            </h4>
          </div>

          <div class="space-y-2 max-h-64 overflow-y-auto">
            <div 
              v-for="komentar in komentarList" 
              :key="komentar.id"
              class="rounded-lg border p-3 transition-colors"
              :class="!komentar.dibaca ? 'bg-white border-orange-300' : 'bg-gray-50 border-gray-200'"
            >
              <div class="flex items-start gap-3">
                <input 
                  type="checkbox" 
                  :checked="komentar.dibaca"
                  @change="markAsDibaca(komentar.id)"
                  class="mt-1 h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                />
                <div class="flex-1">
                  <p class="text-xs font-semibold text-gray-800 mb-1">{{ komentar.indikator_nama }}</p>
                  <p class="text-xs text-gray-600 mb-1">{{ komentar.komentar }}</p>
                  <p class="text-xs text-gray-400">{{ komentar.tanggal }}</p>
                </div>
              </div>
            </div>
          </div>
        </div> -->

        <!-- Jenis Filter Badges -->
        <div v-if="isUnitSelected && data.length > 0 && (!hasTimUnits || selectedTimUnitRef) && availableJenis.length > 1" class="mb-2 flex items-center gap-2 flex-wrap">
          <span class="text-xs text-gray-500 font-medium">Filter:</span>
          <button
            @click="filterJenis = ''"
            class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors border"
            :class="filterJenis === '' ? 'bg-gray-700 text-white border-gray-700' : 'bg-gray-100 text-gray-600 border-gray-200 hover:bg-gray-200'"
          >Semua</button>
          <button
            v-for="jenis in availableJenis"
            :key="jenis"
            @click="filterJenis = filterJenis === jenis ? '' : jenis"
            class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors border"
            :class="filterJenis === jenis
              ? (jenis === 'INM' ? 'bg-blue-600 text-white border-blue-600'
                : jenis === 'SPM' ? 'bg-green-600 text-white border-green-600'
                : jenis === 'PRIORITAS' ? 'bg-purple-600 text-white border-purple-600'
                : jenis === 'IMUT_RS' ? 'bg-orange-500 text-white border-orange-500'
                : 'bg-teal-600 text-white border-teal-600')
              : (jenis === 'INM' ? 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100'
                : jenis === 'SPM' ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'
                : jenis === 'PRIORITAS' ? 'bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100'
                : jenis === 'IMUT_RS' ? 'bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100'
                : 'bg-teal-50 text-teal-700 border-teal-200 hover:bg-teal-100')"
          >{{ jenisLabel(jenis) }}</button>
        </div>

        <!-- Quarter Navigation & Search - Hanya tampil jika tim sudah dipilih (untuk unit yg punya tim) -->
        <div v-if="isUnitSelected && data.length > 0 && (!hasTimUnits || selectedTimUnitRef)" class="mb-4 flex items-center justify-between gap-3">
          <!-- Search Bar -->
          <div class="flex-1 relative max-w-md">
            <input 
              v-model="searchQuery"
              type="text"
              placeholder="Cari indikator atau standar..."
              class="w-full rounded-lg border border-gray-300 px-4 py-2 pl-10 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            />
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Quarter Navigation -->
          <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
            <div class="flex items-center gap-2 text-xs text-gray-600">
              <button
                @click="navigateQuarter('prev')"
                class="flex items-center gap-1 rounded px-2 py-1 hover:bg-gray-200 transition-colors"
                title="TW Sebelumnya"
              >
                <ChevronLeft :size="14" />
              </button>

              <span class="px-2">Triwulan {{ quarter }} Tahun {{ tahun }}</span>

              <button
                @click="navigateQuarter('next')"
                class="flex items-center gap-1 rounded px-2 py-1 hover:bg-gray-200 transition-colors"
                title="TW Berikutnya"
              >
                <ChevronRight :size="14" />
              </button>
            </div>
          </div>
        </div>

        <!-- Table - Hanya tampil jika tim sudah dipilih (untuk unit yg punya tim) -->
        <div v-if="paginatedIndicators.length > 0 && isUnitSelected && (!hasTimUnits || selectedTimUnitRef)" class="overflow-auto rounded-lg border border-gray-200 dark:border-gray-700 flex-1" style="max-height: calc(100vh - 480px); min-height: 300px;">
          <table class="min-w-full w-full text-[13px] border-collapse">
            <thead class="sticky top-0 z-10 bg-gray-50">
              <tr class="bg-gray-50 text-gray-700">
                <th class="border px-2 py-2 w-12 text-center bg-gray-50">NO</th>
                <th class="border px-2 py-2 w-[300px] text-left bg-gray-50">INDIKATOR</th>
                <th class="border px-2 py-2 w-32 text-left bg-gray-50">STANDAR</th>
                <th class="border px-2 py-2 w-[300px] text-left bg-gray-50">NUMERATOR/DENOMINATOR</th>
                <th v-for="month in displayMonths" :key="month" class="border px-2 py-2 w-24 text-center bg-gray-50">
                  {{ monthLabel(month) }}
                </th>
                <th class="border px-2 py-2 w-24 text-center bg-gray-50">RATA2</th>
                <th class="border px-2 py-2 w-[260px] text-left bg-gray-50">ANALISIS / RTL</th>
              </tr>
            </thead>

            <tbody>
              <template v-for="(ind, idx) in paginatedIndicators" :key="ind._virtualKey ?? ind.id">
                <!-- Group header row when jenis changes -->
                <tr v-if="idx === 0 || displayJenis(paginatedIndicators[idx - 1]) !== displayJenis(ind)">
                  <td colspan="99" class="border-x-0 px-3 py-1.5"
                    :class="{
                      'bg-blue-50 border-y border-blue-200': displayJenis(ind) === 'INM',
                      'bg-green-50 border-y border-green-200': displayJenis(ind) === 'SPM',
                      'bg-purple-50 border-y border-purple-200': displayJenis(ind) === 'PRIORITAS',
                      'bg-orange-50 border-y border-orange-200': displayJenis(ind) === 'IMUT_RS',
                      'bg-teal-50 border-y border-teal-200': displayJenis(ind) === 'IMUT_UNIT',
                      'bg-gray-50 border-y border-gray-200': !['INM','SPM','PRIORITAS','IMUT_RS','IMUT_UNIT'].includes(displayJenis(ind)),
                    }">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold"
                      :class="{
                        'text-blue-700': displayJenis(ind) === 'INM',
                        'text-green-700': displayJenis(ind) === 'SPM',
                        'text-purple-700': displayJenis(ind) === 'PRIORITAS',
                        'text-orange-700': displayJenis(ind) === 'IMUT_RS',
                        'text-teal-700': displayJenis(ind) === 'IMUT_UNIT',
                        'text-gray-600': !['INM','SPM','PRIORITAS','IMUT_RS','IMUT_UNIT'].includes(displayJenis(ind)),
                      }">
                      <span class="w-2 h-2 rounded-full inline-block"
                        :class="{
                          'bg-blue-500': displayJenis(ind) === 'INM',
                          'bg-green-500': displayJenis(ind) === 'SPM',
                          'bg-purple-500': displayJenis(ind) === 'PRIORITAS',
                          'bg-orange-500': displayJenis(ind) === 'IMUT_RS',
                          'bg-teal-500': displayJenis(ind) === 'IMUT_UNIT',
                          'bg-gray-400': !['INM','SPM','PRIORITAS','IMUT_RS','IMUT_UNIT'].includes(displayJenis(ind)),
                        }"></span>
                      {{ jenisLabel(displayJenis(ind)) }}
                    </span>
                  </td>
                </tr>
                <!-- ROW N -->
                <tr :class="getIndicatorRowClass(ind)">
                  <td class="border px-2 py-2 align-top text-center" :rowspan="4">{{ (currentPage - 1) * itemsPerPage + idx + 1 }}</td>
                  <td class="border px-2 py-2 align-top" :rowspan="4">
  <div class="flex items-start gap-2">
    <span class="flex-1">{{ ind.indikator }}</span>
    <!-- Rejected History Notification -->
    <div v-if="hasRejectedHistory(ind)" class="relative group flex-shrink-0">
      <button
        class="rounded-full p-1 transition-colors bg-red-100 text-red-600"
        title="Ada riwayat isian yang direject"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </button>
      <!-- Tooltip on hover -->
      <div class="absolute left-0 top-full mt-1 z-50 hidden group-hover:block bg-white rounded-lg shadow-lg border border-red-200 p-3 w-72 max-h-80 overflow-y-auto">
        <p class="text-xs font-semibold text-red-700 mb-2">Riwayat Isian Direject:</p>
        <!-- Show from JSON rejection history -->
        <template v-if="ind.rejectionHistory && ind.rejectionHistory.length > 0">
          <div v-for="month in displayMonths" :key="month" class="text-xs">
            <template v-if="getMonthRejectionHistory(ind, month).length > 0">
              <div class="font-medium text-gray-700 mt-1">{{ monthLabel(month) }}:</div>
              <div v-for="(entry, idx) in getMonthRejectionHistory(ind, month)" :key="idx" class="border-b border-gray-100 py-1 pl-2">
                <div class="text-gray-600">N: {{ entry.n ?? '-' }} | D: {{ entry.d ?? '-' }}</div>
                <div v-if="entry.reason" class="text-red-600 italic">"{{ entry.reason }}"</div>
                <div class="text-gray-400">{{ entry.at }}</div>
              </div>
            </template>
          </div>
        </template>
        <!-- Fallback: show from single columns (backward compat) -->
        <template v-else>
          <div v-for="month in displayMonths" :key="month" class="text-xs">
            <template v-if="ind.a?.[month]?.rejected_n !== null || ind.a?.[month]?.rejected_d !== null || ind.a?.[month]?.rejected">
              <div class="border-b border-gray-100 py-1">
                <span class="font-medium text-gray-700">{{ monthLabel(month) }}:</span>
                <div class="text-gray-600 pl-2">
                  <div v-if="ind.a[month].rejected_n !== null || ind.a[month].rejected_d !== null">
                    N: {{ ind.a[month].rejected_n ?? '-' }} | D: {{ ind.a[month].rejected_d ?? '-' }}
                  </div>
                  <div v-else class="text-gray-400 italic">Data history tidak tersedia</div>
                  <div v-if="ind.a[month].reject_reason" class="text-red-600 italic">"{{ ind.a[month].reject_reason }}"</div>
                  <div class="text-gray-400">{{ ind.a[month].rejected_at || 'Waktu tidak tercatat' }}</div>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>
    </div>
    <button
      v-if="komentarData[ind.id] && !komentarData[ind.id].dibaca"
      @click="openKomentarPopup(ind)"
      class="flex-shrink-0 rounded-full p-1 transition-colors bg-orange-100 text-orange-600 animate-pulse"
      title="Ada catatan revisi dari admin"
    >
      <MessageSquare :size="16" />
    </button>
    <!-- Badge Sudah Direvisi (hanya tampil setelah direvisi, tidak ada tombol di sini) -->
    <span v-if="!isKepalaUnit && isRevised(ind, currentMonthKey) && isViewingCurrentMonth" class="flex-shrink-0 ml-1 px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-300">
      ✓ Sudah Direvisi
    </span>
  </div>
</td>
                  <td class="border px-2 py-2 align-top" :rowspan="4">{{ ind.standar }}</td>

                  <td class="border px-2 py-2">
                    <div class="flex items-start gap-2">
                      <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-gray-100 text-gray-700 text-xs font-semibold">N</span>
                      <span class="block">{{ ind.numeratorDesc }}</span>
                    </div>
                  </td>

                  <td v-for="month in displayMonths" :key="month" :class="cellClassWithValidation(ind, month)" @click="openCell(ind, month, 'N')">
                    {{ displayVal(ind, month, 'N') }}
                  </td>

                  <td class="border px-2 py-2 text-center" :rowspan="2">
                    {{ fmtPctCapped(ind, rata2Quarter(ind)) }}
                    <span v-if="isOverCap(ind, rata2Quarter(ind))" class="text-[10px] text-gray-400 block">({{ fmtPct(ind, rata2Quarter(ind)) }})</span>
                  </td>
                  <td class="border px-2 py-2" :rowspan="2">
                    <div class="grid gap-2">
                      <textarea
                        v-model="ind.analisisRtl[currentMonthKey].analisis"
                        @blur="saveAnalisis(ind)"
                        :disabled="!canEditAnalisis(ind)"
                        :class="{'opacity-50 cursor-not-allowed': !canEditAnalisis(ind)}"
                        rows="2"
                        class="w-full border rounded px-2 py-1 text-sm"
                        placeholder="Analisis..."
                      ></textarea>
                      <textarea
                        v-model="ind.analisisRtl[currentMonthKey].rtl"
                        @blur="saveAnalisis(ind)"
                        :disabled="!canEditAnalisis(ind)"
                        :class="{'opacity-50 cursor-not-allowed': !canEditAnalisis(ind)}"
                        rows="2"
                        class="w-full border rounded px-2 py-1 text-sm"
                        placeholder="RTL..."
                      ></textarea>
                    </div>
                  </td>
                </tr>

                <!-- ROW D -->
                <tr :class="getIndicatorRowClass(ind)">
                  <td class="border px-2 py-2">
                    <div class="flex items-start gap-2">
                      <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-gray-100 text-gray-700 text-xs font-semibold">D</span>
                      <span class="block">{{ ind.denominatorDesc }}</span>
                    </div>
                  </td>

                  <td v-for="month in displayMonths" :key="month" :class="cellClassWithValidation(ind, month)" @click="openCell(ind, month, 'D')">
                    {{ displayVal(ind, month, 'D') }}
                  </td>
                </tr>

                <!-- LAMPIRAN -->
                <tr :class="getIndicatorRowClass(ind)">
                  <td class="border px-2 py-2 font-semibold">LAMPIRAN</td>

                  <td v-for="month in displayMonths" :key="month" class="border px-2 py-2">
                    <!-- Jika BELUM ada file: Tampilkan icon Upload -->
                    <div v-if="!ind.att[month]" class="flex items-center justify-center gap-1">
                      <input type="file" :id="`f-${ind.id}-${month}`" class="hidden" accept=".pdf,.xlsx,.xls"
                             :disabled="!isWindowOpen(month) || isValidated(ind, month) || (isApproved(ind, month) && !hasKomentarAdmin(ind, month))" @change="onUpload(ind, month, $event)" />
                      <label :for="`f-${ind.id}-${month}`"
                             class="p-2 rounded-md border cursor-pointer hover:bg-gray-50 border-gray-300 transition-colors"
                             :class="(isWindowOpen(month) && !isValidated(ind, month) && (!isApproved(ind, month) || hasKomentarAdmin(ind, month)))?'opacity-100':'opacity-50 cursor-not-allowed'"
                             :title="(isApproved(ind, month) && !hasKomentarAdmin(ind, month)) ? 'Sudah di-approve, tidak bisa upload' : 'Upload lampiran'">
                        <Upload :size="18" class="text-gray-600" />
                      </label>
                    </div>

                    <!-- Jika SUDAH ada file: Tampilkan icon Lihat dan Upload Ulang -->
                    <div v-else class="flex items-center justify-center gap-1">
                      <button @click="openLampiranModal(ind, month)"
                              class="p-2 rounded-md border border-blue-500 bg-blue-50 hover:bg-blue-100 transition-colors"
                              :title="'Lihat lampiran'">
                        <Eye :size="18" class="text-blue-700" />
                      </button>
                      <!-- Upload ulang: hanya jika belum validated DAN (belum approved ATAU ada komentar admin) -->
                      <div v-if="isWindowOpen(month) && !isValidated(ind, month) && (!isApproved(ind, month) || hasKomentarAdmin(ind, month))">
                        <input type="file" :id="`f-ulang-${ind.id}-${month}`" class="hidden" accept=".pdf,.xlsx,.xls"
                               @change="onUpload(ind, month, $event)" />
                        <label :for="`f-ulang-${ind.id}-${month}`"
                               class="p-2 rounded-md border border-green-500 bg-green-50 cursor-pointer hover:bg-green-100 transition-colors inline-flex"
                               :title="'Upload ulang'">
                          <Upload :size="18" class="text-green-700" />
                        </label>
                      </div>
                    </div>
                  </td>
                </tr>

                <!-- HASIL -->
                <tr :class="getIndicatorRowClass(ind)">
                  <td class="border px-2 py-2 font-semibold">HASIL</td>

                  <td v-for="month in displayMonths" :key="month" class="border px-2 py-2 text-center">
                    {{ fmtPctCapped(ind, hasil(ind, ind.m[month])) }}
                    <span v-if="isOverCap(ind, hasil(ind, ind.m[month]))" class="text-[10px] text-gray-400 block">({{ fmtPct(ind, hasil(ind, ind.m[month])) }})</span>
                  </td>

                  <td class="border px-2 py-2 text-center font-semibold">
                    {{ fmtPctCapped(ind, rata2Quarter(ind)) }}
                    <span v-if="isOverCap(ind, rata2Quarter(ind))" class="text-[10px] text-gray-400 block">({{ fmtPct(ind, rata2Quarter(ind)) }})</span>
                  </td>
                  <td class="border px-2 py-2"></td>
                </tr>

              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1 && isUnitSelected && (!hasTimUnits || selectedTimUnitRef)" class="mt-4 flex items-center justify-between">
          <p class="text-sm text-gray-600">
            Menampilkan {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredIndicators.length) }} dari {{ filteredIndicators.length }} indikator
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

        <!-- Empty State - hidden for admin/kepala when viewing approval table -->
        <div v-if="!isUnitSelected || (data.length === 0 && isUnitSelected && !props.canApprove)" class="flex flex-col items-center justify-center py-16 text-gray-400">
          <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="text-sm font-medium">Tidak ada data indikator</p>
          <p class="text-xs mt-1">
            <span v-if="!isUnitSelected">Silakan pilih Bagian/Unit terlebih dahulu</span>
            <span v-else-if="hasTimUnits && !selectedTimUnit">Pilih Tim Unit terlebih dahulu</span>
            <span v-else>Belum ada indikator untuk unit ini</span>
          </p>
        </div>

        <!-- Empty State for Search -->
        <div v-if="isUnitSelected && data.length > 0 && filteredIndicators.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-400">
          <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <p class="text-sm font-medium">Tidak ada hasil pencarian</p>
          <p class="text-xs mt-1">Tidak ditemukan indikator dengan kata kunci "{{ searchQuery }}"</p>
        </div>
      </div>
    </div>

    <!-- Modal input nilai -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-lg dark:bg-gray-900">
          <h4 class="mb-4 text-lg font-semibold">{{ modalTitle }}</h4>
          <div class="space-y-2 text-sm mb-3">
            <div><span class="text-gray-500">Bulan capaian:</span> <strong>{{ monthLabel(infoBulan) }}</strong></div>
            <div><span class="text-gray-500">Indikator:</span> <strong>{{ infoIndikator }}</strong></div>
            <template v-if="targetField === 'N'">
              <div><span class="text-gray-500">Numerator:</span> {{ infoNumerator }}</div>
            </template>
            <template v-else>
              <div><span class="text-gray-500">Denominator:</span> {{ infoDenominator }}</div>
            </template>
          </div>
          <label class="block text-sm">
            <span class="mb-1 block">Isi nilai ({{ targetField }})</span>
            <input type="number" step="1" min="0" v-model.number="inputNilai" class="w-full rounded-lg border px-3 py-2" placeholder="Masukkan nilai" />
          </label>
          <div class="mt-5 flex justify-end gap-2">
            <button class="rounded-lg border px-4 py-2" @click="showModal=false">Batal</button>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" @click="saveCell">Simpan</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Popup Peringatan -->
    <Teleport to="body">
      <div v-if="warn.show" class="fixed inset-0 z-[9999] grid place-items-center bg-black/30 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
          <div class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full border-2 border-amber-300 text-amber-400">
            <span class="text-3xl">!</span>
          </div>
          <h4 class="mb-2 text-xl font-bold">Peringatan</h4>
          <p class="mb-4 text-gray-600 text-sm">{{ warn.message }}</p>
          <button @click="closeWarn" class="mx-auto rounded-lg bg-indigo-500 px-5 py-2 text-white hover:bg-indigo-600">
            OK
          </button>
        </div>
      </div>
    </Teleport>

    <!-- Modal Komentar Popup -->
<Teleport to="body">
  <div v-if="showKomentarPopup" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showKomentarPopup = false">
    <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <h4 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <MessageSquare :size="20" class="text-orange-600" />
          Catatan Revisi dari Admin
        </h4>
        <button @click="showKomentarPopup = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
      </div>
      
      <div v-if="selectedKomentar" class="space-y-4">
        <div class="rounded-lg bg-blue-50 p-3">
          <p class="text-xs text-gray-600 mb-1">Indikator:</p>
          <p class="text-sm font-medium text-gray-800">{{ selectedKomentar.indikator }}</p>
        </div>
        
        <div class="rounded-lg bg-orange-50 border border-orange-200 p-4">
          <p class="text-xs font-semibold text-orange-700 mb-2">Catatan:</p>
          <p class="text-sm text-gray-800">{{ selectedKomentar.komentar }}</p>
        </div>
        
        <div v-if="!selectedKomentar.revised" class="rounded-lg bg-yellow-50 border border-yellow-200 p-3 flex items-start gap-2">
          <span class="text-yellow-600 text-lg">⚠️</span>
          <p class="text-xs text-yellow-700">
            Silakan perbaiki data sesuai catatan di atas, kemudian tandai sebagai sudah direvisi.
          </p>
        </div>

        <div class="flex items-center justify-between gap-3">
          <button
            @click="showKomentarPopup = false"
            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          >
            Tutup
          </button>
          <button
            v-if="!selectedKomentar.revised"
            @click="markKomentarAsDibaca"
            class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700"
          >
            ✓ Tandai Sudah Direvisi
          </button>
          <div v-else class="flex-1 text-center text-sm text-green-600 font-medium">
            ✓ Sudah Direvisi
          </div>
        </div>
      </div>
    </div>
  </div>
</Teleport>

<!-- Modal Lampiran: Lihat & Upload Ulang -->
<Teleport to="body">
  <div v-if="showLampiranModal" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="closeLampiranModal">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-auto" @click.stop>
      <!-- Header -->
      <div class="flex items-center justify-between border-b p-4">
        <h3 class="text-lg font-semibold text-gray-800">Lampiran - {{ selectedLampiran.month?.toUpperCase() }}</h3>
        <button @click="closeLampiranModal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
      </div>

      <!-- Content -->
      <div class="p-6 space-y-4">
        <!-- Preview File -->
        <div class="border rounded-lg p-4 bg-gray-50">
          <p class="text-sm font-medium text-gray-700 mb-2">File Lampiran:</p>
          <p class="text-sm text-gray-600 mb-3 break-all">{{ selectedLampiran.ind?.att[selectedLampiran.month!] }}</p>

          <!-- Preview (jika PDF atau Excel) -->
          <div v-if="selectedLampiran.fileUrl" class="mt-3">
            <!-- PDF: Tampilkan di iframe -->
            <iframe v-if="selectedLampiran.fileUrl.endsWith('.pdf')"
                    :src="selectedLampiran.fileUrl"
                    class="w-full h-96 border rounded"></iframe>

            <!-- Excel: Tampilkan icon dan info (tidak bisa preview) -->
            <div v-else-if="selectedLampiran.fileUrl.endsWith('.xlsx') || selectedLampiran.fileUrl.endsWith('.xls')"
                 class="border rounded p-8 bg-green-50 text-center">
              <svg class="w-16 h-16 mx-auto text-green-600 mb-3" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/>
                <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
              </svg>
              <p class="text-sm font-medium text-gray-700">File Excel</p>
              <p class="text-xs text-gray-500 mt-1">File berhasil diupload</p>
            </div>
          </div>
        </div>

        <!-- Info jika sudah divalidasi -->
        <div v-if="selectedLampiran.ind && selectedLampiran.month && isValidated(selectedLampiran.ind, selectedLampiran.month)"
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

<!-- Modal Approve Indikator per Tim -->
<Teleport to="body">
  <div v-if="showApproveModal" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="closeApproveModal">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col" @click.stop>
      <!-- Header -->
      <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <!-- Back button ketika viewing detail -->
          <button
            v-if="viewingIndicatorDetail"
            @click="backToIndicatorList"
            class="p-1.5 rounded-lg bg-white/20 text-white hover:bg-white/30 transition-colors"
            title="Kembali ke daftar"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <div>
            <h3 class="text-lg font-semibold text-white">
              {{ viewingIndicatorDetail ? 'Detail Indikator' : (selectedTimForApprove === 'all' ? 'Daftar Indikator - Semua Indikator' : 'Daftar Indikator - ' + selectedTimForApprove) }}
            </h3>
            <p class="text-purple-200 text-sm">
              {{ viewingIndicatorDetail ? viewingIndicatorDetail.indikator : (isViewingCurrentMonth ? 'Pilih indikator yang akan di-approve untuk bulan ' : 'Data bulan ') + monthLabel(viewMonthKey) + (!isViewingCurrentMonth ? ' (view only)' : '') }}
            </p>
          </div>
        </div>
        <button @click="closeApproveModal" class="text-white/80 hover:text-white text-2xl">&times;</button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-auto p-4">
        <!-- Loading -->
        <div v-if="loadingTimIndikator && !viewingIndicatorDetail" class="flex items-center justify-center py-12">
          <svg class="animate-spin h-8 w-8 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <!-- Loading Detail View -->
        <div v-else-if="viewingIndicatorDetail && loadingIndicatorDetail" class="flex items-center justify-center py-12">
          <svg class="animate-spin h-8 w-8 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <!-- Detail View Indikator - Hanya bulan yang dilihat -->
        <div v-else-if="viewingIndicatorDetail && viewingIndicatorFullData">
          <div class="rounded-lg border border-gray-200 overflow-hidden">
            <table class="min-w-full text-[13px] border-collapse">
              <thead class="bg-gray-50">
                <tr class="bg-gray-50 text-gray-700">
                  <th class="border px-3 py-2 w-[160px] text-left bg-gray-50">DATA</th>
                  <th class="border px-3 py-2 text-left bg-gray-50">KETERANGAN</th>
                  <th class="border px-3 py-2 w-[120px] text-center bg-gray-50">{{ monthLabel(viewMonthKey) }}</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="border px-3 py-2 font-semibold text-gray-700">Indikator</td>
                  <td class="border px-3 py-2 text-sm" colspan="2">{{ viewingIndicatorFullData.indikator }}</td>
                </tr>
                <tr>
                  <td class="border px-3 py-2 font-semibold text-gray-700">Standar</td>
                  <td class="border px-3 py-2 text-sm" colspan="2">{{ viewingIndicatorFullData.standar }}</td>
                </tr>
                <tr class="bg-blue-50">
                  <td class="border px-3 py-2 font-semibold text-gray-700">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-gray-200 text-gray-700 text-xs font-bold mr-1">N</span>
                    Numerator
                  </td>
                  <td class="border px-3 py-2 text-xs text-gray-600">{{ viewingIndicatorFullData.numeratorDesc }}</td>
                  <td class="border px-3 py-2 text-center text-lg font-bold text-blue-700">
                    {{ viewingIndicatorFullData.m?.[viewMonthKey]?.N ?? '-' }}
                    <span v-if="viewingIndicatorFullData.m?.[viewMonthKey]?.N_prev != null" class="text-xs text-gray-400 font-normal block">(sebelumnya: {{ viewingIndicatorFullData.m?.[viewMonthKey]?.N_prev }})</span>
                  </td>
                </tr>
                <tr class="bg-blue-50">
                  <td class="border px-3 py-2 font-semibold text-gray-700">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-gray-200 text-gray-700 text-xs font-bold mr-1">D</span>
                    Denominator
                  </td>
                  <td class="border px-3 py-2 text-xs text-gray-600">{{ viewingIndicatorFullData.denominatorDesc }}</td>
                  <td class="border px-3 py-2 text-center text-lg font-bold text-blue-700">
                    {{ viewingIndicatorFullData.m?.[viewMonthKey]?.D ?? '-' }}
                    <span v-if="viewingIndicatorFullData.m?.[viewMonthKey]?.D_prev != null" class="text-xs text-gray-400 font-normal block">(sebelumnya: {{ viewingIndicatorFullData.m?.[viewMonthKey]?.D_prev }})</span>
                  </td>
                </tr>
                <tr>
                  <td class="border px-3 py-2 font-semibold text-gray-700">Hasil</td>
                  <td class="border px-3 py-2 text-sm text-gray-500">{{ viewingIndicatorFullData.satuan || 'persen' }}</td>
                  <td class="border px-3 py-2 text-center text-lg font-bold text-green-700">
                    {{ fmtPctCapped(viewingIndicatorFullData, hasil(viewingIndicatorFullData, viewingIndicatorFullData.m?.[viewMonthKey])) || '-' }}
                    <span v-if="isOverCap(viewingIndicatorFullData, hasil(viewingIndicatorFullData, viewingIndicatorFullData.m?.[viewMonthKey]))" class="text-[10px] text-gray-400 font-normal block">({{ fmtPct(viewingIndicatorFullData, hasil(viewingIndicatorFullData, viewingIndicatorFullData.m?.[viewMonthKey])) }})</span>
                  </td>
                </tr>
                <tr>
                  <td class="border px-3 py-2 font-semibold text-gray-700">Lampiran</td>
                  <td class="border px-3 py-2" colspan="2">
                    <div v-if="viewingIndicatorFullData.att?.[viewMonthKey]" class="flex items-center gap-2">
                      <a :href="`/capaian-indikator/lampiran/${viewingIndicatorFullData.att[viewMonthKey]}`" target="_blank" class="text-blue-600 hover:underline text-sm flex items-center gap-1">
                        <Eye :size="14" /> Lihat Lampiran
                      </a>
                    </div>
                    <span v-else class="text-gray-400 text-sm">Belum ada lampiran</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Analisis / RTL -->
          <div class="mt-4 rounded-lg border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-4 py-2 border-b flex items-center justify-between">
              <h4 class="text-sm font-semibold text-gray-700">Analisis & RTL - {{ monthLabel(viewMonthKey) }}</h4>
              <span v-if="savingDetailAnalisis" class="text-xs text-purple-600 animate-pulse">Menyimpan...</span>
            </div>
            <div class="p-4 grid gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Analisis</label>
                <textarea
                  v-model="detailAnalisis"
                  @blur="saveDetailAnalisis"
                  rows="3"
                  class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                  placeholder="Tulis analisis..."
                  :disabled="!isViewingCurrentMonth || !!viewingIndicatorDetail?.approved"
                  :class="{ 'bg-gray-100 cursor-not-allowed': !isViewingCurrentMonth || !!viewingIndicatorDetail?.approved }"
                ></textarea>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Rencana Tindak Lanjut (RTL)</label>
                <textarea
                  v-model="detailRtl"
                  @blur="saveDetailAnalisis"
                  rows="3"
                  class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                  placeholder="Tulis RTL..."
                  :disabled="!isViewingCurrentMonth || !!viewingIndicatorDetail?.approved"
                  :class="{ 'bg-gray-100 cursor-not-allowed': !isViewingCurrentMonth || !!viewingIndicatorDetail?.approved }"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Catatan Admin Mutu (read-only) -->
          <div v-if="viewingIndicatorFullData.komentar?.[viewMonthKey]?.komentar" class="mt-4 p-4 rounded-lg bg-orange-50 border border-orange-200">
            <div class="flex items-center gap-2 mb-2">
              <svg class="w-4 h-4 text-orange-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/>
              </svg>
              <h4 class="text-sm font-semibold text-orange-800">Catatan dari Admin Mutu</h4>
            </div>
            <p class="text-sm text-orange-700 whitespace-pre-wrap">{{ viewingIndicatorFullData.komentar[viewMonthKey].komentar }}</p>
          </div>

          <!-- Rekomendasi Mutu Panel -->
          <div v-if="viewingIndicatorDetail?.recommendation" class="mt-4 p-4 rounded-lg border"
            :class="{
              'bg-green-50 border-green-200': viewingIndicatorDetail.recommendation.color === 'green',
              'bg-red-50   border-red-200':   viewingIndicatorDetail.recommendation.color === 'red',
              'bg-gray-50  border-gray-200':  viewingIndicatorDetail.recommendation.color === 'gray',
            }">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0"
                  :class="{
                    'text-green-600': viewingIndicatorDetail.recommendation.color === 'green',
                    'text-red-600':   viewingIndicatorDetail.recommendation.color === 'red',
                    'text-gray-400':  viewingIndicatorDetail.recommendation.color === 'gray',
                  }"
                  fill="currentColor" viewBox="0 0 20 20">
                  <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.001z"/>
                </svg>
                <h4 class="text-sm font-semibold"
                  :class="{
                    'text-green-800': viewingIndicatorDetail.recommendation.color === 'green',
                    'text-red-800':   viewingIndicatorDetail.recommendation.color === 'red',
                    'text-gray-700':  viewingIndicatorDetail.recommendation.color === 'gray',
                  }">
                  Rekomendasi Mutu
                </h4>
              </div>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                :class="{
                  'bg-green-100 text-green-700': viewingIndicatorDetail.recommendation.color === 'green',
                  'bg-red-100   text-red-700':   viewingIndicatorDetail.recommendation.color === 'red',
                  'bg-gray-100  text-gray-500':  viewingIndicatorDetail.recommendation.color === 'gray',
                }">
                {{ viewingIndicatorDetail.recommendation.status }}
              </span>
            </div>
            <div v-if="viewingIndicatorDetail.recommendation.achievement !== null"
              class="mb-2 text-xs font-medium"
              :class="{
                'text-green-700': viewingIndicatorDetail.recommendation.color === 'green',
                'text-red-700':   viewingIndicatorDetail.recommendation.color === 'red',
                'text-gray-600':  viewingIndicatorDetail.recommendation.color === 'gray',
              }">
              Capaian: <strong>{{ viewingIndicatorDetail.recommendation.achievement }}%</strong>
              <span v-if="viewingIndicatorDetail.recommendation.gap" class="ml-2 italic font-normal">
                ({{ viewingIndicatorDetail.recommendation.gap }})
              </span>
            </div>
            <p class="text-sm leading-relaxed"
              :class="{
                'text-green-800': viewingIndicatorDetail.recommendation.color === 'green',
                'text-red-800':   viewingIndicatorDetail.recommendation.color === 'red',
                'text-gray-700':  viewingIndicatorDetail.recommendation.color === 'gray',
              }">
              {{ viewingIndicatorDetail.recommendation.recommendation }}
            </p>
            <p v-if="viewingIndicatorDetail.recommendation.source"
              class="mt-2 text-[11px] italic"
              :class="{
                'text-green-500': viewingIndicatorDetail.recommendation.color === 'green',
                'text-red-400':   viewingIndicatorDetail.recommendation.color === 'red',
                'text-gray-400':  viewingIndicatorDetail.recommendation.color === 'gray',
              }">
              Sumber: {{ viewingIndicatorDetail.recommendation.source }}
            </p>
          </div>

          <!-- Status Info -->
          <div class="mt-4 p-3 rounded-lg bg-gray-50 border">
            <div class="flex items-center gap-4 text-sm">
              <span class="font-medium text-gray-700">Status:</span>
              <span v-if="viewingIndicatorDetail.validated" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Tervalidasi
              </span>
              <span v-else-if="viewingIndicatorDetail.komentar && !viewingIndicatorDetail.revised" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                Ada catatan — Belum Direvisi
              </span>
              <span v-else-if="viewingIndicatorDetail.komentar && viewingIndicatorDetail.revised" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-orange-100 text-orange-700">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
                Ada catatan — Sudah Direvisi
              </span>
              <span v-else-if="viewingIndicatorDetail.approved" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                Approved
              </span>
              <span v-else-if="viewingIndicatorDetail.has_data" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">
                Menunggu Approve
              </span>
              <span v-else class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-500">
                Data belum diisi
              </span>
            </div>
          </div>
        </div>

        <!-- Table List -->
        <div v-else>
          <!-- Empty state: tidak ada indikator dengan data capaian -->
          <div v-if="displayedTimIndikatorList.length === 0" class="py-12 text-center text-gray-400">
            <svg class="mx-auto mb-3 h-12 w-12 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="font-medium text-gray-500">Belum ada data capaian</p>
            <p class="mt-1 text-sm">Indikator belum terisi numerator &amp; denominator</p>
          </div>

          <!-- Select All - only when current month -->
          <div v-if="isViewingCurrentMonth && displayedTimIndikatorList.length > 0" class="mb-3 flex items-center gap-3 px-2">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                :checked="isAllSelected"
                @change="toggleSelectAll"
                class="h-5 w-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
              />
              <span class="text-sm font-medium text-gray-700">Pilih Semua</span>
            </label>
            <span class="text-sm text-gray-500">({{ selectedIndikatorIds.length }} dipilih)</span>
          </div>

          <table v-if="displayedTimIndikatorList.length > 0" class="min-w-full text-sm border-collapse">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-center w-12 border">#</th>
                <th class="px-3 py-2 text-left border">Indikator</th>
                <th class="px-3 py-2 text-center border w-20">Standar</th>
                <th class="px-3 py-2 text-center border w-16">N</th>
                <th class="px-3 py-2 text-center border w-16">D</th>
                <th class="px-3 py-2 text-center border w-28">Status</th>
                <th class="px-3 py-2 text-center border w-16">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr
                v-for="(ind, idx) in displayedTimIndikatorList"
                :key="ind.id"
                class="hover:bg-gray-50 cursor-pointer"
                :class="{
                  'bg-purple-50': selectedIndikatorIds.includes(ind.id),
                  'bg-yellow-50': ind.komentar && !ind.validated && !selectedIndikatorIds.includes(ind.id),
                }"
                @click="ind.can_approve && isViewingCurrentMonth && toggleIndikatorSelection(ind.id)"
              >
                <td class="px-3 py-2 text-center border">
                  <input
                    v-if="ind.can_approve && isViewingCurrentMonth"
                    type="checkbox"
                    :checked="selectedIndikatorIds.includes(ind.id)"
                    @click.stop
                    @change="toggleIndikatorSelection(ind.id)"
                    class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                  />
                  <span v-else class="text-gray-300">-</span>
                </td>
                <td class="px-3 py-2 border">
                  <div class="flex items-start gap-2">
                    <div class="flex-1">
                      <span class="font-medium text-gray-800">{{ ind.indikator }}</span>
                      <!-- Belum Direvisi warning -->
                      <span v-if="ind.komentar && !ind.revised && !ind.validated" class="ml-2 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        Belum Direvisi
                      </span>
                      <!-- Sudah Direvisi badge -->
                      <span v-else-if="ind.komentar && ind.revised && !ind.validated" class="ml-2 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700">
                        ✓ Sudah Direvisi
                      </span>
                      <!-- Recommendation status badge + open bottom panel -->
                      <div v-if="ind.recommendation" class="mt-1 flex items-center gap-1.5 flex-wrap">
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold"
                          :class="{
                            'bg-green-100 text-green-700': ind.recommendation.color === 'green',
                            'bg-red-100 text-red-700':     ind.recommendation.color === 'red',
                            'bg-gray-100 text-gray-500':   ind.recommendation.color === 'gray',
                          }">
                          <span class="w-1.5 h-1.5 rounded-full inline-block"
                            :class="{
                              'bg-green-500': ind.recommendation.color === 'green',
                              'bg-red-500':   ind.recommendation.color === 'red',
                              'bg-gray-400':  ind.recommendation.color === 'gray',
                            }"></span>
                          {{ ind.recommendation.status }}
                        </span>
                        <button @click.stop="activeRecInd = ind; showRejectInput = false"
                          class="text-[10px] text-blue-500 hover:text-blue-700 underline">
                          Lihat saran
                        </button>
                      </div>
                    </div><!-- end flex-1 -->
                    <!-- Rejected History Icon - show if has history OR rejected flag is true -->
                    <div v-if="ind.rejected_n !== null || ind.rejected_d !== null || ind.rejected" class="relative group flex-shrink-0">
                      <button
                        class="rounded-full p-1 bg-red-100 text-red-600"
                        title="Riwayat isian yang direject"
                        @click.stop
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </button>
                      <!-- Tooltip -->
                      <div class="absolute left-0 top-full mt-1 z-50 hidden group-hover:block bg-white rounded-lg shadow-lg border border-red-200 p-3 w-52">
                        <p class="text-xs font-semibold text-red-700 mb-2">Isian Direject:</p>
                        <div class="text-xs text-gray-600">
                          <div v-if="ind.rejected_n !== null || ind.rejected_d !== null">
                            <div>N: {{ ind.rejected_n ?? '-' }}</div>
                            <div>D: {{ ind.rejected_d ?? '-' }}</div>
                          </div>
                          <div v-else class="text-gray-400 italic">Data history tidak tersedia</div>
                          <div v-if="ind.reject_reason" class="mt-1 text-red-600 italic">"{{ ind.reject_reason }}"</div>
                          <div class="mt-1 text-gray-400">{{ ind.rejected_at || 'Waktu tidak tercatat' }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2 text-center border">{{ ind.standar }}</td>
                <td class="px-3 py-2 text-center border">
                  <span :class="ind.n !== null ? 'text-gray-800' : 'text-gray-400'">
                    {{ ind.n !== null ? ind.n : '-' }}
                  </span>
                </td>
                <td class="px-3 py-2 text-center border">
                  <span :class="ind.d !== null ? 'text-gray-800' : 'text-gray-400'">
                    {{ ind.d !== null ? ind.d : '-' }}
                  </span>
                </td>
                <td class="px-3 py-2 text-center border">
                  <span v-if="ind.validated" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Tervalidasi
                  </span>
                  <span v-else-if="ind.komentar" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-orange-100 text-orange-700">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
                    Ada catatan admin
                  </span>
                  <span v-else-if="ind.approved" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Approved
                  </span>
                  <span v-else-if="ind.has_data" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">
                    Belum Approve
                  </span>
                  <span v-else class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-500">
                    Data kosong
                  </span>
                </td>
                <!-- View Action -->
                <td class="px-3 py-2 text-center border">
                  <button
                    @click.stop="viewIndicatorDetail(ind)"
                    class="p-1.5 rounded-lg bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors"
                    title="Lihat Detail Indikator"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Bottom Panel — Recommendation or Reject Input (list view only) -->
      <div v-if="!viewingIndicatorDetail && isViewingCurrentMonth">
        <!-- Recommendation Panel: shown when "Lihat saran" clicked -->
        <div v-if="activeRecInd && activeRecInd.recommendation && !showRejectInput"
          class="border-t px-4 py-3"
          :class="{
            'bg-green-50': activeRecInd.recommendation.color === 'green',
            'bg-red-50':   activeRecInd.recommendation.color === 'red',
            'bg-gray-50':  activeRecInd.recommendation.color === 'gray',
          }">
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="{
                    'bg-green-100 text-green-700': activeRecInd.recommendation.color === 'green',
                    'bg-red-100   text-red-700':   activeRecInd.recommendation.color === 'red',
                    'bg-gray-100  text-gray-500':  activeRecInd.recommendation.color === 'gray',
                  }">
                  {{ activeRecInd.recommendation.status }}
                </span>
                <span class="text-xs font-medium text-gray-700 truncate">{{ activeRecInd.indikator }}</span>
                <span v-if="activeRecInd.recommendation.gap" class="text-[11px] text-red-600 italic shrink-0">
                  — {{ activeRecInd.recommendation.gap }}
                </span>
              </div>
              <p class="text-xs leading-relaxed"
                :class="{
                  'text-green-800': activeRecInd.recommendation.color === 'green',
                  'text-red-800':   activeRecInd.recommendation.color === 'red',
                  'text-gray-700':  activeRecInd.recommendation.color === 'gray',
                }">
                {{ activeRecInd.recommendation.recommendation }}
              </p>
              <p v-if="activeRecInd.recommendation.source" class="mt-1 text-[10px] italic text-gray-400">
                Sumber: {{ activeRecInd.recommendation.source }}
              </p>
            </div>
            <button @click="activeRecInd = null" class="text-gray-400 hover:text-gray-600 text-lg leading-none shrink-0">&times;</button>
          </div>
        </div>

        <!-- Reject Reason Input: shown when Reject button clicked -->
        <div v-if="showRejectInput && selectedIndikatorIds.length > 0" class="border-t px-4 py-3 bg-red-50">
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-red-700">Alasan Reject (opsional):</label>
            <button @click="showRejectInput = false; rejectReason = ''" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
          </div>
          <div class="flex gap-2">
            <input
              v-model="rejectReason"
              type="text"
              placeholder="Masukkan alasan reject..."
              class="flex-1 px-3 py-2 border border-red-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
              @keyup.enter="rejectSelectedIndicators"
            />
            <button
              @click="rejectSelectedIndicators"
              :disabled="rejectingSelected"
              class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 disabled:opacity-50 whitespace-nowrap"
            >
              {{ rejectingSelected ? 'Memproses...' : `Konfirmasi Reject (${selectedIndikatorIds.length})` }}
            </button>
          </div>
        </div>
      </div>

      <!-- View-only notice for non-current month -->
      <div v-if="!isViewingCurrentMonth && !viewingIndicatorDetail" class="border-t px-6 py-3 bg-amber-50">
        <p class="text-sm text-amber-700 text-center font-medium">Data bulan lalu hanya bisa dilihat, tidak bisa approve/reject</p>
      </div>

      <!-- Detail View Footer — Approve/Reject buttons for single indicator -->
      <div v-if="viewingIndicatorDetail && isViewingCurrentMonth" class="border-t bg-gray-50">
        <!-- Reject reason input (shown when detailRejectMode is true) -->
        <div v-if="detailRejectMode" class="px-4 pt-3 pb-2">
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-sm font-medium text-red-700">Alasan Reject (opsional):</label>
            <button @click="detailRejectMode = false; detailRejectReason = ''" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
          </div>
          <div class="flex gap-2">
            <input
              v-model="detailRejectReason"
              type="text"
              placeholder="Masukkan alasan reject..."
              class="flex-1 px-3 py-1.5 border border-red-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
              @keyup.enter="rejectFromDetail"
            />
            <button
              @click="rejectFromDetail"
              :disabled="rejectingFromDetail"
              class="px-4 py-1.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 disabled:opacity-50 whitespace-nowrap"
            >
              {{ rejectingFromDetail ? 'Memproses...' : 'Konfirmasi Reject' }}
            </button>
          </div>
        </div>
        <!-- Footer buttons -->
        <div class="px-6 py-3 flex items-center justify-between">
          <button
            @click="backToIndicatorList"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm"
          >
            ← Kembali
          </button>
          <div v-if="viewingIndicatorDetail.can_approve || viewingIndicatorDetail.can_reject" class="flex items-center gap-2">
            <!-- Reject -->
            <button
              v-if="viewingIndicatorDetail.can_reject"
              @click="detailRejectMode = !detailRejectMode"
              :disabled="rejectingFromDetail || approvingFromDetail"
              class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-red-500 to-rose-600 text-white text-sm font-semibold rounded-lg shadow hover:from-red-600 hover:to-rose-700 disabled:opacity-50 transition-all"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              Reject
            </button>
            <!-- Approve -->
            <button
              v-if="viewingIndicatorDetail.can_approve"
              @click="approveFromDetail"
              :disabled="approvingFromDetail || rejectingFromDetail"
              class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white text-sm font-semibold rounded-lg shadow hover:from-green-600 hover:to-emerald-700 disabled:opacity-50 transition-all"
            >
              <svg v-if="approvingFromDetail" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              {{ approvingFromDetail ? 'Memproses...' : 'Approve' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Footer - hidden when viewing detail -->
      <div v-if="!viewingIndicatorDetail" class="border-t px-6 py-4 flex items-center justify-between bg-gray-50">
        <button
          @click="closeApproveModal"
          class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors"
        >
          Tutup
        </button>

        <div v-if="selectedIndikatorIds.length > 0 && !viewingIndicatorDetail && isViewingCurrentMonth" class="flex items-center gap-3">
          <!-- Reject Button — shows reject input panel -->
          <button
            @click="showRejectInput = true; activeRecInd = null"
            :disabled="rejectingSelected || approvingSelected"
            class="inline-flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-red-500 to-rose-600 text-white font-semibold rounded-lg shadow-md hover:from-red-600 hover:to-rose-700 disabled:opacity-50 transition-all"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span>Reject ({{ selectedIndikatorIds.length }})</span>
          </button>

          <!-- Approve Button -->
          <button
            @click="approveSelectedIndicators"
            :disabled="approvingSelected || rejectingSelected"
            class="inline-flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg shadow-md hover:from-green-600 hover:to-emerald-700 disabled:opacity-50 transition-all"
          >
            <svg v-if="approvingSelected" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ approvingSelected ? 'Memproses...' : `Approve (${selectedIndikatorIds.length})` }}</span>
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
  font-weight: 700;
  position: sticky;
  top: 0;
  z-index: 10;
  background-color: #f9fafb;
  box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
}

.validated-cell {
  background-color: #BBF7D0;
  color: #065f46;
  font-weight: 600;
}

input[type="file"] + label { 
  user-select: none; 
}

/* Custom scrollbar */
.overflow-auto::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.overflow-auto::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

.overflow-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.overflow-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

@media (max-width: 900px) {
  table { 
    font-size: 12px; 
  }
}
</style>