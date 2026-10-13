<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { ClipboardList, Plus, Eye, Pencil, Trash2, CheckCircle, XCircle, ChevronLeft, ChevronRight, Activity } from 'lucide-vue-next';

interface IndikatorAktif {
  id: number;
  indikator: string;
  tim_unit: string | null;
  kode_unit: string;
  jenis_indikator: string | null;
  numerator: string | null;
  denominator: string | null;
  standar: string | null;
  standar_tw1: string | null;
  standar_tw2: string | null;
  standar_tw3: string | null;
  standar_tw4: string | null;
  satuan: string | null;
  satuan_waktu: string | null;
  satuan_tw1: string | null;
  satuan_tw2: string | null;
  satuan_tw3: string | null;
  satuan_tw4: string | null;
  pic: string | null;
  pj_nama: string | null;
  kepala_nama: string | null;
  is_active: boolean;
  created_at_ts: number;
  // Fields from original pengajuan (may be null for manually-created indikators)
  dasar_pemikiran: string | null;
  dimensi_mutu: string | null;
  tujuan: string | null;
  definisi_operasional: string | null;
  kriteria_inklusi_eksklusi: string | null;
  metode_pengumpulan_data: string | null;
  sumber_data: string | null;
  instrumen_pengambilan_data: string | null;
  populasi_sampel: string | null;
}

interface Pengajuan {
  id: number;
  user_id: number;
  user_nama: string;
  kode_unit: string;
  nama_tim: string | null;
  judul_indikator: string;
  dasar_pemikiran: string;
  dimensi_mutu: string;
  tujuan: string;
  definisi_operasional: string;
  jenis_indikator: string;
  numerator: string | null;
  denominator: string | null;
  target_tw1: string | null;
  target_tw2: string | null;
  target_tw3: string | null;
  target_tw4: string | null;
  satuan: string | null;
  satuan_waktu: string | null;
  kriteria_inklusi_eksklusi: string | null;
  metode_pengumpulan_data: string;
  sumber_data: string;
  instrumen_pengambilan_data: string;
  populasi_sampel: string | null;
  penanggung_jawab: string;
  skor_a: number | null;
  skor_b: number | null;
  skor_c: number | null;
  skor_d: number | null;
  skor_e: number | null;
  total_skor: number | null;
  status: string;
  catatan_kepala: string | null;
  catatan_admin: string | null;
  reviewed_by_kepala: string | null;
  reviewed_by_admin: string | null;
  reviewed_at_kepala: string | null;
  reviewed_at_admin: string | null;
  indikator_id: number | null;
  original_indikator_id: number | null;
  request_type: string | null;
  alasan_nonaktif: string | null;
  created_at: string;
  created_at_ts: number;
}

interface Cards {
  indikator_aktif: number;
  sedang_pengajuan: number;
  disetujui: number;
  ditolak: number;
}

interface Props {
  pengajuanList: Pengajuan[];
  indikatorList: IndikatorAktif[];
  cards: Cards;
  userRole: string;
  userId: number;
  kodeUnit: string | null;
  namaTim: string | null;
  userName: string;
  kepalaUnitName: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Pengajuan Indikator', href: '/pengajuan-indikator' },
];

const isAdmin = computed(() => props.userRole === 'admin_mutu');
const isKepalaUnit = computed(() => props.userRole === 'kepala_unit');
const isPenilaiPjData = computed(() => props.userRole?.startsWith('penilai_pj_data') ?? false);

// ===== STATUS HELPERS =====
const statusLabel: Record<string, string> = {
  pending_kepala:   'Menunggu Kepala Unit',
  disetujui_kepala: 'Menunggu Komite Mutu',
  ditolak_kepala:   'Ditolak Kepala Unit',
  disetujui:        'Aktif',
  ditolak:          'Ditolak',
};
const statusColor: Record<string, string> = {
  pending_kepala:   'bg-orange-100 text-orange-700 border-orange-200',
  disetujui_kepala: 'bg-blue-100 text-blue-700 border-blue-200',
  ditolak_kepala:   'bg-red-100 text-red-700 border-red-200',
  disetujui:        'bg-green-100 text-green-700 border-green-200',
  ditolak:          'bg-red-100 text-red-700 border-red-200',
};

// ===== SEARCH + PAGINATION =====
const searchQuery = ref('');
const currentPage = ref(1);
const perPage = 10;

// Combined rows: active indikators + pengajuan
type CombinedRow =
  | ({ _type: 'indikator'; hasPendingRevision: boolean; hasPendingNonaktif: boolean } & IndikatorAktif)
  | ({ _type: 'pengajuan' } & Pengajuan);

// IDs of indikators that have a pending REVISION (via original_indikator_id, excluding nonaktif)
const pendingRevisionIdSet = computed(() =>
  new Set(
    props.pengajuanList
      .filter(p => (p.status === 'pending_kepala' || p.status === 'disetujui_kepala')
        && p.original_indikator_id
        && p.request_type !== 'nonaktif')
      .map(p => p.original_indikator_id as number)
  )
);

// Fallback: title+unit keys for older revisions without original_indikator_id
const pendingRevisionTitleKeys = computed(() =>
  new Set(
    props.pengajuanList
      .filter(p => (p.status === 'pending_kepala' || p.status === 'disetujui_kepala')
        && !p.original_indikator_id
        && p.request_type !== 'nonaktif')
      .map(p => p.judul_indikator.trim().toLowerCase() + '|' + p.kode_unit)
  )
);

// IDs of indikators with a pending NONAKTIF request
const pendingNonaktifIdSet = computed(() =>
  new Set(
    props.pengajuanList
      .filter(p => (p.status === 'pending_kepala' || p.status === 'disetujui_kepala')
        && p.original_indikator_id
        && p.request_type === 'nonaktif')
      .map(p => p.original_indikator_id as number)
  )
);

// Set of all active indikator "title|unit" keys (used to badge revision pengajuan rows)
const activeIndikatorTitleKeys = computed(() =>
  new Set(props.indikatorList.map(i => i.indikator.trim().toLowerCase() + '|' + i.kode_unit))
);

const combinedList = computed((): CombinedRow[] => {
  const rows: CombinedRow[] = [];

  // Always show indikator rows — with pending badge flags when applicable
  for (const ind of props.indikatorList) {
    const hasPendingRevision = pendingRevisionIdSet.value.has(ind.id) ||
      pendingRevisionTitleKeys.value.has(ind.indikator.trim().toLowerCase() + '|' + ind.kode_unit);
    const hasPendingNonaktif = pendingNonaktifIdSet.value.has(ind.id);
    rows.push({ _type: 'indikator', hasPendingRevision, hasPendingNonaktif, ...ind });
  }

  for (const p of props.pengajuanList) {
    // Skip approved/completed submissions — already shown as indikator row
    if (p.status === 'disetujui') continue;

    // Revision & nonaktif pengajuan: penilai sees the indikator row with badge — don't show pengajuan row again
    // Kepala & admin still see the pengajuan row so they can approve/reject
    const isLinkedToIndikator = p.original_indikator_id || p.request_type;
    if (isLinkedToIndikator && isPenilaiPjData.value) continue;

    rows.push({ _type: 'pengajuan', ...p });
  }

  // Sort all combined rows newest first
  rows.sort((a, b) => ((b as any).created_at_ts ?? 0) - ((a as any).created_at_ts ?? 0));

  return rows;
});

const filteredList = computed(() => {
  const q = searchQuery.value.toLowerCase();
  if (!q) return combinedList.value;
  return combinedList.value.filter(row => {
    if (row._type === 'indikator') {
      return row.indikator.toLowerCase().includes(q) ||
        (row.tim_unit ?? '').toLowerCase().includes(q) ||
        'aktif'.includes(q);
    }
    return row.judul_indikator.toLowerCase().includes(q) ||
      (row.nama_tim ?? '').toLowerCase().includes(q) ||
      row.user_nama.toLowerCase().includes(q) ||
      statusLabel[row.status]?.toLowerCase().includes(q);
  });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredList.value.length / perPage)));
const paginatedList = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return filteredList.value.slice(start, start + perPage);
});

function goPage(p: number) {
  currentPage.value = Math.min(Math.max(1, p), totalPages.value);
}

// Reset page on search
function onSearch() {
  currentPage.value = 1;
}

// ===== BLANK FORM =====
const blankForm = () => ({
  judul_indikator: '',
  dasar_pemikiran: '',
  dimensi_mutu: '',
  tujuan: '',
  definisi_operasional: '',
  jenis_indikator: '',
  numerator: '',
  denominator: '',
  target_tw1: '',
  target_tw2: '',
  target_tw3: '',
  target_tw4: '',
  satuan: '',
  satuan_waktu: '',
  kriteria_inklusi_eksklusi: '',
  metode_pengumpulan_data: '',
  sumber_data: '',
  instrumen_pengambilan_data: '',
  populasi_sampel: '',
  penanggung_jawab: '',
  skor_a: null as number | null,
  skor_b: null as number | null,
  skor_c: null as number | null,
  skor_d: null as number | null,
  skor_e: null as number | null,
  total_skor: null as number | null,
  original_indikator_id: null as number | null,
});

// ===== MODAL: TAMBAH =====
const showAdd = ref(false);
const formAdd = ref(blankForm());
const addErrors = ref<Record<string, string>>({});
const submittingAdd = ref(false);

// openAdd is now triggered after assessment, not directly
function openAddFromAssessment(judulPrefill: string, skors: { a: number; b: number; c: number; d: number; e: number; total: number }) {
  showSkorInForm.value = false;
  formAdd.value = {
    ...blankForm(),
    penanggung_jawab: props.namaTim ?? '',
    judul_indikator: judulPrefill,
    skor_a: skors.a,
    skor_b: skors.b,
    skor_c: skors.c,
    skor_d: skors.d,
    skor_e: skors.e,
    total_skor: skors.total,
  };
  addErrors.value = {};
  showAdd.value = true;
}

// ===== ASSESSMENT MODAL =====
interface Candidate {
  _id: number;
  judul: string;
  skor_a: number;
  skor_b: number;
  skor_c: number;
  skor_d: number;
  skor_e: number;
  total: number;
}

const showAssessment = ref(false);
const assessmentStep = ref<'add' | 'list'>('add');
const assessmentCandidates = ref<Candidate[]>([]);
const assessmentError = ref('');
let _cid = 0;

const assessmentFormBlank = () => ({ judul: '', skor_a: '' as string | number, skor_b: '' as string | number, skor_c: '' as string | number, skor_d: '' as string | number, skor_e: '' as string | number });
const assessmentForm = ref(assessmentFormBlank());

const assessmentCriteria = [
  { key: 'skor_a', label: 'a. High Risk Process', desc: 'Proses berisiko tinggi yang berpotensi membahayakan pasien' },
  { key: 'skor_b', label: 'b. High Volume',        desc: 'Proses yang terjadi dengan frekuensi tinggi / jumlah kasus banyak' },
  { key: 'skor_c', label: 'c. High Cost',           desc: 'Proses yang membutuhkan biaya tinggi' },
  { key: 'skor_d', label: 'd. Ketidakpuasan Pasien & Staf', desc: 'Berpotensi menimbulkan ketidakpuasan pasien atau staf' },
  { key: 'skor_e', label: 'e. Sesuai Tujuan Strategis RS',  desc: 'Mendukung visi, misi, dan KPI strategis rumah sakit' },
] as const;

const sortedCandidates = computed(() =>
  [...assessmentCandidates.value].sort((a, b) => b.total - a.total)
);

const hasDuplicateTopScore = computed(() => {
  const s = sortedCandidates.value;
  return s.length >= 2 && s[0].total === s[1].total;
});

function isTopCandidate(c: Candidate) {
  return sortedCandidates.value.length > 0 && c.total === sortedCandidates.value[0].total;
}

function openAssessmentModal() {
  assessmentCandidates.value = [];
  assessmentError.value = '';
  assessmentForm.value = assessmentFormBlank();
  assessmentStep.value = 'add';
  showAssessment.value = true;
}

function addCandidate() {
  const { judul, skor_a, skor_b, skor_c, skor_d, skor_e } = assessmentForm.value;
  if (!judul.trim()) { assessmentError.value = 'Nama indikator wajib diisi.'; return; }
  const scores = [skor_a, skor_b, skor_c, skor_d, skor_e].map(Number);
  if (scores.some(s => !s || s < 1 || s > 5)) { assessmentError.value = 'Semua skor harus diisi (1–5).'; return; }

  const total = scores.reduce((a, b) => a + b, 0);

  // Check duplicate total with existing candidates
  const dup = assessmentCandidates.value.find(c => c.total === total);
  if (dup) {
    assessmentError.value = `Skor total ${total} sudah dimiliki oleh "${dup.judul}". Ubah penilaian salah satu kandidat.`;
    return;
  }

  _cid++;
  assessmentCandidates.value.push({ _id: _cid, judul: judul.trim(), skor_a: scores[0], skor_b: scores[1], skor_c: scores[2], skor_d: scores[3], skor_e: scores[4], total });
  assessmentError.value = '';
  assessmentForm.value = assessmentFormBlank();
  assessmentStep.value = 'list';
}

function removeCandidate(id: number) {
  assessmentCandidates.value = assessmentCandidates.value.filter(c => c._id !== id);
}

function proceedWithCandidate(c: Candidate) {
  if (hasDuplicateTopScore.value) {
    assessmentError.value = 'Masih ada skor tertinggi kembar. Ubah penilaian salah satu kandidat terlebih dahulu.';
    return;
  }
  showAssessment.value = false;
  openAddFromAssessment(c.judul, { a: c.skor_a, b: c.skor_b, c: c.skor_c, d: c.skor_d, e: c.skor_e, total: c.total });
}

// ===== AJUKAN REVISI =====
// showSkorInForm: when true, skor fields are shown inside the Add form (for "Ajukan Revisi")
const showSkorInForm = ref(false);

function openRevisi(ind: IndikatorAktif) {
  showSkorInForm.value = true;
  formAdd.value = {
    ...blankForm(),
    judul_indikator:            ind.indikator,
    dasar_pemikiran:            ind.dasar_pemikiran ?? '',
    dimensi_mutu:               ind.dimensi_mutu ?? '',
    tujuan:                     ind.tujuan ?? '',
    definisi_operasional:       ind.definisi_operasional ?? '',
    jenis_indikator:            ind.jenis_indikator ?? '',
    numerator:                  ind.numerator ?? '',
    denominator:                ind.denominator ?? '',
    target_tw1:                 ind.standar_tw1 ?? '',
    target_tw2:                 ind.standar_tw2 ?? '',
    target_tw3:                 ind.standar_tw3 ?? '',
    target_tw4:                 ind.standar_tw4 ?? '',
    satuan:                     ind.satuan ?? '',
    satuan_waktu:               ind.satuan_waktu ?? '',
    kriteria_inklusi_eksklusi:  ind.kriteria_inklusi_eksklusi ?? '',
    metode_pengumpulan_data:    ind.metode_pengumpulan_data ?? '',
    sumber_data:                ind.sumber_data ?? '',
    instrumen_pengambilan_data: ind.instrumen_pengambilan_data ?? '',
    populasi_sampel:            ind.populasi_sampel ?? '',
    penanggung_jawab:           props.namaTim ?? ind.pic ?? '',
    original_indikator_id:      ind.id,
  };
  addErrors.value = {};
  showAdd.value = true;
}

// Auto-compute total_skor when skor fields change (Ajukan Revisi flow)
watch(
  () => [formAdd.value.skor_a, formAdd.value.skor_b, formAdd.value.skor_c, formAdd.value.skor_d, formAdd.value.skor_e],
  ([a, b, c, d, e]) => {
    if (showSkorInForm.value) {
      const vals = [a, b, c, d, e].filter((v) => v !== null) as number[];
      formAdd.value.total_skor = vals.length === 5 ? vals.reduce((s, v) => s + v, 0) : null;
    }
  }
);

function closeAdd() {
  showAdd.value = false;
  showSkorInForm.value = false;
}

function submitAdd() {
  submittingAdd.value = true;
  addErrors.value = {};
  router.post('/pengajuan-indikator', formAdd.value, {
    onSuccess: () => { closeAdd(); },
    onError: (errors) => { addErrors.value = errors; },
    onFinish: () => { submittingAdd.value = false; },
  });
}

// ===== MODAL: EDIT =====
const showEdit = ref(false);
const editId = ref<number | null>(null);
const formEdit = ref(blankForm());
const editErrors = ref<Record<string, string>>({});
const submittingEdit = ref(false);

function openEdit(item: Pengajuan) {
  editId.value = item.id;
  formEdit.value = {
    judul_indikator:             item.judul_indikator,
    dasar_pemikiran:             item.dasar_pemikiran,
    dimensi_mutu:                item.dimensi_mutu,
    tujuan:                      item.tujuan,
    definisi_operasional:        item.definisi_operasional,
    jenis_indikator:             item.jenis_indikator,
    numerator:                   item.numerator ?? '',
    denominator:                 item.denominator ?? '',
    target_tw1:                  item.target_tw1 ?? '',
    target_tw2:                  item.target_tw2 ?? '',
    target_tw3:                  item.target_tw3 ?? '',
    target_tw4:                  item.target_tw4 ?? '',
    satuan:                      item.satuan ?? '',
    satuan_waktu:                item.satuan_waktu ?? '',
    kriteria_inklusi_eksklusi:   item.kriteria_inklusi_eksklusi ?? '',
    metode_pengumpulan_data:     item.metode_pengumpulan_data,
    sumber_data:                 item.sumber_data,
    instrumen_pengambilan_data:  item.instrumen_pengambilan_data,
    populasi_sampel:             item.populasi_sampel ?? '',
    penanggung_jawab:            item.penanggung_jawab,
    skor_a:                      item.skor_a,
    skor_b:                      item.skor_b,
    skor_c:                      item.skor_c,
    skor_d:                      item.skor_d,
    skor_e:                      item.skor_e,
    total_skor:                  item.total_skor,
  };
  editErrors.value = {};
  showEdit.value = true;
}

function submitEdit() {
  if (!editId.value) return;
  submittingEdit.value = true;
  editErrors.value = {};
  router.put(`/pengajuan-indikator/${editId.value}`, formEdit.value, {
    onSuccess: () => { showEdit.value = false; },
    onError: (errors) => { editErrors.value = errors; },
    onFinish: () => { submittingEdit.value = false; },
  });
}

// ===== MODAL: DETAIL/VIEW =====
const showDetail = ref(false);
const detailItem = ref<(Pengajuan & { _type?: string }) | (IndikatorAktif & { _type: string }) | null>(null);

function openDetail(item: any) {
  detailItem.value = item;
  showDetail.value = true;
}

// Returns TW target: use standar_tw{n} if set; fall back to global standar ONLY when
// none of the four TW-specific fields have any value (legacy single-target indikators).
function getTwTarget(item: any, tw: number): string {
  const val = item[`standar_tw${tw}`];
  if (val) return val;
  const anyTwSet = [1,2,3,4].some(n => item[`standar_tw${n}`]);
  return (!anyTwSet && item.standar) ? item.standar : '—';
}

// ===== MODAL: HAPUS =====
const showDelete = ref(false);
const deleteId = ref<number | null>(null);
const submittingDelete = ref(false);

function openDelete(item: Pengajuan) {
  deleteId.value = item.id;
  showDelete.value = true;
}

function submitDelete() {
  if (!deleteId.value) return;
  submittingDelete.value = true;
  router.delete(`/pengajuan-indikator/${deleteId.value}`, {
    onSuccess: () => { showDelete.value = false; },
    onFinish: () => { submittingDelete.value = false; },
  });
}

// ===== NONAKTIF MODAL (Penilai — replaces direct toggle) =====
const showNonaktifModal = ref(false);
const nonaktifIndikator = ref<IndikatorAktif | null>(null);
const alasanNonaktif = ref('');
const submittingNonaktif = ref(false);

function openNonaktifModal(ind: IndikatorAktif) {
  nonaktifIndikator.value = ind;
  alasanNonaktif.value = '';
  showNonaktifModal.value = true;
}

function submitNonaktif() {
  if (!nonaktifIndikator.value) return;
  submittingNonaktif.value = true;
  router.post(`/pengajuan-indikator/${nonaktifIndikator.value.id}/request-nonaktif`,
    { alasan_nonaktif: alasanNonaktif.value },
    {
      onSuccess: () => { showNonaktifModal.value = false; },
      onFinish: () => { submittingNonaktif.value = false; },
    }
  );
}

// ===== MODAL: REVIEW (Approve/Tolak) =====
const showReview = ref(false);
const reviewItem = ref<Pengajuan | null>(null);
const reviewAction = ref<'approve_kepala' | 'tolak_kepala' | 'approve_admin' | 'tolak_admin'>('approve_kepala');
const reviewCatatan = ref('');
const submittingReview = ref(false);

function openReview(item: Pengajuan, action: typeof reviewAction.value) {
  reviewItem.value = item;
  reviewAction.value = action;
  reviewCatatan.value = '';
  showReview.value = true;
}

function submitReview() {
  if (!reviewItem.value) return;
  submittingReview.value = true;
  const id = reviewItem.value.id;
  const endpoint: Record<string, string> = {
    approve_kepala: `/pengajuan-indikator/${id}/approve-kepala`,
    tolak_kepala:   `/pengajuan-indikator/${id}/tolak-kepala`,
    approve_admin:  `/pengajuan-indikator/${id}/approve-admin`,
    tolak_admin:    `/pengajuan-indikator/${id}/tolak-admin`,
  };
  router.post(endpoint[reviewAction.value], { catatan: reviewCatatan.value }, {
    onSuccess: () => { showReview.value = false; },
    onFinish: () => { submittingReview.value = false; },
  });
}

const reviewTitle = computed(() => {
  const isNonaktif = reviewItem.value?.request_type === 'nonaktif';
  const prefix = isNonaktif ? 'Nonaktif' : 'Pengajuan';
  const map: Record<string, string> = {
    approve_kepala: `Setujui ${prefix} (Kepala Unit)`,
    tolak_kepala:   `Tolak ${prefix} (Kepala Unit)`,
    approve_admin:  `Setujui ${prefix} (Admin Mutu)`,
    tolak_admin:    `Tolak ${prefix} (Admin Mutu)`,
  };
  return map[reviewAction.value] ?? '';
});

const isApproveAction = computed(() => reviewAction.value.startsWith('approve'));

// ===== CAN EDIT/DELETE =====
function canEdit(item: Pengajuan) {
  const penilaiOwner = isPenilaiPjData.value && item.user_id === props.userId;
  return (penilaiOwner || isKepalaUnit.value) && item.status === 'pending_kepala';
}
function canDelete(item: Pengajuan) {
  return isPenilaiPjData.value && item.user_id === props.userId && item.status === 'pending_kepala';
}
function canApproveKepala(item: Pengajuan) {
  return isKepalaUnit.value && item.status === 'pending_kepala';
}
function canTolakKepala(item: Pengajuan) {
  return isKepalaUnit.value && item.status === 'pending_kepala';
}
function canApproveAdmin(item: Pengajuan) {
  return isAdmin.value && item.status === 'disetujui_kepala';
}
function canTolakAdmin(item: Pengajuan) {
  return isAdmin.value && item.status === 'disetujui_kepala';
}

// ===== OPTIONS =====
const dimensiMutuOptions = ['Aman', 'Adil', 'Berorientasi Pasien', 'Tepat Waktu', 'Efektif', 'Efisien', 'Integrasi'];
const jenisIndikatorOptions = [
  { value: 'SPM',      label: 'INDIKATOR STANDAR PELAYANAN MINIMAL' },
  { value: 'INM',      label: 'INDIKATOR NASIONAL MUTU' },
  { value: 'PRIORITAS', label: 'INDIKATOR MUTU PRIORITAS RS' },
  { value: 'IMUT_UNIT', label: 'INDIKATOR MUTU UNIT' },
];
const sumberDataOptions = ['Primer', 'Sekunder'];
const populasiSampelOptions = ['Probability Sampling', 'Non Probability Sampling'];
const metodePengumpulanOptions = ['Retrospektif', 'Observasi'];
const satuanOptions = [
  { value: 'persen',    label: 'Persen (%)' },
  { value: 'permil',    label: 'Permil (‰)' },
  { value: 'rata-rata', label: 'Rata-rata' },
  { value: 'kejadian',  label: 'Kejadian' },
  { value: 'peserta',   label: 'Peserta' },
  { value: 'dokumen',   label: 'Dokumen' },
];
const satuanWaktuOptions = ['Hari', 'Jam', 'Menit'];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Pengajuan Indikator" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 xl:p-6">

      <!-- ===== HEADER BANNER ===== -->
      <div class="rounded-2xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 px-6 py-5 shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-4">
            <div class="flex-shrink-0 p-3 rounded-2xl bg-white/20 shadow-inner">
              <ClipboardList class="h-8 w-8 text-white" />
            </div>
            <div>
              <h1 class="text-2xl font-bold text-white leading-tight">Pengajuan Indikator</h1>
              <p class="text-sm text-indigo-100 mt-0.5">Ajukan indikator mutu baru untuk disetujui berjenjang</p>
            </div>
          </div>
          <button
            v-if="isPenilaiPjData"
            @click="openAssessmentModal()"
            class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-indigo-700 hover:bg-indigo-50 transition-colors shadow-sm flex-shrink-0"
          >
            <Plus :size="18" />
            Tambah Pengajuan
          </button>
        </div>
      </div>

      <!-- ===== 4 CARDS ===== -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Indikator Aktif -->
        <div class="relative overflow-hidden rounded-2xl bg-white border border-blue-100 shadow-sm p-5 hover:shadow-md transition-shadow">
          <div class="absolute top-0 right-0 w-20 h-20 rounded-full bg-blue-50 -translate-y-6 translate-x-6 opacity-80"></div>
          <div class="relative">
            <div class="flex items-center justify-between mb-3">
              <div class="p-2 rounded-xl bg-blue-100">
                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
              </div>
            </div>
            <p class="text-4xl font-extrabold text-blue-700 leading-none">{{ props.cards.indikator_aktif }}</p>
            <p class="text-sm font-semibold text-gray-700 mt-2">Indikator Aktif</p>
            <p class="text-xs text-gray-400 mt-0.5">indikator berlaku saat ini</p>
          </div>
        </div>

        <!-- Sedang Pengajuan -->
        <div class="relative overflow-hidden rounded-2xl bg-white border border-orange-100 shadow-sm p-5 hover:shadow-md transition-shadow">
          <div class="absolute top-0 right-0 w-20 h-20 rounded-full bg-orange-50 -translate-y-6 translate-x-6 opacity-80"></div>
          <div class="relative">
            <div class="flex items-center justify-between mb-3">
              <div class="p-2 rounded-xl bg-orange-100">
                <svg class="h-5 w-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
            </div>
            <p class="text-4xl font-extrabold text-orange-600 leading-none">{{ props.cards.sedang_pengajuan }}</p>
            <p class="text-sm font-semibold text-gray-700 mt-2">Sedang Pengajuan</p>
            <p class="text-xs text-gray-400 mt-0.5">menunggu review</p>
          </div>
        </div>

        <!-- Disetujui -->
        <div class="relative overflow-hidden rounded-2xl bg-white border border-green-100 shadow-sm p-5 hover:shadow-md transition-shadow">
          <div class="absolute top-0 right-0 w-20 h-20 rounded-full bg-green-50 -translate-y-6 translate-x-6 opacity-80"></div>
          <div class="relative">
            <div class="flex items-center justify-between mb-3">
              <div class="p-2 rounded-xl bg-green-100">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
            </div>
            <p class="text-4xl font-extrabold text-green-600 leading-none">{{ props.cards.disetujui }}</p>
            <p class="text-sm font-semibold text-gray-700 mt-2">Disetujui</p>
            <p class="text-xs text-gray-400 mt-0.5">pengajuan diterima</p>
          </div>
        </div>

        <!-- Ditolak -->
        <div class="relative overflow-hidden rounded-2xl bg-white border border-red-100 shadow-sm p-5 hover:shadow-md transition-shadow">
          <div class="absolute top-0 right-0 w-20 h-20 rounded-full bg-red-50 -translate-y-6 translate-x-6 opacity-80"></div>
          <div class="relative">
            <div class="flex items-center justify-between mb-3">
              <div class="p-2 rounded-xl bg-red-100">
                <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
            </div>
            <p class="text-4xl font-extrabold text-red-600 leading-none">{{ props.cards.ditolak }}</p>
            <p class="text-sm font-semibold text-gray-700 mt-2">Ditolak</p>
            <p class="text-xs text-gray-400 mt-0.5">pengajuan tidak diterima</p>
          </div>
        </div>
      </div>

      <!-- ===== TABLE SECTION ===== -->
      <div class="flex-1 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden flex flex-col min-h-0">

        <!-- Table toolbar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 bg-gray-50/60">
          <div class="flex items-center gap-2">
            <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">Daftar Pengajuan</h2>
            <span class="ml-1 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ filteredList.length }}</span>
          </div>
          <div class="flex items-center gap-2 w-full sm:w-auto">
            <div class="relative flex-1 sm:w-72">
              <input
                v-model="searchQuery"
                @input="onSearch"
                type="text"
                placeholder="Cari judul, tim, status..."
                class="w-full rounded-xl border border-gray-200 bg-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400"
              />
              <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
              </svg>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 sticky top-0 z-10">
              <tr class="border-b border-gray-200">
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider w-12">#</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Judul Indikator</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Pengaju / Tim</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <template v-for="(row, idx) in paginatedList" :key="row._type + '-' + row.id">
                <!-- ROW: Indikator Aktif -->
                <tr v-if="row._type === 'indikator'" class="hover:bg-green-50/30 transition-colors"
                  :class="(row as any).hasPendingNonaktif ? 'bg-red-50/15' : (row as any).hasPendingRevision ? 'bg-amber-50/15' : 'bg-green-50/10'"
                >
                  <td class="px-4 py-3.5 text-center text-gray-400 text-xs font-medium">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
                  <td class="px-4 py-3.5">
                    <p class="font-semibold text-gray-800 max-w-xs truncate" :title="(row as IndikatorAktif).indikator">{{ (row as IndikatorAktif).indikator }}</p>
                    <div class="flex items-center gap-1.5 mt-1">
                      <span
                        class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium"
                        :class="(row as IndikatorAktif).is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'"
                      >{{ (row as IndikatorAktif).is_active ? 'Indikator Aktif' : 'Indikator Nonaktif' }}</span>
                      <span v-if="(row as IndikatorAktif).jenis_indikator" class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-600">{{ (row as IndikatorAktif).jenis_indikator }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3.5 text-gray-600 text-sm">{{ (row as IndikatorAktif).tim_unit ?? '-' }}</td>
                  <td class="px-4 py-3.5 text-center">
                    <span v-if="!(row as IndikatorAktif).is_active" class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold border bg-gray-100 text-gray-500 border-gray-200">
                      Nonaktif
                    </span>
                    <span v-else-if="(row as any).hasPendingNonaktif" class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold border bg-red-100 text-red-700 border-red-200">
                      Menunggu Nonaktif
                    </span>
                    <span v-else-if="(row as any).hasPendingRevision" class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold border bg-amber-100 text-amber-700 border-amber-200">
                      Menunggu Revisi
                    </span>
                    <span v-else class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold border bg-green-100 text-green-700 border-green-200">
                      <Activity :size="11" /> Aktif
                    </span>
                  </td>
                  <td class="px-4 py-3.5">
                    <div class="flex items-center justify-center gap-1 flex-wrap">
                      <button @click="openDetail(row as any)" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 transition-colors" title="Lihat Detail">
                        <Eye :size="14" />
                      </button>
                      <!-- Action buttons only for active indikators without pending review -->
                      <template v-if="(row as IndikatorAktif).is_active">
                        <button
                          v-if="isPenilaiPjData && !(row as any).hasPendingRevision && !(row as any).hasPendingNonaktif"
                          @click="openRevisi(row as IndikatorAktif)"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-orange-100 text-orange-700 hover:bg-orange-200 text-xs font-semibold transition-colors"
                          title="Ajukan Revisi"
                        >
                          <Pencil :size="12" /> Ajukan Revisi
                        </button>
                        <button
                          v-if="isPenilaiPjData && !(row as any).hasPendingRevision && !(row as any).hasPendingNonaktif"
                          @click="openNonaktifModal(row as IndikatorAktif)"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 text-xs font-semibold transition-colors"
                          title="Ajukan Nonaktif"
                        >
                          Pengajuan Nonaktif
                        </button>
                        <span v-if="isPenilaiPjData && ((row as any).hasPendingRevision || (row as any).hasPendingNonaktif)" class="text-xs text-gray-400 italic">
                          Menunggu persetujuan...
                        </span>
                      </template>
                    </div>
                  </td>
                </tr>

                <!-- ROW: Pengajuan -->
                <tr v-else class="hover:bg-indigo-50/30 transition-colors">
                  <td class="px-4 py-3.5 text-center text-gray-400 text-xs font-medium">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
                  <td class="px-4 py-3.5">
                    <p class="font-semibold text-gray-800 max-w-xs truncate" :title="(row as Pengajuan).judul_indikator">{{ (row as Pengajuan).judul_indikator }}</p>
                    <div class="flex items-center gap-1.5 mt-1">
                      <span
                        v-if="(row as Pengajuan).request_type === 'nonaktif'"
                        class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700"
                      >Pengajuan Nonaktif</span>
                      <span
                        v-else-if="(row as Pengajuan).original_indikator_id || activeIndikatorTitleKeys.has((row as Pengajuan).judul_indikator.trim().toLowerCase() + '|' + (row as Pengajuan).kode_unit)"
                        class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-orange-100 text-orange-700"
                      >Revisi</span>
                      <span v-if="(row as Pengajuan).jenis_indikator" class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-600">{{ (row as Pengajuan).jenis_indikator }}</span>
                      <span v-if="(row as Pengajuan).dimensi_mutu" class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-50 text-purple-600">{{ (row as Pengajuan).dimensi_mutu }}</span>
                      <span v-if="(row as Pengajuan).total_skor" class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700">Skor: {{ (row as Pengajuan).total_skor }}/25</span>
                    </div>
                  </td>
                  <td class="px-4 py-3.5">
                    <p class="text-gray-700 text-sm">{{ (row as Pengajuan).user_nama }}</p>
                    <p class="text-gray-400 text-xs">{{ (row as Pengajuan).nama_tim ?? '-' }}</p>
                  </td>
                  <td class="px-4 py-3.5 text-center">
                    <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold border" :class="statusColor[(row as Pengajuan).status]">
                      {{ statusLabel[(row as Pengajuan).status] ?? (row as Pengajuan).status }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5">
                    <div class="flex items-center justify-center gap-1 flex-wrap">
                      <button @click="openDetail(row as Pengajuan)" class="p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 transition-colors" title="Lihat Detail">
                        <Eye :size="14" />
                      </button>
                      <button v-if="canEdit(row as Pengajuan)" @click="openEdit(row as Pengajuan)" class="p-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors" title="Edit">
                        <Pencil :size="14" />
                      </button>
                      <button v-if="canDelete(row as Pengajuan)" @click="openDelete(row as Pengajuan)" class="p-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors" title="Hapus">
                        <Trash2 :size="14" />
                      </button>
                      <button v-if="canApproveKepala(row as Pengajuan)" @click="openReview(row as Pengajuan, 'approve_kepala')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 text-xs font-semibold transition-colors">
                        <CheckCircle :size="13" /> Setujui
                      </button>
                      <button v-if="canTolakKepala(row as Pengajuan)" @click="openReview(row as Pengajuan, 'tolak_kepala')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 text-xs font-semibold transition-colors">
                        <XCircle :size="13" /> Tolak
                      </button>
                      <button v-if="canApproveAdmin(row as Pengajuan)" @click="openReview(row as Pengajuan, 'approve_admin')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 text-xs font-semibold transition-colors">
                        <CheckCircle :size="13" /> Setujui
                      </button>
                      <button v-if="canTolakAdmin(row as Pengajuan)" @click="openReview(row as Pengajuan, 'tolak_admin')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 text-xs font-semibold transition-colors">
                        <XCircle :size="13" /> Tolak
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="paginatedList.length === 0">
                <td colspan="5" class="px-4 py-16 text-center">
                  <div class="flex flex-col items-center gap-3 text-gray-400">
                    <div class="p-4 rounded-2xl bg-gray-100">
                      <ClipboardList class="h-10 w-10 opacity-50" />
                    </div>
                    <p class="text-sm font-medium text-gray-500">Belum ada data</p>
                    <p class="text-xs text-gray-400">{{ searchQuery ? 'Coba kata kunci lain' : isPenilaiPjData ? 'Klik "Tambah Pengajuan" untuk mulai mengajukan' : 'Belum ada pengajuan yang perlu ditinjau' }}</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="filteredList.length > 0" class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50/60 flex-shrink-0">
          <p class="text-xs text-gray-500">
            Menampilkan <span class="font-semibold text-gray-700">{{ Math.min((currentPage - 1) * perPage + 1, filteredList.length) }}–{{ Math.min(currentPage * perPage, filteredList.length) }}</span> dari <span class="font-semibold text-gray-700">{{ filteredList.length }}</span> data
          </p>
          <div class="flex items-center gap-1">
            <button @click="goPage(currentPage - 1)" :disabled="currentPage === 1" class="p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-white disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
              <ChevronLeft :size="15" />
            </button>
            <template v-for="p in totalPages" :key="p">
              <button
                v-if="p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1"
                @click="goPage(p)"
                class="w-8 h-8 rounded-lg border text-xs font-semibold transition-colors"
                :class="p === currentPage ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
              >{{ p }}</button>
              <span v-else-if="p === currentPage - 2 || p === currentPage + 2" class="px-1 text-gray-300 text-sm">…</span>
            </template>
            <button @click="goPage(currentPage + 1)" :disabled="currentPage === totalPages" class="p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-white disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
              <ChevronRight :size="15" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MODAL: SELF-ASSESSMENT ===== -->
    <Teleport to="body">
      <div v-if="showAssessment" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-3">
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[92vh]">
          <!-- Header -->
          <div class="flex items-center justify-between bg-gradient-to-r from-violet-600 to-indigo-600 rounded-t-2xl px-5 py-4 flex-shrink-0">
            <div>
              <h3 class="text-base font-bold text-white">Self-Assessment Indikator</h3>
              <p class="text-xs text-violet-200 mt-0.5">Nilai kandidat indikator, lalu pilih yang memiliki skor tertinggi untuk diajukan</p>
            </div>
            <button @click="showAssessment = false" class="text-white/70 hover:text-white p-1">✕</button>
          </div>

          <!-- Body -->
          <div class="overflow-y-auto flex-1 px-5 py-4 space-y-5">

            <!-- Error -->
            <div v-if="assessmentError" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-start gap-2">
              <span>⚠</span> {{ assessmentError }}
            </div>

            <!-- STEP: ADD CANDIDATE -->
            <div v-if="assessmentStep === 'add'" class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Indikator Kandidat <span class="text-red-500">*</span></label>
                <input v-model="assessmentForm.judul" type="text" placeholder="Judul indikator yang diusulkan..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-300" />
              </div>

              <!-- Criteria scoring table -->
              <div class="rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                  <thead class="bg-violet-50">
                    <tr>
                      <th class="px-3 py-2.5 text-left text-xs font-bold text-violet-700">Kriteria</th>
                      <th v-for="s in [1,2,3,4,5]" :key="s" class="px-2 py-2.5 text-center text-xs font-bold text-violet-700 w-12">{{ s }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-for="c in assessmentCriteria" :key="c.key" class="hover:bg-gray-50">
                      <td class="px-3 py-3">
                        <p class="font-semibold text-gray-700 text-xs">{{ c.label }}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ c.desc }}</p>
                      </td>
                      <td v-for="s in [1,2,3,4,5]" :key="s" class="px-2 py-3 text-center">
                        <input type="radio" :name="c.key" :value="s" v-model="(assessmentForm as any)[c.key]" class="accent-violet-600 w-4 h-4 cursor-pointer" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="flex items-center gap-2 justify-end">
                <button v-if="assessmentCandidates.length > 0" @click="assessmentStep = 'list'; assessmentError = ''" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Lihat Daftar</button>
                <button @click="addCandidate" class="px-5 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700">Tambah ke Daftar</button>
              </div>
            </div>

            <!-- STEP: CANDIDATE LIST -->
            <div v-else class="space-y-4">
              <!-- Duplicate warning -->
              <div v-if="hasDuplicateTopScore" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700 flex items-start gap-2">
                <span>⚠</span>
                <div>
                  <p class="font-semibold">Skor tertinggi kembar!</p>
                  <p class="text-xs mt-0.5">Ada dua kandidat dengan skor tertinggi yang sama. Ubah penilaian salah satunya sebelum melanjutkan.</p>
                </div>
              </div>

              <!-- Ranking table -->
              <div class="rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-500 w-8">Rank</th>
                      <th class="px-3 py-2.5 text-left text-xs font-bold text-gray-500">Nama Indikator</th>
                      <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-500">a</th>
                      <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-500">b</th>
                      <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-500">c</th>
                      <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-500">d</th>
                      <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-500">e</th>
                      <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-500">Total</th>
                      <th class="px-3 py-2.5 text-center text-xs font-bold text-gray-500">Aksi</th>
                      <th class="w-8"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr
                      v-for="(c, ri) in sortedCandidates"
                      :key="c._id"
                      :class="isTopCandidate(c) ? 'bg-green-50' : ''"
                    >
                      <td class="px-3 py-3 text-center">
                        <span :class="ri === 0 ? 'text-green-700 font-bold' : 'text-gray-400'" class="text-sm">{{ ri + 1 }}</span>
                      </td>
                      <td class="px-3 py-3">
                        <p class="font-medium text-gray-800 text-xs">{{ c.judul }}</p>
                        <span v-if="isTopCandidate(c) && !hasDuplicateTopScore" class="text-[10px] text-green-600 font-semibold">★ Skor Tertinggi</span>
                        <span v-if="isTopCandidate(c) && hasDuplicateTopScore" class="text-[10px] text-amber-600 font-semibold">⚠ Skor kembar</span>
                      </td>
                      <td class="px-3 py-3 text-center text-xs text-gray-600">{{ c.skor_a }}</td>
                      <td class="px-3 py-3 text-center text-xs text-gray-600">{{ c.skor_b }}</td>
                      <td class="px-3 py-3 text-center text-xs text-gray-600">{{ c.skor_c }}</td>
                      <td class="px-3 py-3 text-center text-xs text-gray-600">{{ c.skor_d }}</td>
                      <td class="px-3 py-3 text-center text-xs text-gray-600">{{ c.skor_e }}</td>
                      <td class="px-3 py-3 text-center">
                        <span :class="isTopCandidate(c) ? 'bg-green-100 text-green-700 font-bold' : 'bg-gray-100 text-gray-600'" class="inline-block px-2 py-0.5 rounded-lg text-xs font-semibold">{{ c.total }}</span>
                      </td>
                      <td class="px-3 py-3 text-center">
                        <button
                          v-if="isTopCandidate(c)"
                          @click="proceedWithCandidate(c)"
                          :disabled="hasDuplicateTopScore"
                          class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >Ajukan →</button>
                        <span v-else class="text-[11px] text-gray-300">—</span>
                      </td>
                      <td class="px-2 py-3 text-center">
                        <button @click="removeCandidate(c._id)" class="text-gray-300 hover:text-red-500 transition-colors text-xs font-bold" title="Hapus">✕</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <button @click="assessmentStep = 'add'; assessmentError = ''" class="text-xs text-violet-600 hover:underline">+ Tambah Kandidat Lain</button>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-between items-center gap-2 px-5 py-4 border-t border-gray-100 flex-shrink-0">
            <button @click="showAssessment = false" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL: TAMBAH PENGAJUAN ===== -->
    <Teleport to="body">
      <div v-if="showAdd" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-3">
        <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[92vh]">
          <!-- Header -->
          <div class="flex items-center justify-between bg-gradient-to-r from-indigo-600 to-purple-600 rounded-t-2xl px-5 py-4 flex-shrink-0">
            <h3 class="text-base font-bold text-white">Tambah Pengajuan Indikator</h3>
            <button @click="closeAdd()" class="text-white/80 hover:text-white">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <!-- Judul Indikator -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Judul Indikator <span class="text-red-500">*</span></label>
              <input v-model="formAdd.judul_indikator" type="text" placeholder="Masukkan judul indikator" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
              <p v-if="addErrors.judul_indikator" class="text-xs text-red-500 mt-1">{{ addErrors.judul_indikator }}</p>
            </div>
            <!-- Dasar Pemikiran -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Dasar Pemikiran <span class="text-red-500">*</span></label>
              <textarea v-model="formAdd.dasar_pemikiran" rows="2" placeholder="Alasan pengajuan indikator ini..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              <p v-if="addErrors.dasar_pemikiran" class="text-xs text-red-500 mt-1">{{ addErrors.dasar_pemikiran }}</p>
            </div>
            <!-- Dimensi Mutu + Jenis Indikator -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Dimensi Mutu <span class="text-red-500">*</span></label>
                <select v-model="formAdd.dimensi_mutu" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in dimensiMutuOptions" :key="o" :value="o">{{ o }}</option>
                </select>
                <p v-if="addErrors.dimensi_mutu" class="text-xs text-red-500 mt-1">{{ addErrors.dimensi_mutu }}</p>
              </div>
              <div>
                <label class="flex items-center gap-1 text-xs font-semibold text-gray-700 mb-1">
                  Jenis Indikator <span class="text-red-500">*</span>
                  <span class="relative group ml-0.5">
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 text-[10px] font-bold cursor-help">?</span>
                    <div class="absolute top-full left-1/2 -translate-x-1/2 mt-1 w-72 bg-gray-900 text-white text-xs rounded-lg px-3 py-2.5 shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 space-y-1.5">
                      <p><span class="font-semibold text-indigo-300">SPM:</span> Indikator Standar Pelayanan Minimal</p>
                      <p><span class="font-semibold text-indigo-300">INM:</span> Indikator Nasional Mutu</p>
                      <p><span class="font-semibold text-indigo-300">PRIORITAS:</span> Indikator Mutu Prioritas RS</p>
                      <p><span class="font-semibold text-indigo-300">IMUT UNIT:</span> Indikator Mutu Unit</p>
                    </div>
                  </span>
                </label>
                <select v-model="formAdd.jenis_indikator" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in jenisIndikatorOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <p v-if="addErrors.jenis_indikator" class="text-xs text-red-500 mt-1">{{ addErrors.jenis_indikator }}</p>
              </div>
            </div>
            <!-- Tujuan -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Tujuan <span class="text-red-500">*</span></label>
              <textarea v-model="formAdd.tujuan" rows="2" placeholder="Tujuan pengukuran indikator ini..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              <p v-if="addErrors.tujuan" class="text-xs text-red-500 mt-1">{{ addErrors.tujuan }}</p>
            </div>
            <!-- Definisi Operasional -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Definisi Operasional <span class="text-red-500">*</span></label>
              <textarea v-model="formAdd.definisi_operasional" rows="2" placeholder="Penjelasan operasional indikator..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              <p v-if="addErrors.definisi_operasional" class="text-xs text-red-500 mt-1">{{ addErrors.definisi_operasional }}</p>
            </div>
            <!-- Numerator + Denominator -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Numerator (Pembilang)</label>
                <textarea v-model="formAdd.numerator" rows="2" placeholder="Jumlah..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Denominator (Penyebut)</label>
                <textarea v-model="formAdd.denominator" rows="2" placeholder="Total..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              </div>
            </div>
            <!-- Target Capaian per TW -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-2">Target Capaian</label>
              <div class="grid grid-cols-4 gap-3">
                <div v-for="tw in [1,2,3,4]" :key="tw">
                  <label class="block text-[11px] font-medium text-gray-500 mb-1 text-center">TW {{ tw }}</label>
                  <input v-model="(formAdd as any)[`target_tw${tw}`]" type="text" :placeholder="`≥ 80%`" class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-300" />
                </div>
              </div>
            </div>
            <!-- Satuan (pengganti Formula) -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Satuan</label>
                <select v-model="formAdd.satuan" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in satuanOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
              <div v-if="formAdd.satuan === 'rata-rata'">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Satuan Waktu</label>
                <select v-model="formAdd.satuan_waktu" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in satuanWaktuOptions" :key="o" :value="o.toLowerCase()">{{ o }}</option>
                </select>
              </div>
            </div>
            <!-- Kriteria Inklusi Eksklusi -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Kriteria Inklusi &amp; Eksklusi</label>
              <textarea v-model="formAdd.kriteria_inklusi_eksklusi" rows="2" placeholder="Kriteria yang termasuk dan tidak termasuk..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
            </div>
            <!-- Metode + Sumber Data -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Metode Pengumpulan Data <span class="text-red-500">*</span></label>
                <select v-model="formAdd.metode_pengumpulan_data" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in metodePengumpulanOptions" :key="o" :value="o">{{ o }}</option>
                </select>
                <p v-if="addErrors.metode_pengumpulan_data" class="text-xs text-red-500 mt-1">{{ addErrors.metode_pengumpulan_data }}</p>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Sumber Data <span class="text-red-500">*</span></label>
                <select v-model="formAdd.sumber_data" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in sumberDataOptions" :key="o" :value="o">{{ o }}</option>
                </select>
                <p v-if="addErrors.sumber_data" class="text-xs text-red-500 mt-1">{{ addErrors.sumber_data }}</p>
              </div>
            </div>
            <!-- Instrumen -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Instrumen Pengambilan Data <span class="text-red-500">*</span></label>
              <input v-model="formAdd.instrumen_pengambilan_data" type="text" placeholder="Formulir, checklist, dll." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
              <p v-if="addErrors.instrumen_pengambilan_data" class="text-xs text-red-500 mt-1">{{ addErrors.instrumen_pengambilan_data }}</p>
            </div>
            <!-- Populasi/Sampel -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Populasi / Sampel</label>
              <select v-model="formAdd.populasi_sampel" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                <option value="">-- Pilih --</option>
                <option v-for="o in populasiSampelOptions" :key="o" :value="o">{{ o }}</option>
              </select>
            </div>
            <!-- Penanggung Jawab -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Penanggung Jawab <span class="text-red-500">*</span></label>
              <input v-model="formAdd.penanggung_jawab" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-gray-50" readonly />
              <p v-if="addErrors.penanggung_jawab" class="text-xs text-red-500 mt-1">{{ addErrors.penanggung_jawab }}</p>
            </div>
            <!-- Penilaian Kriteria Prioritas (Ajukan Revisi only) -->
            <div v-if="showSkorInForm" class="rounded-xl border border-violet-200 bg-violet-50 p-4 space-y-3">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-bold text-violet-700">Penilaian Kriteria Prioritas</span>
                <span class="text-xs text-violet-500 font-medium">(Skor 1–5 per kriteria)</span>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="bg-violet-100">
                      <th class="px-3 py-2 text-left text-xs font-bold text-violet-700 rounded-tl-lg w-1/2">Kriteria</th>
                      <th v-for="s in [1,2,3,4,5]" :key="s" class="px-2 py-2 text-center text-xs font-bold text-violet-700 w-10">{{ s }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-violet-100">
                    <tr v-for="c in assessmentCriteria" :key="c.key" class="hover:bg-violet-100/50">
                      <td class="px-3 py-2.5">
                        <p class="font-semibold text-gray-700 text-xs">{{ c.label }}</p>
                        <p class="text-[11px] text-gray-400 leading-tight mt-0.5">{{ c.desc }}</p>
                      </td>
                      <td v-for="s in [1,2,3,4,5]" :key="s" class="px-2 py-2 text-center">
                        <input
                          type="radio"
                          :name="'add_' + c.key"
                          :value="s"
                          v-model="(formAdd as any)[c.key]"
                          class="accent-violet-600 cursor-pointer"
                        />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="flex items-center justify-between pt-1">
                <p class="text-xs text-violet-600">Pilih skor untuk semua 5 kriteria di atas.</p>
                <p class="text-sm font-bold text-violet-800">
                  Total: <span :class="formAdd.total_skor !== null ? 'text-violet-700' : 'text-gray-400'">
                    {{ formAdd.total_skor !== null ? formAdd.total_skor + ' / 25' : '— / 25' }}
                  </span>
                </p>
              </div>
            </div>
          </div>
          <!-- Footer -->
          <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 flex-shrink-0">
            <button @click="closeAdd()" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
            <button
              @click="submitAdd"
              :disabled="submittingAdd"
              class="px-5 py-2 rounded-lg bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
            >{{ submittingAdd ? 'Menyimpan...' : 'Kirim Pengajuan' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL: EDIT PENGAJUAN ===== -->
    <Teleport to="body">
      <div v-if="showEdit" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-3">
        <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[92vh]">
          <div class="flex items-center justify-between bg-gradient-to-r from-blue-600 to-indigo-600 rounded-t-2xl px-5 py-4 flex-shrink-0">
            <h3 class="text-base font-bold text-white">Edit Pengajuan Indikator</h3>
            <button @click="showEdit = false" class="text-white/80 hover:text-white">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <!-- Judul Indikator -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Judul Indikator <span class="text-red-500">*</span></label>
              <input v-model="formEdit.judul_indikator" type="text" placeholder="Masukkan judul indikator" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
              <p v-if="editErrors.judul_indikator" class="text-xs text-red-500 mt-1">{{ editErrors.judul_indikator }}</p>
            </div>
            <!-- Dasar Pemikiran -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Dasar Pemikiran <span class="text-red-500">*</span></label>
              <textarea v-model="formEdit.dasar_pemikiran" rows="2" placeholder="Alasan pengajuan indikator ini..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              <p v-if="editErrors.dasar_pemikiran" class="text-xs text-red-500 mt-1">{{ editErrors.dasar_pemikiran }}</p>
            </div>
            <!-- Dimensi Mutu + Jenis Indikator -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Dimensi Mutu <span class="text-red-500">*</span></label>
                <select v-model="formEdit.dimensi_mutu" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in dimensiMutuOptions" :key="o" :value="o">{{ o }}</option>
                </select>
                <p v-if="editErrors.dimensi_mutu" class="text-xs text-red-500 mt-1">{{ editErrors.dimensi_mutu }}</p>
              </div>
              <div>
                <label class="flex items-center gap-1 text-xs font-semibold text-gray-700 mb-1">
                  Jenis Indikator <span class="text-red-500">*</span>
                  <span class="relative group ml-0.5">
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 text-[10px] font-bold cursor-help">?</span>
                    <div class="absolute top-full left-1/2 -translate-x-1/2 mt-1 w-72 bg-gray-900 text-white text-xs rounded-lg px-3 py-2.5 shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 space-y-1.5">
                      <p><span class="font-semibold text-indigo-300">SPM:</span> Indikator Standar Pelayanan Minimal</p>
                      <p><span class="font-semibold text-indigo-300">INM:</span> Indikator Nasional Mutu</p>
                      <p><span class="font-semibold text-indigo-300">PRIORITAS:</span> Indikator Mutu Prioritas RS</p>
                      <p><span class="font-semibold text-indigo-300">IMUT UNIT:</span> Indikator Mutu Unit</p>
                    </div>
                  </span>
                </label>
                <select v-model="formEdit.jenis_indikator" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in jenisIndikatorOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <p v-if="editErrors.jenis_indikator" class="text-xs text-red-500 mt-1">{{ editErrors.jenis_indikator }}</p>
              </div>
            </div>
            <!-- Tujuan -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Tujuan <span class="text-red-500">*</span></label>
              <textarea v-model="formEdit.tujuan" rows="2" placeholder="Tujuan pengukuran indikator ini..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              <p v-if="editErrors.tujuan" class="text-xs text-red-500 mt-1">{{ editErrors.tujuan }}</p>
            </div>
            <!-- Definisi Operasional -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Definisi Operasional <span class="text-red-500">*</span></label>
              <textarea v-model="formEdit.definisi_operasional" rows="2" placeholder="Penjelasan operasional indikator..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              <p v-if="editErrors.definisi_operasional" class="text-xs text-red-500 mt-1">{{ editErrors.definisi_operasional }}</p>
            </div>
            <!-- Numerator + Denominator -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Numerator (Pembilang)</label>
                <textarea v-model="formEdit.numerator" rows="2" placeholder="Jumlah..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Denominator (Penyebut)</label>
                <textarea v-model="formEdit.denominator" rows="2" placeholder="Total..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
              </div>
            </div>
            <!-- Target Capaian per TW -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-2">Target Capaian</label>
              <div class="grid grid-cols-4 gap-3">
                <div v-for="tw in [1,2,3,4]" :key="tw">
                  <label class="block text-[11px] font-medium text-gray-500 mb-1 text-center">TW {{ tw }}</label>
                  <input v-model="(formEdit as any)[`target_tw${tw}`]" type="text" :placeholder="`≥ 80%`" class="w-full rounded-lg border border-gray-300 px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-300" />
                </div>
              </div>
            </div>
            <!-- Satuan (pengganti Formula) -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Satuan</label>
                <select v-model="formEdit.satuan" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in satuanOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
              <div v-if="formEdit.satuan === 'rata-rata'">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Satuan Waktu</label>
                <select v-model="formEdit.satuan_waktu" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in satuanWaktuOptions" :key="o" :value="o.toLowerCase()">{{ o }}</option>
                </select>
              </div>
            </div>
            <!-- Kriteria Inklusi Eksklusi -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Kriteria Inklusi &amp; Eksklusi</label>
              <textarea v-model="formEdit.kriteria_inklusi_eksklusi" rows="2" placeholder="Kriteria yang termasuk dan tidak termasuk..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none" />
            </div>
            <!-- Metode + Sumber Data -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Metode Pengumpulan Data <span class="text-red-500">*</span></label>
                <select v-model="formEdit.metode_pengumpulan_data" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in metodePengumpulanOptions" :key="o" :value="o">{{ o }}</option>
                </select>
                <p v-if="editErrors.metode_pengumpulan_data" class="text-xs text-red-500 mt-1">{{ editErrors.metode_pengumpulan_data }}</p>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Sumber Data <span class="text-red-500">*</span></label>
                <select v-model="formEdit.sumber_data" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                  <option value="">-- Pilih --</option>
                  <option v-for="o in sumberDataOptions" :key="o" :value="o">{{ o }}</option>
                </select>
                <p v-if="editErrors.sumber_data" class="text-xs text-red-500 mt-1">{{ editErrors.sumber_data }}</p>
              </div>
            </div>
            <!-- Instrumen -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Instrumen Pengambilan Data <span class="text-red-500">*</span></label>
              <input v-model="formEdit.instrumen_pengambilan_data" type="text" placeholder="Formulir, checklist, dll." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
              <p v-if="editErrors.instrumen_pengambilan_data" class="text-xs text-red-500 mt-1">{{ editErrors.instrumen_pengambilan_data }}</p>
            </div>
            <!-- Populasi/Sampel -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Populasi / Sampel</label>
              <select v-model="formEdit.populasi_sampel" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-white">
                <option value="">-- Pilih --</option>
                <option v-for="o in populasiSampelOptions" :key="o" :value="o">{{ o }}</option>
              </select>
            </div>
            <!-- Penanggung Jawab -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Penanggung Jawab <span class="text-red-500">*</span></label>
              <input v-model="formEdit.penanggung_jawab" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 bg-gray-50" readonly />
              <p v-if="editErrors.penanggung_jawab" class="text-xs text-red-500 mt-1">{{ editErrors.penanggung_jawab }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 flex-shrink-0">
            <button @click="showEdit = false" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
            <button
              @click="submitEdit"
              :disabled="submittingEdit"
              class="px-5 py-2 rounded-lg bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            >{{ submittingEdit ? 'Menyimpan...' : 'Simpan Perubahan' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL: DETAIL ===== -->
    <Teleport to="body">
      <div v-if="showDetail && detailItem" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-3">
        <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[92vh]">
          <div class="flex items-center justify-between bg-gradient-to-r from-gray-700 to-gray-800 rounded-t-2xl px-5 py-4 flex-shrink-0">
            <div>
              <h3 class="text-base font-bold text-white">{{ detailItem._type === 'indikator' ? 'Detail Indikator' : 'Detail Pengajuan' }}</h3>
              <p class="text-xs text-gray-300 mt-0.5">{{ detailItem._type === 'indikator' ? (detailItem as any).indikator : (detailItem as any).judul_indikator }}</p>
            </div>
            <div class="flex items-center gap-3">
              <span v-if="detailItem._type === 'indikator'"
                class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold border"
                :class="(detailItem as any).is_active ? 'bg-green-100 text-green-700 border-green-200' : 'bg-gray-100 text-gray-500 border-gray-200'"
              >{{ (detailItem as any).is_active ? 'Aktif' : 'Nonaktif' }}</span>
              <span v-else class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold border" :class="statusColor[(detailItem as any).status]">
                {{ statusLabel[(detailItem as any).status] }}
              </span>
              <button @click="showDetail = false" class="text-white/80 hover:text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>
          <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4 text-sm">
            <!-- Indikator aktif detail (simple) -->
            <template v-if="detailItem._type === 'indikator'">
              <!-- Nama -->
              <div>
                <span v-if="(detailItem as any).jenis_indikator" class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-600 border border-blue-100 mb-2">{{ (detailItem as any).jenis_indikator }}</span>
                <p class="text-[15px] font-semibold text-gray-900 leading-snug">{{ (detailItem as any).indikator }}</p>
              </div>

              <!-- Penanggung Jawab — left-border accent, natural rows -->
              <div class="border-l-2 border-slate-200 pl-3 space-y-1.5 text-sm">
                <div class="flex items-baseline gap-2">
                  <span class="text-gray-400 min-w-[120px] shrink-0">PJ Data</span>
                  <span class="text-gray-700">{{ (detailItem as any).pj_nama || (detailItem as any).pic || '—' }}</span>
                </div>
                <div class="flex items-baseline gap-2">
                  <span class="text-gray-400 min-w-[120px] shrink-0">Penilai PJ Data</span>
                  <span class="text-gray-700">{{ props.userName }}</span>
                </div>
                <div class="flex items-baseline gap-2">
                  <span class="text-gray-400 min-w-[120px] shrink-0">Kepala Bagian</span>
                  <span class="text-gray-700">{{ (detailItem as any).kepala_nama || props.kepalaUnitName || '—' }}</span>
                </div>
              </div>

              <!-- Numerator / Denominator -->
              <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div>
                  <p class="text-xs text-gray-400 mb-0.5">Numerator</p>
                  <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ (detailItem as any).numerator || '—' }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-400 mb-0.5">Denominator</p>
                  <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ (detailItem as any).denominator || '—' }}</p>
                </div>
              </div>

              <!-- Target per TW — single container, divided columns -->
              <div>
                <p class="text-xs text-gray-400 mb-2">Target per Triwulan</p>
                <div class="grid grid-cols-4 divide-x divide-gray-200 border border-gray-200 rounded-lg overflow-hidden">
                  <div v-for="tw in [1,2,3,4]" :key="tw" class="px-3 py-3 text-center">
                    <p class="text-[10px] text-gray-400 mb-1.5">TW {{ tw }}</p>
                    <p class="text-sm font-semibold text-gray-800">{{ getTwTarget(detailItem, tw) }}</p>
                    <p class="text-[10px] text-gray-400 mt-1 capitalize">{{ (detailItem as any)[`satuan_tw${tw}`] || (detailItem as any).satuan || '' }}</p>
                  </div>
                </div>
              </div>
            </template>
            <!-- Pengajuan detail (full) -->
            <template v-else>
            <!-- Nonaktif request: show concise view -->
            <template v-if="(detailItem as any).request_type === 'nonaktif'">
              <div class="rounded-xl bg-red-50 border border-red-200 p-4 space-y-2">
                <p class="text-xs font-bold text-red-700 uppercase tracking-wider">Pengajuan Nonaktif Indikator</p>
                <p class="text-sm font-semibold text-gray-900">{{ (detailItem as any).judul_indikator }}</p>
                <div class="mt-2">
                  <p class="text-xs text-gray-500 font-medium">Alasan Nonaktif</p>
                  <p class="text-sm text-gray-800 whitespace-pre-wrap mt-0.5">{{ (detailItem as any).alasan_nonaktif }}</p>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div><p class="text-xs text-gray-500 font-medium">Pengaju</p><p class="text-gray-800">{{ (detailItem as any).user_nama }}</p></div>
                <div><p class="text-xs text-gray-500 font-medium">Tim Unit</p><p class="text-gray-800">{{ (detailItem as any).nama_tim ?? '-' }}</p></div>
              </div>
              <!-- Riwayat Review -->
              <div v-if="(detailItem as any).reviewed_by_kepala || (detailItem as any).reviewed_by_admin" class="mt-2 rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Riwayat Review</p>
                <div v-if="(detailItem as any).reviewed_by_kepala" class="text-xs space-y-0.5">
                  <p class="font-semibold text-gray-700">Kepala Unit: {{ (detailItem as any).reviewed_by_kepala }} <span class="font-normal text-gray-400">({{ (detailItem as any).reviewed_at_kepala }})</span></p>
                  <p class="text-gray-600" v-if="(detailItem as any).catatan_kepala">Catatan: {{ (detailItem as any).catatan_kepala }}</p>
                </div>
                <div v-if="(detailItem as any).reviewed_by_admin" class="text-xs space-y-0.5">
                  <p class="font-semibold text-gray-700">Admin Mutu: {{ (detailItem as any).reviewed_by_admin }} <span class="font-normal text-gray-400">({{ (detailItem as any).reviewed_at_admin }})</span></p>
                  <p class="text-gray-600" v-if="(detailItem as any).catatan_admin">Catatan: {{ (detailItem as any).catatan_admin }}</p>
                </div>
              </div>
            </template>

            <!-- Regular/revision pengajuan -->
            <template v-else>
            <!-- Info Row -->
            <div class="grid grid-cols-2 gap-4">
              <div><p class="text-xs text-gray-500 font-medium">Pengaju</p><p class="text-gray-800">{{ (detailItem as any).user_nama }}</p></div>
              <div><p class="text-xs text-gray-500 font-medium">Tim Unit</p><p class="text-gray-800">{{ (detailItem as any).nama_tim ?? '-' }}</p></div>
              <div><p class="text-xs text-gray-500 font-medium">Jenis Indikator</p><p class="text-gray-800">{{ (detailItem as any).jenis_indikator }}</p></div>
            </div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Judul Indikator</p><p class="text-gray-800 whitespace-pre-wrap">{{ (detailItem as any).judul_indikator }}</p></div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Dasar Pemikiran</p><p class="text-gray-800 whitespace-pre-wrap">{{ (detailItem as any).dasar_pemikiran }}</p></div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Dimensi Mutu</p><p class="text-gray-800">{{ (detailItem as any).dimensi_mutu }}</p></div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Tujuan</p><p class="text-gray-800 whitespace-pre-wrap">{{ (detailItem as any).tujuan }}</p></div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Definisi Operasional</p><p class="text-gray-800 whitespace-pre-wrap">{{ (detailItem as any).definisi_operasional }}</p></div>
            <div class="grid grid-cols-2 gap-4">
              <div><p class="text-xs text-gray-500 font-medium mb-0.5">Numerator (Pembilang)</p><p class="text-gray-800 whitespace-pre-wrap">{{ (detailItem as any).numerator ?? '-' }}</p></div>
              <div><p class="text-xs text-gray-500 font-medium mb-0.5">Denominator (Penyebut)</p><p class="text-gray-800 whitespace-pre-wrap">{{ (detailItem as any).denominator ?? '-' }}</p></div>
            </div>
            <!-- Target Capaian per TW -->
            <div>
              <p class="text-xs text-gray-500 font-medium mb-1.5">Target Capaian</p>
              <div class="grid grid-cols-4 gap-3">
                <div v-for="tw in [1,2,3,4]" :key="tw" class="rounded-lg border border-gray-200 bg-gray-50 p-2 text-center">
                  <p class="text-[11px] text-gray-400 mb-0.5">TW {{ tw }}</p>
                  <p class="text-sm font-semibold text-gray-800">{{ (detailItem as any)[`target_tw${tw}`] || '-' }}</p>
                </div>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div><p class="text-xs text-gray-500 font-medium mb-0.5">Satuan</p><p class="text-gray-800">{{ (detailItem as any).satuan ?? '-' }}</p></div>
              <div v-if="(detailItem as any).satuan === 'rata-rata'"><p class="text-xs text-gray-500 font-medium mb-0.5">Satuan Waktu</p><p class="text-gray-800">{{ (detailItem as any).satuan_waktu ?? '-' }}</p></div>
            </div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Kriteria Inklusi &amp; Eksklusi</p><p class="text-gray-800 whitespace-pre-wrap">{{ (detailItem as any).kriteria_inklusi_eksklusi ?? '-' }}</p></div>
            <div class="grid grid-cols-2 gap-4">
              <div><p class="text-xs text-gray-500 font-medium mb-0.5">Metode Pengumpulan Data</p><p class="text-gray-800">{{ (detailItem as any).metode_pengumpulan_data }}</p></div>
              <div><p class="text-xs text-gray-500 font-medium mb-0.5">Sumber Data</p><p class="text-gray-800">{{ (detailItem as any).sumber_data }}</p></div>
            </div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Instrumen Pengambilan Data</p><p class="text-gray-800">{{ (detailItem as any).instrumen_pengambilan_data }}</p></div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Populasi / Sampel</p><p class="text-gray-800 whitespace-pre-wrap">{{ (detailItem as any).populasi_sampel ?? '-' }}</p></div>
            <div><p class="text-xs text-gray-500 font-medium mb-0.5">Penanggung Jawab</p><p class="text-gray-800">{{ (detailItem as any).penanggung_jawab }}</p></div>

            <!-- Riwayat Review -->
            <div v-if="(detailItem as any).reviewed_by_kepala || (detailItem as any).reviewed_by_admin" class="mt-2 rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
              <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Riwayat Review</p>
              <div v-if="(detailItem as any).reviewed_by_kepala" class="text-xs space-y-0.5">
                <p class="font-semibold text-gray-700">Kepala Unit: {{ (detailItem as any).reviewed_by_kepala }} <span class="font-normal text-gray-400">({{ (detailItem as any).reviewed_at_kepala }})</span></p>
                <p class="text-gray-600" v-if="(detailItem as any).catatan_kepala">Catatan: {{ (detailItem as any).catatan_kepala }}</p>
              </div>
              <div v-if="(detailItem as any).reviewed_by_admin" class="text-xs space-y-0.5">
                <p class="font-semibold text-gray-700">Admin Mutu: {{ (detailItem as any).reviewed_by_admin }} <span class="font-normal text-gray-400">({{ (detailItem as any).reviewed_at_admin }})</span></p>
                <p class="text-gray-600" v-if="(detailItem as any).catatan_admin">Catatan: {{ (detailItem as any).catatan_admin }}</p>
              </div>
              <div v-if="(detailItem as any).indikator_id" class="text-xs text-green-700 font-medium">
                ✓ Indikator telah dibuat (ID: {{ (detailItem as any).indikator_id }})
              </div>
            </div>
            <!-- Hasil Self-Assessment (softer, bottom) -->
            <div v-if="(detailItem as any).total_skor" class="rounded-lg bg-violet-50/70 px-4 py-3 space-y-1.5">
              <div class="flex items-center justify-between mb-1">
                <p class="text-xs font-semibold text-violet-600">Hasil Self-Assessment</p>
                <span class="text-xs font-bold text-violet-700">Total: {{ (detailItem as any).total_skor }} / 25</span>
              </div>
              <div v-for="c in assessmentCriteria" :key="c.key" class="flex items-center justify-between text-sm">
                <span class="text-gray-600">{{ c.label.replace(/^[a-e]\. /, '') }}</span>
                <span class="font-semibold text-violet-700 ml-4">{{ (detailItem as any)[c.key] ?? '—' }}</span>
              </div>
            </div>
            </template><!-- end regular/revision template -->
            </template><!-- end v-else pengajuan detail -->
          </div>
          <div class="flex justify-end px-5 py-4 border-t border-gray-100 flex-shrink-0">
            <button @click="showDetail = false" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Tutup</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL: HAPUS ===== -->
    <Teleport to="body">
      <div v-if="showDelete" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-3">
        <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6">
          <div class="flex flex-col items-center text-center gap-3">
            <div class="p-3 rounded-full bg-red-100">
              <Trash2 class="h-6 w-6 text-red-600" />
            </div>
            <h3 class="text-base font-bold text-gray-900">Hapus Pengajuan?</h3>
            <p class="text-sm text-gray-500">Pengajuan yang dihapus tidak dapat dikembalikan.</p>
          </div>
          <div class="flex gap-2 mt-5">
            <button @click="showDelete = false" class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
            <button
              @click="submitDelete"
              :disabled="submittingDelete"
              class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
            >{{ submittingDelete ? 'Menghapus...' : 'Ya, Hapus' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL: REVIEW (Approve/Tolak) ===== -->
    <Teleport to="body">
      <div v-if="showReview && reviewItem" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-3">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div :class="isApproveAction ? 'bg-green-100' : 'bg-red-100'" class="p-2.5 rounded-xl">
              <CheckCircle v-if="isApproveAction" class="h-5 w-5 text-green-600" />
              <XCircle v-else class="h-5 w-5 text-red-600" />
            </div>
            <div>
              <h3 class="text-base font-bold text-gray-900">{{ reviewTitle }}</h3>
              <p class="text-xs text-gray-500 mt-0.5 max-w-xs truncate">{{ reviewItem.judul_indikator }}</p>
            </div>
          </div>

          <!-- Alasan nonaktif (shown for all nonaktif reviews) -->
          <div v-if="reviewItem.request_type === 'nonaktif'" class="mb-4 rounded-lg bg-orange-50 border border-orange-200 p-3 text-xs text-orange-800">
            <p class="font-semibold mb-0.5">Alasan Pengajuan Nonaktif:</p>
            <p class="whitespace-pre-wrap">{{ reviewItem.alasan_nonaktif }}</p>
          </div>

          <!-- Info hints -->
          <div v-if="isApproveAction && reviewAction === 'approve_admin' && reviewItem.request_type === 'nonaktif'" class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-700">
            Setelah disetujui, indikator akan <strong>dinonaktifkan</strong> dari sistem.
          </div>
          <div v-else-if="isApproveAction && reviewAction === 'approve_admin'" class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-xs text-green-700">
            Setelah disetujui, indikator akan otomatis diperbarui di sistem.
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700">
              Catatan
              <span v-if="!isApproveAction && reviewItem.request_type === 'nonaktif'" class="text-red-500">*</span>
              <span v-else class="text-gray-400 font-normal">(opsional)</span>
            </label>
            <textarea
              v-model="reviewCatatan"
              rows="3"
              :placeholder="!isApproveAction && reviewItem.request_type === 'nonaktif' ? 'Wajib isi alasan penolakan...' : 'Tambahkan catatan atau keterangan...'"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"
            />
          </div>

          <div class="flex gap-2 mt-5">
            <button @click="showReview = false" class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
            <button
              @click="submitReview"
              :disabled="submittingReview"
              class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-white disabled:opacity-50 transition-colors"
              :class="isApproveAction ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
            >{{ submittingReview ? 'Menyimpan...' : (isApproveAction ? 'Ya, Setujui' : 'Ya, Tolak') }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL: PENGAJUAN NONAKTIF ===== -->
    <Teleport to="body">
      <div v-if="showNonaktifModal && nonaktifIndikator" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-3">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2.5 rounded-xl bg-red-100">
              <XCircle class="h-5 w-5 text-red-600" />
            </div>
            <div>
              <h3 class="text-base font-bold text-gray-900">Pengajuan Nonaktif Indikator</h3>
              <p class="text-xs text-gray-500 mt-0.5 max-w-xs truncate">{{ nonaktifIndikator.indikator }}</p>
            </div>
          </div>

          <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-700">
            Pengajuan ini akan dikirim ke Kepala Unit untuk disetujui, kemudian ke Admin Mutu. Indikator baru dinonaktifkan setelah Admin Mutu menyetujui.
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700">Alasan Nonaktif <span class="text-red-500">*</span></label>
            <textarea
              v-model="alasanNonaktif"
              rows="4"
              placeholder="Jelaskan alasan mengapa indikator ini perlu dinonaktifkan..."
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 resize-none"
            />
          </div>

          <div class="flex gap-2 mt-5">
            <button @click="showNonaktifModal = false" class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
            <button
              @click="submitNonaktif"
              :disabled="submittingNonaktif || !alasanNonaktif.trim()"
              class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50 transition-colors"
            >{{ submittingNonaktif ? 'Mengirim...' : 'Kirim Pengajuan' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>
