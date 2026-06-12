<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Eye, Pencil, Trash2, Plus, X, Save, Search } from 'lucide-vue-next';

interface TimUnit {
  id: number;
  nama_tim: string;
}

interface Unit {
  id: number;
  kode_unit: string;
  nama_unit: string;
  alias: string;
  tim_units?: TimUnit[];
}

interface Pegawai {
  id: number;
  name: string;
  nip: string | null;
  role: string;
  status_pegawai: string | null;
  status_kerja: string | null;
  kode_unit: string | null;
  unit?: Unit | null;
}

const props = defineProps<{
  users: Pegawai[];
  units: Unit[];
}>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Manajemen Pegawai', href: '/manajemen-pegawai' },
];

const roleLabels: Record<string, string> = {
  admin_mutu: 'Admin Mutu',
  kepala_unit: 'Kepala Unit',
  staf: 'Staf',
  penilai_pj_data: 'Penilai PJ Data',
};

// Opsi untuk field "Jabatan" (tipe kepegawaian, sebelumnya "Status Pegawai")
const jabatanOptions = [
  'PNS',
  'CPNS',
  'PPPK',
  'PPPK Paruh Waktu',
  'Pegawai Blud (Tetap Non ASN)',
  'PJLP',
  'Mitra',
  'Pegawai Lainnya Non ASN',
];

// Opsi untuk field "Status Pegawai" (status aktif/tidak)
const statusKerjaOptions = ['Aktif', 'Resign', 'Pensiun', 'Mutasi'];

const statusKerjaBadge: Record<string, string> = {
  Aktif: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  Resign: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  Pensiun: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  Mutasi: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
};

/* ====== Flash Messages ====== */
const flashSuccess = computed(() => (page.props as any).flash?.success || '');
const flashError = computed(() => (page.props as any).flash?.error || '');

/* ====== Helpers ====== */
function getRoleDisplay(role: string): string {
  if (role.startsWith('penilai_pj_data - ')) {
    return 'Penilai PJ Data - ' + role.replace('penilai_pj_data - ', '');
  }
  return roleLabels[role] ?? role;
}

function parseRole(role: string): { base_role: string; is_pj_data: boolean; is_penilai_pj_data: boolean; pj_data_tim: string } {
  if (role.startsWith('PJ Data - ')) {
    return { base_role: 'staf', is_pj_data: true, is_penilai_pj_data: false, pj_data_tim: role.replace('PJ Data - ', '') };
  }
  if (role === 'PJ Data') {
    return { base_role: 'staf', is_pj_data: true, is_penilai_pj_data: false, pj_data_tim: '' };
  }
  if (role.startsWith('penilai_pj_data - ')) {
    return { base_role: 'staf', is_pj_data: false, is_penilai_pj_data: true, pj_data_tim: role.replace('penilai_pj_data - ', '') };
  }
  if (role === 'penilai_pj_data') {
    return { base_role: 'staf', is_pj_data: false, is_penilai_pj_data: true, pj_data_tim: '' };
  }
  return { base_role: role, is_pj_data: false, is_penilai_pj_data: false, pj_data_tim: '' };
}

function getPjDataTims(role: string): string[] {
  if (role.startsWith('PJ Data - ')) {
    return role.replace('PJ Data - ', '').split(', ').map(t => t.trim()).filter(Boolean);
  }
  return [];
}

function getPenilaiDataTims(role: string): string[] {
  if (role.startsWith('penilai_pj_data - ')) {
    return role.replace('penilai_pj_data - ', '').split(', ').map(t => t.trim()).filter(Boolean);
  }
  return [];
}

function isPenilaiRole(role: string): boolean {
  return role === 'penilai_pj_data' || role.startsWith('penilai_pj_data - ');
}

function getTimsForUnit(kode_unit: string): TimUnit[] {
  return props.units.find(u => u.kode_unit === kode_unit)?.tim_units ?? [];
}

/* ====== Search & Pagination ====== */
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const filteredUsers = computed(() => {
  if (!searchQuery.value) return props.users;
  const q = searchQuery.value.toLowerCase();
  return props.users.filter(u =>
    u.name.toLowerCase().includes(q) ||
    getRoleDisplay(u.role).toLowerCase().includes(q)
  );
});

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / itemsPerPage));

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  return filteredUsers.value.slice(start, start + itemsPerPage);
});

const paginationPages = computed((): (number | '...')[] => {
  const total = totalPages.value;
  const cur = currentPage.value;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const pages: (number | '...')[] = [1];
  if (cur > 3) pages.push('...');
  for (let i = Math.max(2, cur - 1); i <= Math.min(total - 1, cur + 1); i++) pages.push(i);
  if (cur < total - 2) pages.push('...');
  pages.push(total);
  return pages;
});

function goToPage(p: number) {
  if (p >= 1 && p <= totalPages.value) currentPage.value = p;
}

watch(searchQuery, () => { currentPage.value = 1; });

/* ====== Modal State ====== */
// View
const showView = ref(false);
const viewItem = ref<Pegawai | null>(null);

// Add
const showAdd = ref(false);
const formAdd = ref({ name: '', nip: '', password: '', base_role: '', status_pegawai: '', status_kerja: '', kode_unit: '', is_pj_data: false, is_penilai_pj_data: false });
const selectedAddTims = ref<string[]>([]);
const addTimError = ref('');
const addErrors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

// Edit — is_pj_data & pj_data_tim hidden, auto-populated to preserve status
const showEdit = ref(false);
const editItem = ref<Pegawai | null>(null);
const formEdit = ref({ name: '', nip: '', password: '', base_role: '', status_pegawai: '', status_kerja: '', kode_unit: '', is_pj_data: false, is_penilai_pj_data: false, pj_data_tim: '' });
const editErrors = ref<Record<string, string>>({});

// Delete
const showDelete = ref(false);
const deleteItem = ref<Pegawai | null>(null);

// PJ Data inline modal (tabel)
const pjDataModal = ref<{ user: Pegawai } | null>(null);
const selectedPjTims = ref<string[]>([]);
const pjTimError = ref('');

// Penilai PJ Data inline modal (tabel)
const penilaiPjDataModal = ref<{ user: Pegawai } | null>(null);
const selectedPenilaiTims = ref<string[]>([]);
const penilaiTimError = ref('');

/* ====== Watchers ====== */
watch(() => formAdd.value.base_role, (val) => {
  if (val !== 'staf') {
    formAdd.value.is_pj_data = false;
    formAdd.value.is_penilai_pj_data = false;
    selectedAddTims.value = [];
    addTimError.value = '';
  }
});
watch(() => formAdd.value.is_pj_data, (val) => {
  if (val) formAdd.value.is_penilai_pj_data = false;
  else { selectedAddTims.value = []; addTimError.value = ''; }
});
watch(() => formAdd.value.is_penilai_pj_data, (val) => {
  if (val) { formAdd.value.is_pj_data = false; selectedAddTims.value = []; addTimError.value = ''; }
});
watch(() => formAdd.value.kode_unit, () => {
  formAdd.value.is_pj_data = false;
  formAdd.value.is_penilai_pj_data = false;
  selectedAddTims.value = [];
  addTimError.value = '';
});

// Note: reset PJ Data when kode_unit is MANUALLY changed in the edit form (not on initial open)
function onEditKodeUnitChange() {
  formEdit.value.is_pj_data = false;
  formEdit.value.is_penilai_pj_data = false;
  formEdit.value.pj_data_tim = '';
}

/* ====== Tim validation (Add modal) ====== */
function onAddTimChange() {
  if (selectedAddTims.value.length > 2) {
    selectedAddTims.value.pop();
    addTimError.value = 'Maksimal 2 tim yang dapat dipilih.';
  } else {
    addTimError.value = '';
  }
}

/* ====== PJ Data inline (tabel) ====== */
function handlePjDataCheckbox(user: Pegawai) {
  if (!user.kode_unit) return;

  // Jika sudah PJ Data → hapus langsung tanpa modal
  if (user.role.startsWith('PJ Data')) {
    savePjDataUpdate(user, false, '');
    return;
  }

  const tims = getTimsForUnit(user.kode_unit);
  if (tims.length === 0) {
    // Unit tanpa tim: set langsung
    savePjDataUpdate(user, true, '');
  } else {
    // Unit punya tim: buka modal untuk pilih tim (kosong karena baru ditambahkan)
    selectedPjTims.value = [];
    pjTimError.value = '';
    pjDataModal.value = { user };
  }
}

function onPjTimChange() {
  if (selectedPjTims.value.length > 2) {
    selectedPjTims.value.pop();
    pjTimError.value = 'Maksimal 2 tim yang dapat dipilih.';
  } else {
    pjTimError.value = '';
  }
}

function confirmPjData() {
  if (!pjDataModal.value) return;
  savePjDataUpdate(pjDataModal.value.user, true, selectedPjTims.value.join(', '));
  pjDataModal.value = null;
}

function removePjDataFromModal() {
  if (!pjDataModal.value) return;
  savePjDataUpdate(pjDataModal.value.user, false, '');
  pjDataModal.value = null;
}

function savePjDataUpdate(user: Pegawai, isPj: boolean, timStr: string) {
  // Gunakan endpoint khusus agar tidak perlu kirim/validasi field lain (status_pegawai, dll)
  router.put(`/manajemen-pegawai/${user.id}/pj-role`, {
    base_role: 'staf',
    is_pj_data: isPj,
    pj_data_tim: timStr,
    is_penilai_pj_data: false,
  }, { preserveScroll: true });
}

/* ====== Penilai PJ Data inline (tabel) ====== */
function handlePenilaianPjDataCheckbox(user: Pegawai) {
  if (!user.kode_unit) return;

  // Jika sudah Penilai PJ Data → hapus langsung tanpa modal
  if (isPenilaiRole(user.role)) {
    savePenilaiPjDataUpdate(user, false, '');
    return;
  }

  const tims = getTimsForUnit(user.kode_unit);
  if (tims.length === 0) {
    savePenilaiPjDataUpdate(user, true, '');
  } else {
    selectedPenilaiTims.value = [];
    penilaiTimError.value = '';
    penilaiPjDataModal.value = { user };
  }
}

function onPenilaiTimChange() {
  if (selectedPenilaiTims.value.length > 2) {
    selectedPenilaiTims.value.pop();
    penilaiTimError.value = 'Maksimal 2 tim yang dapat dipilih.';
  } else {
    penilaiTimError.value = '';
  }
}

function confirmPenilaiPjData() {
  if (!penilaiPjDataModal.value) return;
  savePenilaiPjDataUpdate(penilaiPjDataModal.value.user, true, selectedPenilaiTims.value.join(', '));
  penilaiPjDataModal.value = null;
}

function removePenilaiPjDataFromModal() {
  if (!penilaiPjDataModal.value) return;
  savePenilaiPjDataUpdate(penilaiPjDataModal.value.user, false, '');
  penilaiPjDataModal.value = null;
}

function savePenilaiPjDataUpdate(user: Pegawai, isPenilai: boolean, timStr: string) {
  router.put(`/manajemen-pegawai/${user.id}/pj-role`, {
    base_role: 'staf',
    is_pj_data: false,
    pj_data_tim: timStr,
    is_penilai_pj_data: isPenilai,
  }, { preserveScroll: true });
}

/* ====== Modal Actions ====== */
function openView(user: Pegawai) {
  viewItem.value = user;
  showView.value = true;
}

function openAdd() {
  formAdd.value = { name: '', nip: '', password: '', base_role: '', status_pegawai: '', status_kerja: '', kode_unit: '', is_pj_data: false, is_penilai_pj_data: false };
  selectedAddTims.value = [];
  addTimError.value = '';
  addErrors.value = {};
  showAdd.value = true;
}

function openEdit(user: Pegawai) {
  editItem.value = user;
  const parsed = parseRole(user.role);
  formEdit.value = {
    name: user.name,
    nip: user.nip || '',
    password: '',
    base_role: parsed.base_role,
    status_pegawai: user.status_pegawai || '',
    status_kerja: user.status_kerja || '',
    kode_unit: user.kode_unit || '',
    is_pj_data: parsed.is_pj_data,
    is_penilai_pj_data: parsed.is_penilai_pj_data,
    pj_data_tim: parsed.pj_data_tim,
  };
  editErrors.value = {};
  showEdit.value = true;
}

function openDelete(user: Pegawai) {
  deleteItem.value = user;
  showDelete.value = true;
}

/* ====== CRUD ====== */
function submitAdd() {
  if (isSubmitting.value) return;
  addErrors.value = {};

  if (!formAdd.value.name) { addErrors.value.name = 'Nama wajib diisi'; }
  if (!formAdd.value.password) { addErrors.value.password = 'Password wajib diisi'; }
  if (!formAdd.value.base_role) { addErrors.value.base_role = 'Jabatan wajib dipilih'; }

  if (Object.keys(addErrors.value).length > 0) return;

  isSubmitting.value = true;
  router.post('/manajemen-pegawai', {
    ...formAdd.value,
    pj_data_tim: selectedAddTims.value.join(', '),
  }, {
    preserveScroll: true,
    onSuccess: () => { showAdd.value = false; isSubmitting.value = false; },
    onError: (errors) => { addErrors.value = errors as Record<string, string>; isSubmitting.value = false; },
  });
}

function submitEdit() {
  if (isSubmitting.value || !editItem.value) return;
  editErrors.value = {};

  if (!formEdit.value.name) { editErrors.value.name = 'Nama wajib diisi'; }
  if (!formEdit.value.base_role) { editErrors.value.base_role = 'Jabatan wajib dipilih'; }

  if (Object.keys(editErrors.value).length > 0) return;

  isSubmitting.value = true;
  router.put(`/manajemen-pegawai/${editItem.value.id}`, formEdit.value, {
    preserveScroll: true,
    onSuccess: () => { showEdit.value = false; isSubmitting.value = false; },
    onError: (errors) => { editErrors.value = errors as Record<string, string>; isSubmitting.value = false; },
  });
}

function submitDelete() {
  if (isSubmitting.value || !deleteItem.value) return;
  isSubmitting.value = true;
  router.delete(`/manajemen-pegawai/${deleteItem.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { showDelete.value = false; isSubmitting.value = false; },
    onError: () => { isSubmitting.value = false; },
  });
}
</script>

<template>
  <Head title="Manajemen Pegawai" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4 xl:p-6">

      <!-- Flash Messages -->
      <div v-if="flashSuccess" class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
        {{ flashSuccess }}
      </div>
      <div v-if="flashError" class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
        {{ flashError }}
      </div>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <h2 class="text-xl font-bold dark:text-gray-100">Manajemen Pegawai</h2>
        <button
          @click="openAdd"
          class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 shadow-md transition-colors"
        >
          <Plus :size="16" />
          Tambah Pegawai
        </button>
      </div>

      <!-- Search -->
      <div class="flex items-center gap-2">
        <div class="relative w-full max-w-md">
          <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama atau jabatan..."
            class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-12">No</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Nama</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">NIP</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Peran</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Jabatan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700">Status Pegawai</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-48">PJ Data / Penilai</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-28">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="(user, index) in paginatedUsers" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
              <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ user.name }}</td>
              <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ user.nip || '-' }}</td>

              <!-- Peran (role sistem) -->
              <td class="px-4 py-3">
                <span
                  class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="{
                    'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300': user.role === 'admin_mutu',
                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300': user.role === 'kepala_unit',
                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': user.role === 'staf',
                    'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300': user.role.startsWith('PJ Data'),
                    'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300': isPenilaiRole(user.role),
                  }"
                >
                  {{ getRoleDisplay(user.role) }}
                </span>
              </td>

              <!-- Jabatan (tipe kepegawaian: PNS, CPNS, dst) -->
              <td class="px-4 py-3 text-gray-900 dark:text-gray-100 text-sm">
                {{ user.status_pegawai || '-' }}
              </td>

              <!-- Status Pegawai (Aktif, Resign, Pensiun, Mutasi) -->
              <td class="px-4 py-3">
                <span
                  v-if="user.status_kerja"
                  class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="statusKerjaBadge[user.status_kerja] ?? 'bg-gray-100 text-gray-700'"
                >
                  {{ user.status_kerja }}
                </span>
                <span v-else class="text-gray-400 text-sm">-</span>
              </td>

              <!-- Kolom PJ Data / Penilai PJ Data -->
              <td class="px-4 py-3">
                <template v-if="user.kode_unit && (user.role === 'staf' || user.role.startsWith('PJ Data') || isPenilaiRole(user.role))">
                  <div class="flex items-center gap-3 justify-center">
                    <!-- PJ Data checkbox -->
                    <label class="flex items-center gap-1 cursor-pointer" :class="isPenilaiRole(user.role) ? 'opacity-50' : ''">
                      <input
                        type="checkbox"
                        :checked="user.role.startsWith('PJ Data') || pjDataModal?.user.id === user.id"
                        :disabled="isPenilaiRole(user.role)"
                        @change="handlePjDataCheckbox(user)"
                        class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500 cursor-pointer disabled:cursor-not-allowed"
                      />
                      <span class="text-xs text-gray-600 dark:text-gray-400">PJ</span>
                      <span v-if="getPjDataTims(user.role).length" class="text-xs text-teal-600 dark:text-teal-400 font-medium">
                        ({{ getPjDataTims(user.role).join(', ') }})
                      </span>
                    </label>
                    <!-- Penilai PJ Data checkbox -->
                    <label class="flex items-center gap-1 cursor-pointer" :class="user.role.startsWith('PJ Data') ? 'opacity-50' : ''">
                      <input
                        type="checkbox"
                        :checked="isPenilaiRole(user.role) || penilaiPjDataModal?.user.id === user.id"
                        :disabled="user.role.startsWith('PJ Data')"
                        @change="handlePenilaianPjDataCheckbox(user)"
                        class="h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer disabled:cursor-not-allowed"
                      />
                      <span class="text-xs text-gray-600 dark:text-gray-400">Penilai</span>
                      <span v-if="getPenilaiDataTims(user.role).length" class="text-xs text-orange-600 dark:text-orange-400 font-medium">
                        ({{ getPenilaiDataTims(user.role).join(', ') }})
                      </span>
                    </label>
                  </div>
                </template>
                <span v-else-if="!user.kode_unit && user.role === 'staf'" class="text-xs text-gray-400 block text-center italic" title="Assign unit terlebih dahulu">Perlu unit</span>
                <span v-else class="text-xs text-gray-400 block text-center">-</span>
              </td>

              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button @click="openView(user)" class="rounded p-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30" title="Lihat">
                    <Eye :size="16" />
                  </button>
                  <button @click="openEdit(user)" class="rounded p-1.5 text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/30" title="Edit">
                    <Pencil :size="16" />
                  </button>
                  <button @click="openDelete(user)" class="rounded p-1.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30" title="Hapus">
                    <Trash2 :size="16" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="paginatedUsers.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                {{ searchQuery ? 'Tidak ada data yang cocok' : 'Belum ada data pegawai' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between mt-3 px-1">
        <p class="text-xs text-gray-500 dark:text-gray-400">
          {{ (currentPage - 1) * itemsPerPage + 1 }}–{{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }} dari {{ filteredUsers.length }} data
        </p>
        <div class="flex items-center gap-1">
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-600 dark:text-gray-300 disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
          >‹</button>
          <template v-for="p in paginationPages" :key="String(p)">
            <span v-if="p === '...'" class="flex items-center justify-center w-8 h-8 text-sm text-gray-400 dark:text-gray-500 select-none">…</span>
            <button
              v-else
              @click="goToPage(p as number)"
              class="flex items-center justify-center w-8 h-8 rounded-lg border text-sm font-medium transition-colors"
              :class="p === currentPage
                ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
            >{{ p }}</button>
          </template>
          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-600 dark:text-gray-300 disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
          >›</button>
        </div>
      </div>
    </div>

    <!-- ===== MODAL VIEW ===== -->
    <Teleport to="body">
      <div v-if="showView" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showView = false">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-semibold dark:text-gray-100">Detail Pegawai</h4>
            <button @click="showView = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
          </div>
          <div v-if="viewItem" class="space-y-3">
            <div class="grid grid-cols-3 gap-2 text-sm">
              <span class="font-medium text-gray-600 dark:text-gray-400">Nama</span>
              <span class="col-span-2 dark:text-gray-100">{{ viewItem.name }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-sm">
              <span class="font-medium text-gray-600 dark:text-gray-400">NIP</span>
              <span class="col-span-2 dark:text-gray-100">{{ viewItem.nip || '-' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-sm">
              <span class="font-medium text-gray-600 dark:text-gray-400">Peran</span>
              <span class="col-span-2">
                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="{
                    'bg-purple-100 text-purple-700': viewItem.role === 'admin_mutu',
                    'bg-blue-100 text-blue-700': viewItem.role === 'kepala_unit',
                    'bg-teal-100 text-teal-700': viewItem.role.startsWith('PJ Data'),
                    'bg-gray-100 text-gray-700': viewItem.role === 'staf',
                    'bg-orange-100 text-orange-700': viewItem.role === 'penilai_pj_data',
                  }">
                  {{ getRoleDisplay(viewItem.role) }}
                </span>
              </span>
            </div>
            <div v-if="viewItem.role.startsWith('PJ Data')" class="grid grid-cols-3 gap-2 text-sm">
              <span class="font-medium text-gray-600 dark:text-gray-400">Tim PJ Data</span>
              <span class="col-span-2 text-teal-700 dark:text-teal-400">{{ getPjDataTims(viewItem.role).join(', ') || 'Semua Tim' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-sm">
              <span class="font-medium text-gray-600 dark:text-gray-400">Jabatan</span>
              <span class="col-span-2 dark:text-gray-100">{{ viewItem.status_pegawai || '-' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-sm">
              <span class="font-medium text-gray-600 dark:text-gray-400">Status Pegawai</span>
              <span class="col-span-2">
                <span v-if="viewItem.status_kerja" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusKerjaBadge[viewItem.status_kerja] ?? 'bg-gray-100 text-gray-700'">
                  {{ viewItem.status_kerja }}
                </span>
                <span v-else class="dark:text-gray-100">-</span>
              </span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-sm">
              <span class="font-medium text-gray-600 dark:text-gray-400">Unit</span>
              <span class="col-span-2 dark:text-gray-100">{{ viewItem.unit ? viewItem.unit.nama_unit : '-' }}</span>
            </div>
          </div>
          <div class="mt-6 flex justify-end">
            <button @click="showView = false" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md">
              <X :size="15" /> Tutup
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL ADD ===== -->
    <Teleport to="body">
      <div v-if="showAdd" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showAdd = false">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-semibold dark:text-gray-100">Tambah Pegawai</h4>
            <button @click="showAdd = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
          </div>

          <div class="space-y-4">
            <!-- Nama -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Nama <span class="text-red-500">*</span></span>
              <input v-model="formAdd.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
              <span v-if="addErrors.name" class="text-xs text-red-500 mt-1">{{ addErrors.name }}</span>
            </label>

            <!-- NIP -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">NIP</span>
              <input v-model="formAdd.nip" type="text" placeholder="Nomor Induk Pegawai" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
              <span v-if="addErrors.nip" class="text-xs text-red-500 mt-1">{{ addErrors.nip }}</span>
            </label>

            <!-- Password -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Password <span class="text-red-500">*</span></span>
              <input v-model="formAdd.password" type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
              <span v-if="addErrors.password" class="text-xs text-red-500 mt-1">{{ addErrors.password }}</span>
            </label>

            <!-- Peran -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Peran <span class="text-red-500">*</span></span>
              <select v-model="formAdd.base_role" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="" disabled>Pilih Peran...</option>
                <option value="admin_mutu">Admin Mutu</option>
                <option value="kepala_unit">Kepala Unit</option>
                <option value="staf">Staf</option>
              </select>
              <span v-if="addErrors.base_role" class="text-xs text-red-500 mt-1">{{ addErrors.base_role }}</span>
            </label>

            <!-- Jabatan (tipe kepegawaian) -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Jabatan</span>
              <select v-model="formAdd.status_pegawai" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Pilih Jabatan...</option>
                <option v-for="opt in jabatanOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </label>

            <!-- Status Pegawai (aktif/resign/dll) -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Status Pegawai</span>
              <select v-model="formAdd.status_kerja" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Pilih Status...</option>
                <option v-for="opt in statusKerjaOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </label>

            <!-- Unit -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Unit</span>
              <select v-model="formAdd.kode_unit" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Tidak ada unit</option>
                <option v-for="unit in units" :key="unit.id" :value="unit.kode_unit">{{ unit.kode_unit }} - {{ unit.nama_unit }}</option>
              </select>
            </label>

            <!-- PJ Data / Penilai PJ Data section (hanya staf + unit dipilih) -->
            <div v-if="formAdd.base_role === 'staf' && formAdd.kode_unit" class="rounded-lg border border-teal-200 bg-teal-50 dark:border-teal-800 dark:bg-teal-900/20 p-3 space-y-2">
              <!-- PJ Data checkbox -->
              <label class="flex items-center gap-2 cursor-pointer" :class="formAdd.is_penilai_pj_data ? 'opacity-40 pointer-events-none' : ''">
                <input type="checkbox" v-model="formAdd.is_pj_data" :disabled="formAdd.is_penilai_pj_data" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                <span class="text-sm font-medium text-teal-800 dark:text-teal-300">PJ Data</span>
                <span class="text-xs text-teal-600 dark:text-teal-400">(dapat mengisi capaian indikator)</span>
              </label>
              <div v-if="formAdd.is_pj_data && getTimsForUnit(formAdd.kode_unit).length > 0" class="mt-1 ml-6">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Pilih Tim <span class="text-gray-400">(maks. 2)</span></p>
                <div v-for="tim in getTimsForUnit(formAdd.kode_unit)" :key="tim.id" class="flex items-center gap-2 mb-1.5">
                  <input type="checkbox" :value="tim.nama_tim" v-model="selectedAddTims" @change="onAddTimChange" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">{{ tim.nama_tim }}</span>
                </div>
                <p v-if="addTimError" class="text-xs text-red-500 mt-1">{{ addTimError }}</p>
              </div>
              <!-- Divider -->
              <div class="border-t border-teal-200 dark:border-teal-700"></div>
              <!-- Penilai PJ Data checkbox -->
              <label class="flex items-center gap-2 cursor-pointer" :class="formAdd.is_pj_data ? 'opacity-40 pointer-events-none' : ''">
                <input type="checkbox" v-model="formAdd.is_penilai_pj_data" :disabled="formAdd.is_pj_data" class="h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500" />
                <span class="text-sm font-medium text-orange-800 dark:text-orange-300">Penilai PJ Data</span>
                <span class="text-xs text-orange-600 dark:text-orange-400">(dapat menilai, tidak bisa approve)</span>
              </label>
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-3">
            <button @click="showAdd = false" class="flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
              <X :size="15" /> Batal
            </button>
            <button @click="submitAdd" :disabled="isSubmitting" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700 shadow-md disabled:opacity-50">
              <Save :size="15" /> {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL EDIT (tanpa PJ Data section) ===== -->
    <Teleport to="body">
      <div v-if="showEdit" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showEdit = false">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-semibold dark:text-gray-100">Edit Pegawai</h4>
            <button @click="showEdit = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
          </div>

          <div class="space-y-4">
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Nama <span class="text-red-500">*</span></span>
              <input v-model="formEdit.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
              <span v-if="editErrors.name" class="text-xs text-red-500 mt-1">{{ editErrors.name }}</span>
            </label>

            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">NIP</span>
              <input v-model="formEdit.nip" type="text" placeholder="Nomor Induk Pegawai" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
              <span v-if="editErrors.nip" class="text-xs text-red-500 mt-1">{{ editErrors.nip }}</span>
            </label>

            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Password <span class="text-gray-400 text-xs">(kosongkan jika tidak diubah)</span></span>
              <input v-model="formEdit.password" type="password" placeholder="Kosongkan jika tidak diubah" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
              <span v-if="editErrors.password" class="text-xs text-red-500 mt-1">{{ editErrors.password }}</span>
            </label>

            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Peran <span class="text-red-500">*</span></span>
              <select v-model="formEdit.base_role" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="" disabled>Pilih Peran...</option>
                <option value="admin_mutu">Admin Mutu</option>
                <option value="kepala_unit">Kepala Unit</option>
                <option value="staf">Staf</option>
              </select>
              <span v-if="editErrors.base_role" class="text-xs text-red-500 mt-1">{{ editErrors.base_role }}</span>
            </label>

            <!-- Jabatan (tipe kepegawaian) -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Jabatan</span>
              <select v-model="formEdit.status_pegawai" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Pilih Jabatan...</option>
                <option v-for="opt in jabatanOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </label>

            <!-- Status Pegawai (aktif/resign/dll) -->
            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Status Pegawai</span>
              <select v-model="formEdit.status_kerja" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Pilih Status...</option>
                <option v-for="opt in statusKerjaOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </label>

            <label class="block text-sm">
              <span class="mb-1 block font-medium dark:text-gray-200">Unit</span>
              <select v-model="formEdit.kode_unit" @change="onEditKodeUnitChange" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <option value="">Tidak ada unit</option>
                <option v-for="unit in units" :key="unit.id" :value="unit.kode_unit">{{ unit.kode_unit }} - {{ unit.nama_unit }}</option>
              </select>
            </label>

            <!-- Info PJ Data (read-only) -->
            <div v-if="formEdit.is_pj_data" class="rounded-lg border border-teal-200 bg-teal-50 dark:border-teal-800 dark:bg-teal-900/20 px-3 py-2">
              <span class="text-xs text-teal-700 dark:text-teal-300">
                Pegawai ini berstatus <strong>PJ Data</strong>. Ubah via kolom PJ Data di tabel.
              </span>
            </div>
            <!-- Info Penilai PJ Data (read-only) -->
            <div v-if="formEdit.is_penilai_pj_data" class="rounded-lg border border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-900/20 px-3 py-2">
              <span class="text-xs text-orange-700 dark:text-orange-300">
                Pegawai ini berstatus <strong>Penilai PJ Data</strong> (dapat menilai, tidak bisa approve).
              </span>
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-3">
            <button @click="showEdit = false" class="flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
              <X :size="15" /> Batal
            </button>
            <button @click="submitEdit" :disabled="isSubmitting" class="flex items-center gap-2 rounded-lg bg-amber-600 px-5 py-2 text-white hover:bg-amber-700 shadow-md disabled:opacity-50">
              <Save :size="15" /> {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL DELETE ===== -->
    <Teleport to="body">
      <div v-if="showDelete" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="showDelete = false">
        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-semibold text-red-600 dark:text-red-400">Hapus Pegawai</h4>
            <button @click="showDelete = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
          </div>
          <p class="text-sm text-gray-700 dark:text-gray-300">Apakah Anda yakin ingin menghapus pegawai <strong>{{ deleteItem?.name }}</strong>?</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tindakan ini tidak dapat dibatalkan.</p>
          <div class="mt-6 flex justify-end gap-3">
            <button @click="showDelete = false" class="flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
              <X :size="15" /> Batal
            </button>
            <button @click="submitDelete" :disabled="isSubmitting" class="flex items-center gap-2 rounded-lg bg-red-600 px-5 py-2 text-white hover:bg-red-700 shadow-md disabled:opacity-50">
              <Trash2 :size="15" /> {{ isSubmitting ? 'Menghapus...' : 'Hapus' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL PJ DATA TIM SELECTION ===== -->
    <Teleport to="body">
      <div v-if="pjDataModal" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="pjDataModal = null">
        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-base font-semibold dark:text-gray-100">PJ Data</h4>
            <button @click="pjDataModal = null" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
          </div>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            <strong>{{ pjDataModal.user.name }}</strong> — Unit ini memiliki tim. Pilih yang dikelola (maks. 2).
          </p>
          <div class="space-y-2">
            <label v-for="tim in getTimsForUnit(pjDataModal.user.kode_unit || '')" :key="tim.id" class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" :value="tim.nama_tim" v-model="selectedPjTims" @change="onPjTimChange" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
              <span class="text-sm dark:text-gray-200">{{ tim.nama_tim }}</span>
            </label>
          </div>
          <p v-if="pjTimError" class="text-xs text-red-500 mt-2">{{ pjTimError }}</p>
          <div class="mt-6 flex items-center justify-between">
            <button
              v-if="pjDataModal?.user.role.startsWith('PJ Data')"
              @click="removePjDataFromModal"
              class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 transition-colors"
            >
              Hapus PJ Data
            </button>
            <span v-else />
            <div class="flex gap-2">
              <button @click="pjDataModal = null" class="rounded-lg border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
              <button @click="confirmPjData" class="rounded-lg bg-teal-600 px-4 py-1.5 text-sm text-white hover:bg-teal-700 shadow">Simpan</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODAL PENILAI PJ DATA TIM SELECTION ===== -->
    <Teleport to="body">
      <div v-if="penilaiPjDataModal" class="fixed inset-0 z-[9999] grid place-items-center bg-black/40 p-4" @click.self="penilaiPjDataModal = null">
        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-base font-semibold dark:text-gray-100">Penilai PJ Data</h4>
            <button @click="penilaiPjDataModal = null" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
          </div>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            <strong>{{ penilaiPjDataModal.user.name }}</strong> — Unit ini memiliki tim. Pilih yang dinilai (maks. 2).
          </p>
          <div class="space-y-2">
            <label v-for="tim in getTimsForUnit(penilaiPjDataModal.user.kode_unit || '')" :key="tim.id" class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" :value="tim.nama_tim" v-model="selectedPenilaiTims" @change="onPenilaiTimChange" class="h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500" />
              <span class="text-sm dark:text-gray-200">{{ tim.nama_tim }}</span>
            </label>
          </div>
          <p v-if="penilaiTimError" class="text-xs text-red-500 mt-2">{{ penilaiTimError }}</p>
          <div class="mt-6 flex items-center justify-between">
            <button
              v-if="isPenilaiRole(penilaiPjDataModal.user.role)"
              @click="removePenilaiPjDataFromModal"
              class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 transition-colors"
            >
              Hapus Penilai
            </button>
            <span v-else />
            <div class="flex gap-2">
              <button @click="penilaiPjDataModal = null" class="rounded-lg border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
              <button @click="confirmPenilaiPjData" class="rounded-lg bg-orange-600 px-4 py-1.5 text-sm text-white hover:bg-orange-700 shadow">Simpan</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
