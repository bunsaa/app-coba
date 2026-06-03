<!-- Vue 3 SFC component: AppLayout + Inertia + Tailwind + Lucide -->
<!-- DataTables (ESM) + Responsive; Modal Tambah Indikator; Modal View (PIC -> read-only, dropdown rapi); Modal Edit (hanya Num/Denom, dropdown rapi) -->

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { registerAutoSave } from '@/composables/useAutoSaveRegistry';

interface TimUnit {
  id: number;
  kode_unit: string;
  nama_tim: string;
}

interface Units {
  id: number;
  kode_unit: string;
  nama_unit: string;
  alias: string;
  tim_units?: TimUnit[];
}

interface props {
  units: Units[];
  currentFilter: string;
}

const props = defineProps<props>();
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Indikator', href: '/indikator' },
];

// Menggunakan data units dari props yang sudah diambil dari database
const units = computed(() => props.units);

/* ====== STATE ====== */
const showView = ref(false);
const viewItem = ref<Units | null>(null);
const viewTimUnit = ref('');
const viewPic = ref('');
const viewIndikatorList = ref<any[]>([]); // List indikator untuk ditampilkan dalam tabel
const formViewInd = ref({ indikator: '', standar: '', numerator: '', denominator: '' });
function resetViewInd() {
  viewTimUnit.value = '';
  viewPic.value = '';
  viewIndikatorList.value = [];
  formViewInd.value = { indikator: '', standar: '', numerator: '', denominator: '' };

  // Auto-load indikator jika unit tidak memiliki tim
  if (viewItem.value && (!viewItem.value.tim_units || viewItem.value.tim_units.length === 0)) {
    loadIndicatorFor(viewItem.value.nama_unit, '');
  }
}

const showEdit = ref(false);
const editItem = ref<Units | null>(null);
const editUnitName = ref('');
const editTimUnit = ref('');
const editPic = ref('');
const editIndikatorId = ref<number | null>(null); // ID indikator yang sedang di-edit
const editJenisIndikator = ref(''); // Jenis indikator (read-only display)
const editIndikatorList = ref<any[]>([]); // List indikator untuk dipilih
const formEditInd = ref({ indikator: '', standar: '', numerator: '', denominator: '', satuan: 'persen', satuan_waktu: '' });
const editTwOption = ref<string>('all'); // 'all' | '1' | '2' | '3' | '4'
const editListLoading = ref(false);
function resetEditInd() {
  editTimUnit.value = '';
  editPic.value = '';
  editIndikatorId.value = null;
  editJenisIndikator.value = '';
  editIndikatorList.value = [];
  editListLoading.value = false;
  formEditInd.value = { indikator: '', standar: '', numerator: '', denominator: '', satuan: 'persen', satuan_waktu: '' };
  editTwOption.value = 'all';
}

const showAddIndicator = ref(false);
const selectedUnit = ref<Units | null>(null);
const selectedUnitName = ref('');
const selectedTimUnit = ref('');
const selectedPicUnits = ref<string[]>([]); // Multi-select unit codes untuk PIC
const picUnitSearch = ref(''); // Search query untuk filter daftar unit/tim di PIC
const formAddInd = ref<{ jenis_indikator: string; indikator: string; standar: string; satuan: string; satuan_waktu: string; pic: string; numerator: string; denominator: string; berlaku_tw: number[] }>({ jenis_indikator: '', indikator: '', standar: '', satuan: 'persen', satuan_waktu: '', pic: '', numerator: '', denominator: '', berlaku_tw: [1,2,3,4] });
const formAddTwOption = ref<string>('all'); // 'all' | '1' | '2' | '3' | '4'
const isSubmitting = ref(false); // Prevent double click saat request sedang diproses

// Draft localStorage untuk form tambah indikator
const DRAFT_KEY = 'indikator_tambah_draft'
const hasDraft = ref(false)

onMounted(() => {
  try {
    if (localStorage.getItem(DRAFT_KEY)) hasDraft.value = true
  } catch {}
})

function restoreDraft() {
  try {
    const raw = localStorage.getItem(DRAFT_KEY)
    if (!raw) return
    const draft = JSON.parse(raw)
    if (draft.form) Object.assign(formAddInd.value, draft.form)
    if (draft.twOption) formAddTwOption.value = draft.twOption
    if (Array.isArray(draft.picUnits)) selectedPicUnits.value = draft.picUnits
    hasDraft.value = false
    localStorage.removeItem(DRAFT_KEY)
    showAddIndicator.value = true
  } catch {
    hasDraft.value = false
    localStorage.removeItem(DRAFT_KEY)
  }
}

function discardDraft() {
  hasDraft.value = false
  localStorage.removeItem(DRAFT_KEY)
}

// Filter units berdasarkan search query untuk PIC checkbox list
const filteredPicUnits = computed(() => {
  const q = picUnitSearch.value.trim().toLowerCase();
  if (!q) return units.value;
  return units.value.filter(u =>
    u.nama_unit.toLowerCase().includes(q) ||
    u.kode_unit.toLowerCase().includes(q) ||
    (u.tim_units ?? []).some(t => t.nama_tim.toLowerCase().includes(q))
  );
});

// Konversi twOption → array untuk dikirim ke server
function twOptionToArray(opt: string): number[] {
  if (opt === 'all') return [1,2,3,4];
  return [parseInt(opt)];
}

// Computed untuk mendapatkan PIC otomatis - hanya untuk modal View & Edit
const computedPicView = computed(() => {
  if (viewTimUnit.value) {
    return viewTimUnit.value;
  }
  if (viewAvailableTimUnits.value.length === 0 && viewItem.value) {
    return viewItem.value.nama_unit;
  }
  return '';
});

const computedPicEdit = computed(() => {
  if (editTimUnit.value) {
    return editTimUnit.value;
  }
  if (editAvailableTimUnits.value.length === 0 && editItem.value) {
    return editItem.value.nama_unit;
  }
  return '';
});

const viewAvailableTimUnits = computed(() => {
  if (viewItem.value && viewItem.value.tim_units && viewItem.value.tim_units.length > 0) {
    return viewItem.value.tim_units;
  }
  return [];
});

const editAvailableTimUnits = computed(() => {
  if (editItem.value && editItem.value.tim_units && editItem.value.tim_units.length > 0) {
    return editItem.value.tim_units;
  }
  return [];
});

/* ====== Pagination & Search for Units Table ====== */
const searchQueryUnits = ref('');
const currentPageUnits = ref(1);
const itemsPerPageUnits = 10;

// Filtered & Paginated Data for Units
const filteredUnits = computed(() => {
  if (!searchQueryUnits.value) return units.value;

  const query = searchQueryUnits.value.toLowerCase();
  return units.value.filter(unit =>
    unit.nama_unit.toLowerCase().includes(query)
  );
});

const totalPagesUnits = computed(() => Math.ceil(filteredUnits.value.length / itemsPerPageUnits));

const paginatedUnits = computed(() => {
  const start = (currentPageUnits.value - 1) * itemsPerPageUnits;
  const end = start + itemsPerPageUnits;
  return filteredUnits.value.slice(start, end);
});

function goToPageUnits(page: number) {
  if (page >= 1 && page <= totalPagesUnits.value) {
    currentPageUnits.value = page;
  }
}

// Reset page when search changes
watch(searchQueryUnits, () => {
  currentPageUnits.value = 1;
});

/* ====== Functions sudah tidak diperlukan karena menggunakan manual table ====== */

function loadIndicatorFor(unitName: string, timUnit: string) {
  const kodeUnit = viewItem.value?.kode_unit;
  if (!kodeUnit) return;

  // Load data dari database - INCLUDE INACTIVE untuk modal view
  axios.post('/indikator/get-by-unit', {
    kode_unit: kodeUnit,
    tim_unit: timUnit || null,
    include_inactive: true // Tambah parameter ini
  })
  .then(response => {
    console.log('✅ Indikators loaded:', response.data);
    viewIndikatorList.value = response.data;
  })
  .catch(error => {
    console.error('❌ Error loading indikators:', error);
    console.error('Response:', error.response?.data);
    alert('Gagal memuat data indikator: ' + (error.response?.data?.error || error.message));
  });
}

function loadIndicatorForEdit(unitName: string, timUnit: string, autoSelect = true) {
  const kodeUnit = editItem.value?.kode_unit;
  if (!kodeUnit) return;

  // Reset pilihan & form saat load list baru
  editIndikatorId.value = null;
  editJenisIndikator.value = '';
  editIndikatorList.value = [];
  formEditInd.value = { indikator: '', standar: '', numerator: '', denominator: '', satuan: 'persen', satuan_waktu: '' };
  editListLoading.value = true;

  // Load data dari database - HANYA AKTIF untuk edit
  axios.post('/indikator/get-by-unit', {
    kode_unit: kodeUnit,
    tim_unit: timUnit || null,
    include_inactive: false // Hanya yang aktif
  })
  .then(response => {
    console.log('✅ Indikators loaded for edit:', response.data);
    editIndikatorList.value = response.data;

    // Auto-select indikator pertama hanya jika autoSelect = true (unit dengan tim)
    if (autoSelect && response.data.length > 0) {
      const firstIndikator = response.data[0];
      editIndikatorId.value = firstIndikator.id;
      const berlakuTw0 = firstIndikator.berlaku_tw ?? [1,2,3,4];
      editTwOption.value = berlakuTw0.length === 4 ? 'all' : String(berlakuTw0[0]);
      formEditInd.value = {
        indikator: firstIndikator.indikator,
        standar: firstIndikator.standar,
        numerator: firstIndikator.numerator || '',
        denominator: firstIndikator.denominator || '',
        satuan: firstIndikator.satuan || 'persen',
        satuan_waktu: firstIndikator.satuan_waktu || '',
      };
      editJenisIndikator.value = firstIndikator.jenis_indikator || '';
    }
  })
  .catch(error => {
    console.error('❌ Error loading indikators for edit:', error);
    alert('Gagal memuat data indikator: ' + (error.response?.data?.error || error.message));
  })
  .finally(() => {
    editListLoading.value = false;
  });
}

// Buka modal edit — untuk unit tanpa tim: langsung load list indicator
function openEditModal(unit: Units) {
  editItem.value = unit;
  editUnitName.value = unit.nama_unit;
  resetEditInd();
  showEdit.value = true;
  const hasTim = unit.tim_units && unit.tim_units.length > 0;
  if (!hasTim) {
    loadIndicatorForEdit(unit.nama_unit, '', false);
  }
}


// Function untuk handle perubahan indikator yang dipilih
function onEditIndikatorChange(indikatorId: number | null) {
  if (!indikatorId) return;
  
  const selected = editIndikatorList.value.find(ind => ind.id === indikatorId);
  if (selected) {
    console.log('Selected indikator:', selected);
    editIndikatorId.value = selected.id;
    const berlakuTwSel = selected.berlaku_tw ?? [1,2,3,4];
    editTwOption.value = berlakuTwSel.length === 4 ? 'all' : String(berlakuTwSel[0]);
    formEditInd.value = {
      indikator: selected.indikator,
      standar: selected.standar,
      numerator: selected.numerator || '',
      denominator: selected.denominator || '',
      satuan: selected.satuan || 'persen',
      satuan_waktu: selected.satuan_waktu || '',
    };
    editJenisIndikator.value = selected.jenis_indikator || '';
    console.log('formEditInd after change:', formEditInd.value);
  }
}

function openAddIndicatorModal() {
  // Reset semua form
  selectedUnit.value = null;
  selectedUnitName.value = '';
  selectedTimUnit.value = '';
  selectedPicUnits.value = [];
  picUnitSearch.value = '';
  formAddInd.value = { jenis_indikator: '', indikator: '', standar: '', satuan: 'persen', satuan_waktu: '', pic: '', numerator: '', denominator: '', berlaku_tw: [1,2,3,4] };
  formAddTwOption.value = 'all';
  showAddIndicator.value = true;
}

function addIndicatorSave() {
  // Prevent double submit
  if (isSubmitting.value) {
    console.log('Already submitting, ignoring duplicate request');
    return;
  }

  const f = formAddInd.value;

  // Validasi: Jenis indikator wajib dipilih
  if (!f.jenis_indikator) {
    alert('Pilih jenis indikator terlebih dahulu.');
    return;
  }

  // Validasi: Semua field wajib diisi
  if (!f.indikator || !f.standar || !f.satuan || !f.numerator || !f.denominator) {
    alert('Lengkapi semua kolom wajib (Indikator, Standar, Satuan, Numerator, Denominator).');
    return;
  }

  // Validasi: Jika satuan = rata-rata, satuan_waktu wajib diisi
  if (f.satuan === 'rata-rata' && !f.satuan_waktu) {
    alert('Satuan waktu harus diisi untuk satuan rata-rata.');
    return;
  }

  // Validasi: Minimal harus pilih 1 unit untuk PIC
  if (selectedPicUnits.value.length === 0) {
    alert('Pilih minimal 1 unit untuk PIC.');
    return;
  }

  console.log('Data yang akan dikirim:', {
    jenis_indikator: f.jenis_indikator,
    kode_unit: null, // Tidak terikat ke satu unit
    tim_unit: null,
    indikator: f.indikator,
    standar: f.standar,
    satuan: f.satuan,
    satuan_waktu: f.satuan_waktu,
    pic: null, // Tidak menggunakan pic string lagi
    pic_units: selectedPicUnits.value, // Array of unit codes
    numerator: f.numerator,
    denominator: f.denominator,
  });

  // Set submitting flag
  isSubmitting.value = true;

  // Simpan ke database via Inertia
  router.post('/indikator', {
    jenis_indikator: f.jenis_indikator,
    kode_unit: null,
    tim_unit: null,
    indikator: f.indikator,
    standar: f.standar,
    satuan: f.satuan,
    satuan_waktu: f.satuan_waktu,
    pic: null,
    pic_units: selectedPicUnits.value,
    numerator: f.numerator,
    denominator: f.denominator,
    berlaku_tw: twOptionToArray(formAddTwOption.value),
  }, {
    preserveScroll: true,
    onSuccess: (page) => {
      console.log('Success response:', page);
      alert('Indikator berhasil ditambahkan!');
      localStorage.removeItem(DRAFT_KEY)
      hasDraft.value = false
      showAddIndicator.value = false;
      // Reset form
      formAddInd.value = { jenis_indikator: '', indikator: '', standar: '', satuan: 'persen', satuan_waktu: '', pic: '', numerator: '', denominator: '', berlaku_tw: [1,2,3,4] };
      formAddTwOption.value = 'all';
      selectedPicUnits.value = [];
      picUnitSearch.value = '';
      // Reload indicator list if view modal open
      if (showView.value && viewItem.value) {
        loadIndicatorFor(viewItem.value.nama_unit, viewTimUnit.value);
      }
      // Reset rekap cache agar data fresh saat dibuka kembali
      rekapData.value = [];
    },
    onError: (errors) => {
      console.error('Error:', errors);
      if (errors.indikator) {
        alert('⚠️ ' + errors.indikator);
      } else {
        alert('Gagal menambahkan indikator: ' + JSON.stringify(errors));
      }
    },
    onFinish: () => {
      console.log('Request finished');
      isSubmitting.value = false; // Reset flag after request completes
    }
  });
}

function editSave() {
  console.log('=== START EDIT SAVE ===');
  console.log('editUnitName:', editUnitName.value);
  console.log('editTimUnit:', editTimUnit.value);
  console.log('editIndikatorId:', editIndikatorId.value);
  console.log('formEditInd:', formEditInd.value);
  
  if (!editUnitName.value) { 
    alert('Unit tidak ditemukan'); 
    return; 
  }
  
  // Jika unit memiliki tim units, wajib pilih tim unit
  if (editAvailableTimUnits.value.length > 0 && !editTimUnit.value) { 
    alert('Pilih Tim/Unit terlebih dahulu'); 
    return; 
  }

  if (!editIndikatorId.value) {
    alert('Pilih indikator yang akan di-edit');
    return;
  }
  
  const f = formEditInd.value;
  
  console.log('Checking validation...');
  console.log('indikator:', f.indikator);
  console.log('standar:', f.standar);
  console.log('numerator:', f.numerator);
  console.log('denominator:', f.denominator);
  
  if (!f.indikator || !f.standar || !f.numerator || !f.denominator) {
    alert('Semua field harus diisi (kecuali PIC)');
    return;
  }
  if (f.satuan === 'rata-rata' && !f.satuan_waktu) {
    alert('Satuan waktu harus diisi untuk satuan rata-rata.');
    return;
  }

  const dataToSend = {
    indikator: f.indikator,
    standar: f.standar,
    numerator: f.numerator,
    denominator: f.denominator,
    satuan: f.satuan,
    satuan_waktu: f.satuan === 'rata-rata' ? f.satuan_waktu : null,
    berlaku_tw: twOptionToArray(editTwOption.value),
  };

  console.log('Data yang akan dikirim:', dataToSend);
  console.log('URL:', `/indikator/${editIndikatorId.value}`);

  // Update ke database via Inertia
  router.put(`/indikator/${editIndikatorId.value}`, dataToSend, {
    preserveScroll: true,
    onSuccess: (page) => {
      console.log('✅ Success response:', page);
      alert('Indikator berhasil diupdate!');
      showEdit.value = false;
      resetEditInd();
      // Reload indicator list if view modal open
      if (showView.value && viewItem.value) {
        loadIndicatorFor(viewItem.value.nama_unit, viewTimUnit.value);
      }
      // Reset rekap cache
      rekapData.value = [];
    },
    onError: (errors) => {
      console.error('❌ Error:', errors);
      alert('Gagal mengupdate indikator: ' + JSON.stringify(errors));
    },
    onFinish: () => {
      console.log('=== FINISH EDIT SAVE ===');
    }
  });
}

async function toggleActiveIndikator(indikator: any) {
  const action = indikator.is_active ? 'menonaktifkan' : 'mengaktifkan';

  if (!confirm(`Yakin ingin ${action} indikator ini?`)) return;

  try {
    const response = await axios.post(`/indikator/toggle-active/${indikator.id}`);

    alert(response.data.message);

    // Reload indicator list & reset rekap cache
    if (viewItem.value) {
      loadIndicatorFor(viewItem.value.nama_unit, viewTimUnit.value);
    }
    rekapData.value = [];
  } catch (error) {
    console.error('Error toggling active:', error);
    alert('Gagal mengubah status indikator');
  }
}

/* ====== REKAP MODAL ====== */
const showRekap = ref(false);
const rekapData = ref<any[]>([]);
const rekapLoading = ref(false);
const rekapFilterJenis = ref('');
const rekapFilterStatus = ref('');
const rekapSearch = ref('');
const rekapPage = ref(1);
const rekapPerPage = 10;

/* ====== REKAP EDIT MODAL ====== */
const showRekapEdit = ref(false);
const rekapEditItem = ref<any>(null);
const rekapEditForm = ref({ indikator: '', standar: '', numerator: '', denominator: '', satuan: 'persen', satuan_waktu: '' });
const rekapEditTwOption = ref<string>('all');
const rekapEditSaving = ref(false);
const rekapEditSelectedUnits = ref<string[]>([]); // Multi-select PIC units untuk edit
const rekapEditPicSearch = ref(''); // Search untuk PIC checkbox dalam edit modal

const rekapEditFilteredUnits = computed(() => {
  const q = rekapEditPicSearch.value.trim().toLowerCase();
  if (!q) return units.value;
  return units.value.filter(u =>
    u.nama_unit.toLowerCase().includes(q) ||
    u.kode_unit.toLowerCase().includes(q) ||
    (u.tim_units ?? []).some(t => t.nama_tim.toLowerCase().includes(q))
  );
});

const jenisLabel: Record<string, string> = {
  INM: 'INDIKATOR NASIONAL MUTU', SPM: 'INDIKATOR STANDAR PELAYANAN MINIMAL', PRIORITAS: 'INDIKATOR MUTU PRIORITAS RS', IMUT_UNIT: 'INDIKATOR MUTU UNIT',
};
const jenisBadgeClass: Record<string, string> = {
  INM: 'bg-purple-100 text-purple-700',
  SPM: 'bg-blue-100 text-blue-700',
  PRIORITAS: 'bg-green-100 text-green-700',
  IMUT_UNIT: 'bg-yellow-100 text-yellow-700',
};

const rekapGrouped = computed(() => {
  const jenisOrder: Record<string, number> = { INM: 0, SPM: 1, PRIORITAS: 2, IMUT_RS: 3, IMUT_UNIT: 4 };

  // INM & PRIORITAS: grouped by name (shared across many units = 1 entry)
  const groupedMap = new Map<string, any>();
  // SPM, IMUT_RS, IMUT_UNIT: each DB record = 1 separate row
  const nonGrouped: any[] = [];

  const shouldGroup = (jenis: string) => jenis === 'INM' || jenis === 'PRIORITAS';

  for (const row of rekapData.value as any[]) {
    if (shouldGroup(row.jenis_indikator)) {
      const key = `${row.jenis_indikator}||${row.indikator}`;
      if (!groupedMap.has(key)) {
        groupedMap.set(key, {
          ...row,
          units: [{ id: row.id, kode_unit: row.kode_unit, nama_unit: row.nama_unit, tim_unit: row.tim_unit, is_active: row.is_active }],
        });
      } else {
        groupedMap.get(key).units.push({ id: row.id, kode_unit: row.kode_unit, nama_unit: row.nama_unit, tim_unit: row.tim_unit, is_active: row.is_active });
      }
    } else {
      nonGrouped.push({
        ...row,
        units: [{ id: row.id, kode_unit: row.kode_unit, nama_unit: row.nama_unit, tim_unit: row.tim_unit, is_active: row.is_active }],
        allActive: row.is_active,
        allInactive: !row.is_active,
        hasDuplicate: false,
      });
    }
  }

  const groupedList = Array.from(groupedMap.values()).map(g => {
    const unitKeys = g.units.map((u: any) => `${u.kode_unit ?? ''}|${u.tim_unit ?? ''}`);
    return {
      ...g,
      is_active: g.units.some((u: any) => u.is_active),
      allActive: g.units.every((u: any) => u.is_active),
      allInactive: g.units.every((u: any) => !u.is_active),
      hasDuplicate: unitKeys.length !== new Set(unitKeys).size,
    };
  });

  return [...groupedList, ...nonGrouped]
    .sort((a, b) => (jenisOrder[a.jenis_indikator] ?? 99) - (jenisOrder[b.jenis_indikator] ?? 99));
});

const rekapSummary = computed(() => ({
  total:     rekapGrouped.value.filter(d => d.is_active).length,
  inm:       rekapGrouped.value.filter(d => d.jenis_indikator === 'INM' && d.is_active).length,
  spm:       rekapGrouped.value.filter(d => d.jenis_indikator === 'SPM' && d.is_active).length,
  prioritas: rekapGrouped.value.filter(d => (d.jenis_indikator === 'PRIORITAS' || (d.jenis_indikator === 'INM' && d.is_prioritas)) && d.is_active).length,
  imut_unit: rekapGrouped.value.filter(d => d.jenis_indikator === 'IMUT_UNIT' && d.is_active).length,
  aktif:     rekapGrouped.value.filter(d => d.is_active).length,
  nonaktif:  rekapGrouped.value.filter(d => d.allInactive).length,
}));

const rekapFiltered = computed(() => {
  let data = rekapGrouped.value;
  if (rekapFilterJenis.value) {
    if (rekapFilterJenis.value === 'PRIORITAS') {
      data = data.filter(d => d.jenis_indikator === 'PRIORITAS' || (d.jenis_indikator === 'INM' && d.is_prioritas));
    } else {
      data = data.filter(d => d.jenis_indikator === rekapFilterJenis.value);
    }
  }
  if (rekapFilterStatus.value === 'aktif') data = data.filter(d => d.is_active);
  if (rekapFilterStatus.value === 'nonaktif') data = data.filter(d => d.allInactive);
  if (rekapSearch.value) {
    const q = rekapSearch.value.toLowerCase();
    data = data.filter(d =>
      d.indikator?.toLowerCase().includes(q) ||
      d.units?.some((u: any) => u.nama_unit?.toLowerCase().includes(q) || u.tim_unit?.toLowerCase().includes(q))
    );
  }
  return data;
});

const rekapTotalPages = computed(() => Math.max(1, Math.ceil(rekapFiltered.value.length / rekapPerPage)));
const rekapPaginated  = computed(() => {
  const start = (rekapPage.value - 1) * rekapPerPage;
  return rekapFiltered.value.slice(start, start + rekapPerPage);
});

// Reset ke halaman 1 setiap kali filter/search berubah
watch([rekapFilterJenis, rekapFilterStatus, rekapSearch], () => { rekapPage.value = 1; });

async function openRekapModal() {
  rekapPage.value = 1;
  showRekap.value = true;
  rekapLoading.value = true;
  try {
    const res = await axios.post('/indikator/get-all');
    rekapData.value = res.data;
  } catch {
    alert('Gagal memuat data rekap indikator');
  } finally {
    rekapLoading.value = false;
  }
}

async function togglePrioritas(ind: any): Promise<void> {
  const newVal = !ind.is_prioritas;
  // Optimistic update on all rows with same indicator text
  rekapData.value = rekapData.value.map((r: any) =>
    r.indikator === ind.indikator && r.jenis_indikator === 'INM'
      ? { ...r, is_prioritas: newVal }
      : r
  );
  try {
    await axios.post('/indikator/toggle-prioritas', {
      indikator_text: ind.indikator,
      is_prioritas: newVal,
    });
  } catch {
    // Revert on error
    rekapData.value = rekapData.value.map((r: any) =>
      r.indikator === ind.indikator && r.jenis_indikator === 'INM'
        ? { ...r, is_prioritas: !newVal }
        : r
    );
    alert('Gagal mengubah status Prioritas RS');
  }
}

function twArrayToOption(arr: number[]): string {
  if (!arr || arr.length === 4) return 'all';
  return String(arr[0]);
}

function openRekapEdit(ind: any) {
  rekapEditItem.value = ind;
  rekapEditTwOption.value = twArrayToOption(ind.berlaku_tw ?? [1,2,3,4]);
  rekapEditForm.value = {
    indikator:    ind.indikator ?? '',
    standar:      String(ind.standar ?? ''),
    numerator:    ind.numerator ?? '',
    denominator:  ind.denominator ?? '',
    satuan:       ind.satuan ?? 'persen',
    satuan_waktu: ind.satuan_waktu ?? '',
  };
  // Pre-select current units from the grouped units array
  rekapEditSelectedUnits.value = (ind.units ?? []).map((u: any) =>
    u.tim_unit ? `${u.kode_unit}|${u.tim_unit}` : u.kode_unit
  );
  rekapEditPicSearch.value = '';
  showRekapEdit.value = true;
}

async function rekapEditSave() {
  const f = rekapEditForm.value;
  if (!f.indikator || !f.standar || !f.numerator || !f.denominator) {
    alert('Semua field harus diisi');
    return;
  }
  if (f.satuan === 'rata-rata' && !f.satuan_waktu) {
    alert('Satuan waktu harus diisi untuk satuan rata-rata');
    return;
  }
  if (rekapEditSelectedUnits.value.length === 0) {
    alert('Pilih minimal 1 unit untuk PIC');
    return;
  }
  rekapEditSaving.value = true;
  try {
    await axios.post('/indikator/rekap-update', {
      original_indikator: rekapEditItem.value.indikator,
      jenis_indikator:    rekapEditItem.value.jenis_indikator,
      indikator:          f.indikator,
      standar:            f.standar,
      numerator:          f.numerator,
      denominator:        f.denominator,
      satuan:             f.satuan,
      satuan_waktu:       f.satuan === 'rata-rata' ? f.satuan_waktu : null,
      berlaku_tw:         twOptionToArray(rekapEditTwOption.value),
      pic_units:          rekapEditSelectedUnits.value,
    });
    alert('Indikator berhasil diupdate!');
    showRekapEdit.value = false;
    rekapEditItem.value = null;
    // Reload rekap data
    rekapLoading.value = true;
    const res = await axios.post('/indikator/get-all');
    rekapData.value = res.data;
  } catch {
    alert('Gagal mengupdate indikator');
  } finally {
    rekapEditSaving.value = false;
    rekapLoading.value = false;
  }
}

async function toggleActiveFromRekap(ind: any) {
  const newState = !ind.is_active;
  const action = newState ? 'mengaktifkan' : 'menonaktifkan';
  if (!confirm(`Yakin ingin ${action} indikator ini?`)) return;
  try {
    const res = await axios.post('/indikator/toggle-active-group', {
      original_indikator: ind.indikator,
      jenis_indikator:    ind.jenis_indikator,
      is_active:          newState,
    });
    alert(res.data.message);
    // Reload rekap data
    rekapLoading.value = true;
    const reload = await axios.post('/indikator/get-all');
    rekapData.value = reload.data;
    rekapLoading.value = false;
  } catch {
    alert('Gagal mengubah status indikator');
    rekapLoading.value = false;
  }
}

async function deleteIndikatorUnit(unitEntry: any, indText: string): Promise<void> {
  const label = unitEntry.tim_unit
    ? `${unitEntry.nama_unit} - ${unitEntry.tim_unit}`
    : unitEntry.nama_unit;
  if (!confirm(`Yakin ingin menghapus indikator "${indText}" untuk unit "${label}"? Data ini tidak dapat dikembalikan.`)) return;
  try {
    await axios.delete(`/indikator/${unitEntry.id}`);
    // Hapus dari rekapData
    rekapData.value = rekapData.value.filter((r: any) => r.id !== unitEntry.id);
  } catch {
    alert('Gagal menghapus indikator');
  }
}

function formatPicDisplay(picEntry: string): string {
  // Format bisa berupa: "UNIT-01" atau "UNIT-01|Tim A"
  if (picEntry.includes('|')) {
    const [kodeUnit, timUnit] = picEntry.split('|');
    const unit = units.value.find(u => u.kode_unit === kodeUnit);
    return unit ? `${unit.nama_unit} - ${timUnit}` : picEntry;
  } else {
    const unit = units.value.find(u => u.kode_unit === picEntry);
    return unit ? unit.nama_unit : picEntry;
  }
}

// Filter PIC untuk modal view - hanya tampilkan yang relevan dengan unit/tim yang sedang dilihat
function getRelevantPicForView(item: any): string {
  // Jika indikator lama (ada kolom pic), tampilkan pic
  if (item.pic) {
    return item.pic;
  }

  // Jika indikator baru (ada pic_units array)
  if (item.pic_units && item.pic_units.length > 0) {
    const currentKodeUnit = viewItem.value?.kode_unit;
    const currentTimUnit = viewTimUnit.value;

    // Jika ada tim unit yang dipilih, cari yang spesifik
    if (currentTimUnit) {
      const searchPattern = `${currentKodeUnit}|${currentTimUnit}`;
      const found = item.pic_units.find((p: string) => p === searchPattern);
      if (found) {
        return currentTimUnit;
      }
    }

    // Jika tidak ada tim unit atau tidak ketemu, cari yang kode_unit saja
    const found = item.pic_units.find((p: string) => p === currentKodeUnit);
    if (found && viewItem.value) {
      return viewItem.value.nama_unit;
    }

    // Fallback: tampilkan unit pertama dari pic_units
    return formatPicDisplay(item.pic_units[0]);
  }

  return '-';
}

// ===== AUTO-SAVE SEBELUM AUTO-LOGOUT =====
const _unregisterAutoSave = registerAutoSave(async () => {
  if (showAddIndicator.value) {
    try {
      localStorage.setItem(DRAFT_KEY, JSON.stringify({
        form: formAddInd.value,
        twOption: formAddTwOption.value,
        picUnits: selectedPicUnits.value,
      }))
      hasDraft.value = false // akan di-set true pada re-mount berikutnya
    } catch {
      // ignore localStorage errors
    }
  }
})

onUnmounted(_unregisterAutoSave)

</script>

<template>
  <Head title="Indikator" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-3 p-4 xl:p-6">
      <h3 class="shrink-0 text-xl font-bold text-center text-gray-800 dark:text-gray-100">INDIKATOR MUTU RSUD TARAKAN</h3>

      <!-- Banner draft tersimpan dari sesi sebelumnya -->
      <div
        v-if="hasDraft"
        class="flex items-center justify-between gap-3 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm dark:border-yellow-700 dark:bg-yellow-900/30"
      >
        <div class="flex items-center gap-2 text-yellow-800 dark:text-yellow-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
          </svg>
          Ada draft tambah indikator yang tersimpan dari sesi sebelumnya.
        </div>
        <div class="flex gap-2">
          <button
            @click="restoreDraft"
            class="rounded-md bg-yellow-600 px-3 py-1 text-xs font-medium text-white hover:bg-yellow-700"
          >Pulihkan</button>
          <button
            @click="discardDraft"
            class="rounded-md border border-yellow-400 px-3 py-1 text-xs font-medium text-yellow-700 hover:bg-yellow-100 dark:text-yellow-300 dark:hover:bg-yellow-900/50"
          >Abaikan</button>
        </div>
      </div>

      <!-- Container Card -->
      <div class="flex flex-1 min-h-0 flex-col rounded-xl border border-l-4 border-sidebar-border/70 bg-white p-5 shadow-md dark:border-sidebar-border xl:p-6">

        <!-- Header dengan Tombol & Search -->
        <div class="mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <!-- Search Bar -->
          <div class="flex-1 relative max-w-md">
            <input
              v-model="searchQueryUnits"
              type="text"
              placeholder="Cari Unit/Bagian..."
              class="w-full rounded-lg border border-gray-300 px-4 py-2 pl-10 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            />
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Tombol Aksi -->
          <div class="flex items-center gap-2">
            <!-- Tombol View Rekap -->
            <button
              @click="openRekapModal"
              class="flex items-center justify-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-white hover:bg-teal-700 shadow-md transition-colors whitespace-nowrap"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Rekap
            </button>
            <!-- Tombol Tambah Indikator -->
            <button
              @click="openAddIndicatorModal"
              class="flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 shadow-md transition-colors whitespace-nowrap"
            >
              <span class="text-xl">➕</span>
            </button>
          </div>
        </div>

        <!-- Table with Scroll -->
        <div class="overflow-auto rounded-lg border border-gray-200 flex-1 dark:border-gray-700" style="max-height: calc(100vh - 400px); min-height: 400px;">
          <table class="w-full text-sm border-collapse">
            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="border px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-800">No</th>
                <th class="border px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-800">Unit/Bagian</th>
                <th class="border px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-800">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(unit, index) in paginatedUnits"
                :key="unit.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                <td class="border px-4 py-3 text-center dark:text-gray-200">{{ (currentPageUnits - 1) * itemsPerPageUnits + index + 1 }}</td>
                <td class="border px-4 py-3 dark:text-gray-200">
                  <div>
                    <p class="font-medium">{{ unit.nama_unit }}</p>
                    <p v-if="unit.tim_units && unit.tim_units.length > 0" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                      {{ unit.tim_units.length }} Tim Unit
                    </p>
                  </div>
                </td>
                <td class="border px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button
                      @click="viewItem = unit; resetViewInd(); showView = true"
                      class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700 dark:text-gray-200 transition-colors"
                      title="Lihat"
                    >
                      👁️ 
                    </button>
                    <button
                      @click="openEditModal(unit)"
                      class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700 dark:text-gray-200 transition-colors"
                      title="Edit"
                    >
                      ✏️ 
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="paginatedUnits.length === 0">
                <td colspan="3" class="border px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                  <p class="text-sm">Tidak ada data yang sesuai dengan pencarian "{{ searchQueryUnits }}"</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPagesUnits > 1" class="mt-4 flex items-center justify-between">
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Menampilkan {{ (currentPageUnits - 1) * itemsPerPageUnits + 1 }} - {{ Math.min(currentPageUnits * itemsPerPageUnits, filteredUnits.length) }} dari {{ filteredUnits.length }} data
          </p>

          <div class="flex items-center gap-2">
            <button
              @click="goToPageUnits(currentPageUnits - 1)"
              :disabled="currentPageUnits === 1"
              class="px-3 py-1 rounded-lg border text-sm transition-colors"
              :class="currentPageUnits === 1 ? 'border-gray-200 text-gray-400 cursor-not-allowed dark:border-gray-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
            >
              ‹ Prev
            </button>

            <div class="flex gap-1">
              <button
                v-for="page in totalPagesUnits"
                :key="page"
                @click="goToPageUnits(page)"
                class="px-3 py-1 rounded-lg text-sm transition-colors"
                :class="page === currentPageUnits ? 'bg-indigo-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
              >
                {{ page }}
              </button>
            </div>

            <button
              @click="goToPageUnits(currentPageUnits + 1)"
              :disabled="currentPageUnits === totalPagesUnits"
              class="px-3 py-1 rounded-lg border text-sm transition-colors"
              :class="currentPageUnits === totalPagesUnits ? 'border-gray-200 text-gray-400 cursor-not-allowed dark:border-gray-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
            >
              Next ›
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Tambah -->
      <Teleport to="body">
        <div v-if="showAddIndicator" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showAddIndicator=false">
          <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
              <h4 class="text-lg font-semibold dark:text-gray-100">Tambah Indikator Mutu</h4>
              <button @click="showAddIndicator=false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Jenis Indikator -->
              <label class="block text-sm md:col-span-2">
                <span class="mb-1 block font-medium dark:text-gray-200">Jenis Indikator <span class="text-red-500">*</span></span>
                <select
                  v-model="formAddInd.jenis_indikator"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                  <option value="" disabled>Pilih Jenis Indikator...</option>
                  <option value="SPM">INDIKATOR STANDAR PELAYANAN MINIMAL</option>
                  <option value="INM">INDIKATOR NASIONAL MUTU</option>
                  <option value="PRIORITAS">INDIKATOR MUTU PRIORITAS RS</option>
                  <option value="IMUT_UNIT">INDIKATOR MUTU UNIT</option>
                </select>
              </label>

              <!-- Multi-select PIC Units dengan nested Tim Units -->
              <label class="block text-sm md:col-span-2">
                <span class="mb-1 block font-medium dark:text-gray-200">PIC (Pilih Unit/Tim) <span class="text-red-500">*</span></span>
                <div class="border border-gray-300 rounded-lg dark:border-gray-600">
                  <div class="px-3 pt-2 pb-1 border-b border-gray-200 dark:border-gray-700">
                    <input
                      v-model="picUnitSearch"
                      type="text"
                      placeholder="Cari unit atau tim..."
                      class="w-full rounded border border-gray-200 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                  </div>
                  <div class="p-3 max-h-56 overflow-y-auto">
                  <p v-if="filteredPicUnits.length === 0" class="text-xs text-gray-400 text-center py-2">Tidak ada hasil</p>
                  <div v-for="unit in filteredPicUnits" :key="unit.id" class="mb-3">
                    <!-- Unit Checkbox -->
                    <div class="flex items-center mb-1">
                      <input
                        type="checkbox"
                        :id="'unit-' + unit.kode_unit"
                        :value="unit.kode_unit"
                        v-model="selectedPicUnits"
                        class="mr-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                      />
                      <label :for="'unit-' + unit.kode_unit" class="text-sm font-medium dark:text-gray-200 cursor-pointer">
                        {{ unit.nama_unit }}
                      </label>
                    </div>

                    <!-- Tim Units (nested, jika ada) -->
                    <div v-if="unit.tim_units && unit.tim_units.length > 0" class="ml-6 mt-1 space-y-1">
                      <div v-for="tim in unit.tim_units" :key="tim.id" class="flex items-center">
                        <input
                          type="checkbox"
                          :id="'tim-' + unit.kode_unit + '-' + tim.id"
                          :value="unit.kode_unit + '|' + tim.nama_tim"
                          v-model="selectedPicUnits"
                          class="mr-2 h-3.5 w-3.5 rounded border-gray-300 text-indigo-500 focus:ring-indigo-400"
                        />
                        <label :for="'tim-' + unit.kode_unit + '-' + tim.id" class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer">
                          {{ tim.nama_tim }}
                        </label>
                      </div>
                    </div>
                  </div>
                  </div><!-- end overflow scroll -->
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Dipilih: {{ selectedPicUnits.length > 0 ? selectedPicUnits.length + ' unit/tim' : 'Belum ada yang dipilih' }}
                </p>
              </label>

              <!-- Indikator -->
              <label class="block text-sm md:col-span-2">
                <span class="mb-1 block font-medium dark:text-gray-200">Indikator <span class="text-red-500">*</span></span>
                <textarea 
                  v-model="formAddInd.indikator" 
                  rows="4" 
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" 
                  placeholder="Deskripsi indikator..."
                ></textarea>
              </label>

              <!-- Standar, Satuan, (Satuan Waktu jika rata-rata), Berlaku TW — 1 baris -->
              <div class="block text-sm md:col-span-2">
                <div :class="['grid grid-cols-1 gap-4', formAddInd.satuan === 'rata-rata' ? 'md:grid-cols-4' : 'md:grid-cols-3']">
                  <!-- Standar -->
                  <label class="block">
                    <span class="mb-1 block font-medium dark:text-gray-200">Standar <span class="text-red-500">*</span></span>
                    <input
                      v-model="formAddInd.standar"
                      type="text"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                      placeholder="cth: ≥ 90%"
                    />
                  </label>

                  <!-- Satuan -->
                  <label class="block">
                    <span class="mb-1 block font-medium dark:text-gray-200">Satuan <span class="text-red-500">*</span></span>
                    <select
                      v-model="formAddInd.satuan"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                      <option value="persen">Persen (%)</option>
                      <option value="permil">Permil (‰)</option>
                      <option value="rata-rata">Rata-rata</option>
                      <option value="kejadian">Kejadian</option>
                      <option value="peserta">Peserta</option>
                      <option value="dokumen">Dokumen/Laporan</option>
                    </select>
                  </label>

                  <!-- Satuan Waktu (hanya muncul jika satuan = rata-rata) -->
                  <label v-if="formAddInd.satuan === 'rata-rata'" class="block">
                    <span class="mb-1 block font-medium dark:text-gray-200">Satuan Waktu <span class="text-red-500">*</span></span>
                    <select
                      v-model="formAddInd.satuan_waktu"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                      <option value="">Pilih satuan waktu...</option>
                      <option value="hari">Hari</option>
                      <option value="jam">Jam</option>
                      <option value="menit">Menit</option>
                    </select>
                  </label>

                  <!-- Berlaku di TW (selalu tampil, posisi terakhir) -->
                  <label class="block">
                    <span class="mb-1 block font-medium dark:text-gray-200">Berlaku di TW <span class="text-red-500">*</span></span>
                    <select
                      v-model="formAddTwOption"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                      <option value="all">Semua TW</option>
                      <option value="1">TW 1</option>
                      <option value="2">TW 2</option>
                      <option value="3">TW 3</option>
                      <option value="4">TW 4</option>
                    </select>
                  </label>
                </div>
              </div>

              <!-- Numerator -->
              <label class="block text-sm">
                <span class="mb-1 block font-medium dark:text-gray-200">Numerator <span class="text-red-500">*</span></span>
                <textarea 
                  v-model="formAddInd.numerator" 
                  rows="3" 
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                  placeholder="Pembilang..."
                ></textarea>
              </label>

              <!-- Denominator -->
              <label class="block text-sm">
                <span class="mb-1 block font-medium dark:text-gray-200">Denominator <span class="text-red-500">*</span></span>
                <textarea 
                  v-model="formAddInd.denominator" 
                  rows="3" 
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                  placeholder="Penyebut..."
                ></textarea>
              </label>
            </div>

            <div class="mt-6 flex justify-end gap-2">
              <button 
                class="rounded-lg border border-gray-300 px-5 py-2 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800 dark:text-gray-100" 
                @click="showAddIndicator=false"
              >
                Batal
              </button>
              <button
                class="rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                @click="addIndicatorSave"
                :disabled="isSubmitting"
              >
                {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Modal View -->
      <Teleport to="body">
        <div v-if="showView" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showView=false">
          <div class="w-full max-w-6xl rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
              <h4 class="text-lg font-semibold dark:text-gray-100">Detail Bagian/Unit</h4>
              <button @click="showView=false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

              <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <p class="text-sm text-gray-500 dark:text-gray-400">Nama Unit</p>
                <p class="font-medium dark:text-gray-100">{{ viewItem?.nama_unit }}</p>
              </div>

              <!-- Dropdown Tim/Unit - hanya tampil jika unit memiliki tim -->
              <div v-if="viewAvailableTimUnits.length > 0" class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <label class="block text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-200">Pilih Tim/Unit</span>
                <select
                  v-model="viewTimUnit"
                  @change="loadIndicatorFor(viewItem?.nama_unit ?? '', viewTimUnit)"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                  <option disabled value="">-- Pilih Tim/Unit --</option>
                  <option v-for="tim in viewAvailableTimUnits" :key="tim.id" :value="tim.nama_tim">
                    {{ tim.nama_tim }}
                  </option>
                </select>
              </label>
              </div>
            </div>

            <!-- Tim/Unit Dropdown - hanya tampil jika ada tim units -->
            <!-- <div v-if="viewAvailableTimUnits.length > 0" class="mb-4">
              <label class="block text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-200">Pilih Tim/Unit</span>
                <select 
                  v-model="viewTimUnit" 
                  @change="loadIndicatorFor(viewItem?.nama_unit ?? '', viewTimUnit)"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                  <option disabled value="">-- Pilih Tim/Unit --</option>
                  <option v-for="tim in viewAvailableTimUnits" :key="tim.id" :value="tim.nama_tim">
                    {{ tim.nama_tim }}
                  </option>
                </select>
              </label>
            </div> -->

            <!-- Tombol tidak diperlukan lagi karena indikator otomatis ter-load -->

            <!-- Tabel List Indikator -->
            <div v-if="viewIndikatorList.length > 0" class="mt-4">
              <h5 class="text-md font-semibold mb-3 dark:text-gray-100">Daftar Indikator</h5>
              <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 dark:bg-gray-800">
  <tr>
    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">No</th>
    <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Jenis</th>
    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Indikator</th>
    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Standar</th>
    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">PIC</th>
    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Numerator</th>
    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Denominator</th>
    <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Aksi</th>
  </tr>
</thead>
<tbody class="divide-y divide-gray-200 dark:divide-gray-700">
  <tr v-for="(item, index) in viewIndikatorList" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ index + 1 }}</td>
    <td class="px-4 py-3 text-center">
      <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-semibold whitespace-nowrap"
        :class="{
          'bg-blue-100 text-blue-700': item.jenis_indikator === 'INM',
          'bg-green-100 text-green-700': item.jenis_indikator === 'SPM',
          'bg-purple-100 text-purple-700': item.jenis_indikator === 'PRIORITAS',
          'bg-orange-100 text-orange-700': item.jenis_indikator === 'IMUT_RS',
          'bg-teal-100 text-teal-700': item.jenis_indikator === 'IMUT_UNIT',
          'bg-gray-100 text-gray-600': !['INM','SPM','PRIORITAS','IMUT_RS','IMUT_UNIT'].includes(item.jenis_indikator),
        }">
        {{ item.jenis_indikator === 'IMUT_RS' ? 'IMUT RS' : item.jenis_indikator === 'IMUT_UNIT' ? 'IMUT UNIT' : item.jenis_indikator }}
      </span>
    </td>
    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
      <div class="max-w-md">{{ item.indikator }}</div>
    </td>
    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ item.standar }}</td>
    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
      <!-- Tampilkan hanya PIC yang relevan dengan unit/tim yang sedang dilihat -->
      {{ getRelevantPicForView(item) }}
    </td>
    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
      <div class="max-w-xs">{{ item.numerator }}</div>
    </td>
    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
      <div class="max-w-xs">{{ item.denominator }}</div>
    </td>
    <td class="px-4 py-3 text-center">
      <button
        @click="toggleActiveIndikator(item)"
        :class="item.is_active ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'"
        class="px-3 py-1 rounded text-white text-xs font-medium transition-colors"
      >
        {{ item.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
      </button>
    </td>
  </tr>
</tbody>
                </table>
              </div>
            </div>

            <!-- Pesan jika belum ada data -->
            <div v-else-if="viewTimUnit || (viewAvailableTimUnits.length === 0 && viewIndikatorList.length === 0)" class="text-center py-8 text-gray-500 dark:text-gray-400">
              <p>Belum ada indikator untuk unit/tim ini</p>
            </div>

            <div class="mt-6 flex justify-end">
              <button 
                class="rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md" 
                @click="showView=false"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Modal Edit -->
      <Teleport to="body">
        <div v-if="showEdit" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showEdit=false">
          <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
              <h4 class="text-lg font-semibold dark:text-gray-100">Edit Indikator</h4>
              <button @click="showEdit=false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
              <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Unit: {{ editUnitName }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Tim/Unit Dropdown - hanya tampil jika ada tim units -->
              <label v-if="editAvailableTimUnits.length > 0" class="block text-sm md:col-span-2">
                <span class="mb-1 block font-medium dark:text-gray-200">Pilih Tim/Unit</span>
                <select 
                  v-model="editTimUnit" 
                  @change="loadIndicatorForEdit(editUnitName, editTimUnit, false)"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                  <option disabled value="">-- Pilih Tim/Unit --</option>
                  <option v-for="tim in editAvailableTimUnits" :key="tim.id" :value="tim.nama_tim">
                    {{ tim.nama_tim }}
                  </option>
                </select>
              </label>

              <!-- Unit tanpa tim: loading state -->
              <div v-if="editAvailableTimUnits.length === 0 && editListLoading" class="md:col-span-2 text-center py-6 text-gray-400 dark:text-gray-500">
                <svg class="mx-auto mb-2 h-5 w-5 animate-spin text-amber-500" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <p class="text-sm">Memuat indikator...</p>
              </div>

              <!-- Unit tanpa tim: dropdown pilih indikator -->
              <label v-else-if="editAvailableTimUnits.length === 0 && editIndikatorList.length > 0" class="block text-sm md:col-span-2">
                <span class="mb-1 block font-medium dark:text-gray-200">Pilih Indikator yang akan di-edit</span>
                <select
                  v-model="editIndikatorId"
                  @change="onEditIndikatorChange(editIndikatorId)"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                  <option disabled :value="null">-- Pilih Indikator --</option>
                  <option v-for="ind in editIndikatorList" :key="ind.id" :value="ind.id">
                    {{ ind.indikator }}
                  </option>
                </select>
              </label>

              <!-- Unit tanpa tim: tidak ada indikator aktif -->
              <div v-else-if="editAvailableTimUnits.length === 0 && !editListLoading && editIndikatorList.length === 0" class="md:col-span-2 text-center py-8 text-gray-400 dark:text-gray-500">
                <p class="text-sm">Belum ada indikator aktif untuk unit ini</p>
              </div>

              <!-- Unit dengan tim: dropdown pilih indikator (muncul setelah tim dipilih) -->
              <label v-if="editAvailableTimUnits.length > 0 && editIndikatorList.length > 0" class="block text-sm md:col-span-2">
                <span class="mb-1 block font-medium dark:text-gray-200">Pilih Indikator yang akan di-edit</span>
                <select
                  v-model="editIndikatorId"
                  @change="onEditIndikatorChange(editIndikatorId)"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                  <option disabled :value="null">-- Pilih Indikator --</option>
                  <option v-for="ind in editIndikatorList" :key="ind.id" :value="ind.id">
                    {{ ind.indikator.substring(0, 80) }}{{ ind.indikator.length > 80 ? '...' : '' }}
                  </option>
                </select>
              </label>

              <template v-if="editIndikatorId">
                <!-- Jenis Indikator (READ ONLY) -->
                <div class="block text-sm md:col-span-2">
                  <span class="mb-1 block font-medium dark:text-gray-200">Jenis Indikator</span>
                  <div class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    {{ editJenisIndikator ? (jenisLabel[editJenisIndikator] || editJenisIndikator) : '-' }}
                  </div>
                </div>

                <!-- Indikator (EDITABLE) -->
                <label class="block text-sm md:col-span-2">
                  <span class="mb-1 block font-medium dark:text-gray-200">Indikator <span class="text-amber-600">*</span></span>
                  <textarea 
                    v-model="formEditInd.indikator" 
                    rows="4" 
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    placeholder="Deskripsi indikator..."
                  ></textarea>
                </label>

                <!-- Standar, Satuan, (Satuan Waktu), Berlaku TW — 1 baris -->
                <div class="block text-sm md:col-span-2">
                  <div :class="['grid grid-cols-1 gap-4', formEditInd.satuan === 'rata-rata' ? 'md:grid-cols-4' : 'md:grid-cols-3']">
                    <!-- Standar -->
                    <label class="block">
                      <span class="mb-1 block font-medium dark:text-gray-200">Standar <span class="text-amber-600">*</span></span>
                      <input
                        v-model="formEditInd.standar"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        placeholder="cth: ≥ 90%"
                      />
                    </label>

                    <!-- Satuan -->
                    <label class="block">
                      <span class="mb-1 block font-medium dark:text-gray-200">Satuan <span class="text-amber-600">*</span></span>
                      <select
                        v-model="formEditInd.satuan"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                      >
                        <option value="persen">Persen (%)</option>
                        <option value="permil">Permil (‰)</option>
                        <option value="rata-rata">Rata-rata</option>
                        <option value="kejadian">Kejadian</option>
                        <option value="peserta">Peserta</option>
                        <option value="dokumen">Dokumen/Laporan</option>
                      </select>
                    </label>

                    <!-- Satuan Waktu (hanya jika rata-rata) -->
                    <label v-if="formEditInd.satuan === 'rata-rata'" class="block">
                      <span class="mb-1 block font-medium dark:text-gray-200">Satuan Waktu <span class="text-amber-600">*</span></span>
                      <select
                        v-model="formEditInd.satuan_waktu"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                      >
                        <option value="">Pilih satuan waktu...</option>
                        <option value="hari">Hari</option>
                        <option value="jam">Jam</option>
                        <option value="menit">Menit</option>
                      </select>
                    </label>

                    <!-- Berlaku di TW -->
                    <label class="block">
                      <span class="mb-1 block font-medium dark:text-gray-200">Berlaku di TW <span class="text-amber-600">*</span></span>
                      <select
                        v-model="editTwOption"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                      >
                        <option value="all">Semua TW</option>
                        <option value="1">TW 1</option>
                        <option value="2">TW 2</option>
                        <option value="3">TW 3</option>
                        <option value="4">TW 4</option>
                      </select>
                    </label>
                  </div>
                </div>

                <!-- PIC (read-only) -->
                <label class="block text-sm md:col-span-2">
                  <span class="mb-1 block font-medium dark:text-gray-200">PIC</span>
                  <input
                    :value="computedPicEdit"
                    type="text"
                    disabled
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 opacity-80 cursor-not-allowed dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                  />
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PIC tidak dapat diubah</p>
                </label>

                <!-- Numerator (editable) -->
                <label class="block text-sm">
                  <span class="mb-1 block font-medium dark:text-gray-200">Numerator <span class="text-amber-600">*</span></span>
                  <textarea 
                    v-model="formEditInd.numerator" 
                    rows="3" 
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    placeholder="Pembilang..."
                  ></textarea>
                </label>

                <!-- Denominator (editable) -->
                <label class="block text-sm">
                  <span class="mb-1 block font-medium dark:text-gray-200">Denominator <span class="text-amber-600">*</span></span>
                  <textarea 
                    v-model="formEditInd.denominator" 
                    rows="3" 
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    placeholder="Penyebut..."
                  ></textarea>
                </label>
              </template>

              <!-- Unit dengan tim: belum ada indikator untuk tim yang dipilih -->
              <div v-else-if="editAvailableTimUnits.length > 0 && editIndikatorList.length === 0 && editTimUnit" class="md:col-span-2 text-center py-8 text-gray-500 dark:text-gray-400">
                <p>Belum ada indikator untuk tim ini</p>
              </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
              <button 
                class="rounded-lg border border-gray-300 px-5 py-2 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800 dark:text-gray-100" 
                @click="showEdit=false"
              >
                Batal
              </button>
              <button 
                class="rounded-lg bg-amber-500 px-5 py-2 text-white hover:bg-amber-600 shadow-md" 
                @click="editSave"
              >
                Simpan
              </button>
            </div>
          </div>
        </div>
      </Teleport>
      <!-- Modal Rekap Seluruh Indikator -->
      <Teleport to="body">
        <div v-if="showRekap" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" @click.self="showRekap=false">
          <div class="flex flex-col w-full max-w-7xl rounded-xl bg-white shadow-2xl dark:bg-gray-900" style="max-height:90vh">

            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
              <div>
                <h4 class="text-lg font-bold text-gray-800 dark:text-gray-100">Rekap Seluruh Indikator Mutu</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">RSUD Tarakan — semua unit kerja</p>
              </div>
              <button @click="showRekap=false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none dark:hover:text-gray-200">&times;</button>
            </div>

            <!-- Summary chips -->
            <div class="flex flex-wrap gap-2 px-6 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                Total: {{ rekapSummary.total }}
              </span>
              <span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-semibold text-purple-700">INM: {{ rekapSummary.inm }}</span>
              <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">SPM: {{ rekapSummary.spm }}</span>
              <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">PRIORITAS RS: {{ rekapSummary.prioritas }}</span>
              <span class="rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-700">IMUT Unit: {{ rekapSummary.imut_unit }}</span>
              <span class="ml-auto rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Aktif: {{ rekapSummary.aktif }}</span>
              <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">Nonaktif: {{ rekapSummary.nonaktif }}</span>
            </div>

            <!-- Filter bar -->
            <div class="flex flex-wrap gap-3 px-6 py-3 border-b border-gray-100 dark:border-gray-700">
              <!-- Search -->
              <div class="relative flex-1 min-w-[200px]">
                <input
                  v-model="rekapSearch"
                  type="text"
                  placeholder="Cari indikator / unit..."
                  class="w-full rounded-lg border border-gray-300 px-3 py-1.5 pl-8 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
              <!-- Filter Jenis -->
              <select v-model="rekapFilterJenis" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Semua Jenis</option>
                <option value="INM">INDIKATOR NASIONAL MUTU</option>
                <option value="SPM">INDIKATOR STANDAR PELAYANAN MINIMAL</option>
                <option value="PRIORITAS">INDIKATOR MUTU PRIORITAS RS</option>
                <option value="IMUT_UNIT">INDIKATOR MUTU UNIT</option>
              </select>
              <!-- Filter Status -->
              <select v-model="rekapFilterStatus" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
              </select>
              <span class="self-center text-xs text-gray-500 dark:text-gray-400">{{ rekapFiltered.length }} indikator</span>
            </div>

            <!-- Table -->
            <div class="flex-1 overflow-auto px-6 py-3">
              <!-- Loading -->
              <div v-if="rekapLoading" class="flex items-center justify-center py-16 text-gray-500 dark:text-gray-400">
                <svg class="mr-2 h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                Memuat data...
              </div>

              <!-- Empty -->
              <div v-else-if="rekapFiltered.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="mb-2 h-12 w-12 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm">Tidak ada data indikator</p>
              </div>

              <!-- Table data -->
              <table v-else class="w-full text-sm border-collapse">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                  <tr>
                    <th class="border border-gray-200 px-3 py-2.5 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-10">No</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-24">Jenis</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-40">Unit / Tim</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700">Judul Indikator</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-52">Numerator</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-52">Denominator</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-20">Standar</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-20">Satuan</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-24">Berlaku TW</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-20">Status</th>
                    <th class="border border-gray-200 px-3 py-2.5 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 dark:border-gray-700 w-20">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(ind, idx) in rekapPaginated"
                    :key="ind.indikator"
                    class="border-b border-gray-100 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50 transition-colors"
                    :class="{ 'opacity-60': ind.allInactive }"
                  >
                    <td class="border border-gray-200 px-3 py-2 text-center text-xs text-gray-500 dark:border-gray-700">{{ (rekapPage - 1) * rekapPerPage + idx + 1 }}</td>
                    <td class="border border-gray-200 px-3 py-2 text-center dark:border-gray-700">
                      <span class="inline-block rounded px-1.5 py-0.5 text-[11px] font-semibold" :class="jenisBadgeClass[ind.jenis_indikator] ?? 'bg-gray-100 text-gray-600'">
                        {{ jenisLabel[ind.jenis_indikator] ?? ind.jenis_indikator }}
                      </span>
                      <!-- INM: checkbox termasuk Prioritas RS -->
                      <div v-if="ind.jenis_indikator === 'INM'" class="mt-1.5 flex items-center justify-center gap-1">
                        <input
                          type="checkbox"
                          :id="`prio-${ind.indikator}`"
                          :checked="ind.is_prioritas"
                          @change="togglePrioritas(ind)"
                          class="w-3 h-3 rounded accent-purple-600 cursor-pointer"
                        />
                        <label :for="`prio-${ind.indikator}`" class="text-[10px] text-purple-600 dark:text-purple-400 cursor-pointer leading-none whitespace-nowrap">+ Prioritas</label>
                      </div>
                      <!-- Badge tambahan jika is_prioritas -->
                      <div v-if="ind.jenis_indikator === 'INM' && ind.is_prioritas" class="mt-1">
                        <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">PRIORITAS RS</span>
                      </div>
                    </td>
                    <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">
                      <!-- Warning duplikat -->
                      <div v-if="ind.hasDuplicate" class="mb-1.5 flex items-center gap-1 rounded bg-yellow-50 px-1.5 py-0.5 border border-yellow-200">
                        <span class="text-[10px] text-yellow-700 font-semibold">⚠ Ada duplikat</span>
                      </div>
                      <div v-for="(u, ui) in ind.units" :key="u.id" :class="(ui as number) > 0 ? 'mt-1 pt-1 border-t border-gray-100 dark:border-gray-700' : ''">
                        <div class="flex items-start justify-between gap-1 group">
                          <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-800 dark:text-gray-200 leading-snug">{{ u.nama_unit }}</p>
                            <p v-if="u.tim_unit" class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ u.tim_unit }}</p>
                          </div>
                          <!-- Tombol hapus unit -->
                          <button
                            @click.stop="deleteIndikatorUnit(u, ind.indikator)"
                            title="Hapus indikator untuk unit ini"
                            class="flex-shrink-0 opacity-0 group-hover:opacity-100 w-5 h-5 flex items-center justify-center rounded text-red-400 hover:text-red-600 hover:bg-red-50 transition-all text-[11px]"
                          >✕</button>
                        </div>
                      </div>
                    </td>
                    <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">
                      <p class="text-xs text-gray-800 dark:text-gray-200 leading-snug">{{ ind.indikator }}</p>
                    </td>
                    <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">
                      <p class="text-xs text-gray-600 dark:text-gray-400 leading-snug line-clamp-3">{{ ind.numerator || '-' }}</p>
                    </td>
                    <td class="border border-gray-200 px-3 py-2 dark:border-gray-700">
                      <p class="text-xs text-gray-600 dark:text-gray-400 leading-snug line-clamp-3">{{ ind.denominator || '-' }}</p>
                    </td>
                    <td class="border border-gray-200 px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 dark:border-gray-700">{{ ind.standar }}</td>
                    <td class="border border-gray-200 px-3 py-2 text-center text-xs text-gray-600 dark:text-gray-400 dark:border-gray-700 capitalize">
                      {{ ind.satuan }}{{ ind.satuan_waktu ? ' / ' + ind.satuan_waktu : '' }}
                    </td>
                    <td class="border border-gray-200 px-3 py-2 text-center dark:border-gray-700">
                      <div class="flex flex-wrap justify-center gap-0.5">
                        <span v-for="tw in ([1,2,3,4] as number[])" :key="tw"
                          class="inline-block rounded px-1 py-0.5 text-[10px] font-semibold"
                          :class="(ind.berlaku_tw ?? [1,2,3,4]).includes(tw) ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-300'"
                        >TW{{ tw }}</span>
                      </div>
                    </td>
                    <td class="border border-gray-200 px-3 py-2 text-center dark:border-gray-700">
                      <span v-if="ind.allActive" class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold bg-emerald-100 text-emerald-700">Aktif</span>
                      <span v-else-if="ind.allInactive" class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold bg-red-100 text-red-600">Nonaktif</span>
                      <span v-else class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold bg-yellow-100 text-yellow-700">Sebagian aktif</span>
                    </td>
                    <!-- Aksi -->
                    <td class="border border-gray-200 px-2 py-2 text-center dark:border-gray-700">
                      <div class="flex items-center justify-center gap-1">
                        <button
                          @click="openRekapEdit(ind)"
                          title="Edit indikator"
                          class="inline-flex items-center justify-center w-7 h-7 rounded hover:bg-blue-50 text-blue-600 dark:hover:bg-blue-900/30 transition-colors"
                        >✏️</button>
                        <button
                          @click="toggleActiveFromRekap(ind)"
                          :title="ind.allInactive ? 'Aktifkan indikator' : 'Nonaktifkan indikator'"
                          class="inline-flex items-center justify-center w-7 h-7 rounded transition-colors"
                          :class="ind.allInactive ? 'hover:bg-emerald-50 text-emerald-600 dark:hover:bg-emerald-900/30' : 'hover:bg-red-50 text-red-500 dark:hover:bg-red-900/30'"
                        >{{ ind.allInactive ? '▶️' : '⏸️' }}</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Footer: pagination + tutup -->
            <div class="flex items-center justify-between border-t border-gray-200 px-6 py-3 dark:border-gray-700">
              <!-- Pagination -->
              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <button
                  @click="rekapPage--"
                  :disabled="rekapPage <= 1"
                  class="rounded border border-gray-300 px-2.5 py-1 text-xs hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed dark:border-gray-600 dark:hover:bg-gray-700"
                >&#8249;</button>
                <span class="text-xs">Hal. {{ rekapPage }} / {{ rekapTotalPages }}</span>
                <button
                  @click="rekapPage++"
                  :disabled="rekapPage >= rekapTotalPages"
                  class="rounded border border-gray-300 px-2.5 py-1 text-xs hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed dark:border-gray-600 dark:hover:bg-gray-700"
                >&#8250;</button>
                <span class="ml-2 text-xs text-gray-400">{{ rekapFiltered.length }} total</span>
              </div>
              <button @click="showRekap=false" class="rounded-lg border border-gray-300 px-5 py-2 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800 dark:text-gray-200">
                Tutup
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Modal Edit Rekap (z lebih tinggi dari rekap modal) -->
      <Teleport to="body">
        <div v-if="showRekapEdit" class="fixed inset-0 z-[10000] grid place-items-center bg-black/50 p-4" @click.self="showRekapEdit=false">
          <div class="w-full max-w-4xl rounded-xl bg-white shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <div>
                <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100">Edit Indikator Mutu</h4>
                <p v-if="rekapEditItem" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Perubahan berlaku untuk semua unit yang terpilih
                </p>
              </div>
              <button @click="showRekapEdit=false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none dark:hover:text-gray-200">&times;</button>
            </div>

            <!-- Form -->
            <div class="px-6 py-4 space-y-4">

              <!-- Jenis Indikator (read-only) -->
              <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Indikator</label>
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold" :class="jenisBadgeClass[rekapEditItem?.jenis_indikator] ?? 'bg-gray-100 text-gray-600'">
                    {{ jenisLabel[rekapEditItem?.jenis_indikator] ?? rekapEditItem?.jenis_indikator }}
                  </span>
                  <span class="text-xs text-gray-400 dark:text-gray-500">— tidak dapat diubah</span>
                </div>
              </div>

              <!-- PIC (Pilih Unit/Tim) — multi-select checkbox -->
              <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  PIC (Unit/Tim) <span class="text-red-500">*</span>
                </label>
                <div class="border border-gray-300 rounded-lg dark:border-gray-600">
                  <div class="px-3 pt-2 pb-1 border-b border-gray-200 dark:border-gray-700">
                    <input
                      v-model="rekapEditPicSearch"
                      type="text"
                      placeholder="Cari unit atau tim..."
                      class="w-full rounded border border-gray-200 px-2 py-1 text-sm focus:border-teal-400 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                  </div>
                  <div class="p-3 max-h-48 overflow-y-auto">
                    <p v-if="rekapEditFilteredUnits.length === 0" class="text-xs text-gray-400 text-center py-2">Tidak ada hasil</p>
                    <div v-for="unit in rekapEditFilteredUnits" :key="unit.id" class="mb-3">
                      <!-- Unit checkbox -->
                      <div class="flex items-center mb-1">
                        <input
                          type="checkbox"
                          :id="'redit-unit-' + unit.kode_unit"
                          :value="unit.kode_unit"
                          v-model="rekapEditSelectedUnits"
                          class="mr-2 h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
                        />
                        <label :for="'redit-unit-' + unit.kode_unit" class="text-sm font-medium dark:text-gray-200 cursor-pointer">
                          {{ unit.nama_unit }}
                        </label>
                      </div>
                      <!-- Tim units (nested) -->
                      <div v-if="unit.tim_units && unit.tim_units.length > 0" class="ml-6 mt-1 space-y-1">
                        <div v-for="tim in unit.tim_units" :key="tim.id" class="flex items-center">
                          <input
                            type="checkbox"
                            :id="'redit-tim-' + unit.kode_unit + '-' + tim.id"
                            :value="unit.kode_unit + '|' + tim.nama_tim"
                            v-model="rekapEditSelectedUnits"
                            class="mr-2 h-3.5 w-3.5 rounded border-gray-300 text-teal-500 focus:ring-teal-400"
                          />
                          <label :for="'redit-tim-' + unit.kode_unit + '-' + tim.id" class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer">
                            {{ tim.nama_tim }}
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Dipilih: {{ rekapEditSelectedUnits.length > 0 ? rekapEditSelectedUnits.length + ' unit/tim' : 'Belum ada yang dipilih' }}
                </p>
              </div>

              <!-- Judul Indikator -->
              <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Judul Indikator <span class="text-red-500">*</span></label>
                <textarea v-model="rekapEditForm.indikator" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="Deskripsi indikator..."></textarea>
              </div>

              <!-- Standar, Satuan, Satuan Waktu, Berlaku TW -->
              <div :class="['grid grid-cols-1 gap-3', rekapEditForm.satuan === 'rata-rata' ? 'md:grid-cols-4' : 'md:grid-cols-3']">
                <div>
                  <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Standar <span class="text-red-500">*</span></label>
                  <input v-model="rekapEditForm.standar" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="cth: ≥ 90%" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Satuan <span class="text-red-500">*</span></label>
                  <select v-model="rekapEditForm.satuan" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    <option value="persen">Persen (%)</option>
                    <option value="permil">Permil (‰)</option>
                    <option value="rata-rata">Rata-rata</option>
                    <option value="kejadian">Kejadian</option>
                    <option value="peserta">Peserta</option>
                    <option value="dokumen">Dokumen/Laporan</option>
                  </select>
                </div>
                <div v-if="rekapEditForm.satuan === 'rata-rata'">
                  <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Satuan Waktu <span class="text-red-500">*</span></label>
                  <select v-model="rekapEditForm.satuan_waktu" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    <option value="">Pilih satuan waktu...</option>
                    <option value="hari">Hari</option>
                    <option value="jam">Jam</option>
                    <option value="menit">Menit</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Berlaku Triwulan</label>
                  <select v-model="rekapEditTwOption" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    <option value="all">Semua TW</option>
                    <option value="1">TW 1</option>
                    <option value="2">TW 2</option>
                    <option value="3">TW 3</option>
                    <option value="4">TW 4</option>
                  </select>
                </div>
              </div>

              <!-- Numerator & Denominator -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Numerator <span class="text-red-500">*</span></label>
                  <textarea v-model="rekapEditForm.numerator" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="Pembilang..."></textarea>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Denominator <span class="text-red-500">*</span></label>
                  <textarea v-model="rekapEditForm.denominator" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="Penyebut..."></textarea>
                </div>
              </div>

            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
              <button @click="showRekapEdit=false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800 dark:text-gray-200">Batal</button>
              <button
                @click="rekapEditSave"
                :disabled="rekapEditSaving"
                class="rounded-lg bg-teal-600 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
              >{{ rekapEditSaving ? 'Menyimpan...' : 'Simpan' }}</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AppLayout>
</template>

<style scoped>
th, td {
  border: 1px solid #e5e7eb;
}

thead th {
  position: sticky;
  top: 0;
  z-index: 10;
  box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
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
</style>