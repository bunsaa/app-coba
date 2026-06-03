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
  return roleLabels[role] ?? role;
}

function parseRole(role: string): { base_role: string; is_pj_data: boolean; pj_data_tim: string } {
  if (role.startsWith('PJ Data - ')) {
    return { base_role: 'staf', is_pj_data: true, pj_data_tim: role.replace('PJ Data - ', '') };
  }
  if (role === 'PJ Data') {
    return { base_role: 'staf', is_pj_data: true, pj_data_tim: '' };
  }
  return { base_role: role, is_pj_data: false, pj_data_tim: '' };
}

function getPjDataTims(role: string): string[] {
  if (role.startsWith('PJ Data - ')) {
    return role.replace('PJ Data - ', '').split(', ').map(t => t.trim()).filter(Boolean);
  }
  return [];
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
const formAdd = ref({ name: '', nip: '', password: '', base_role: '', status_pegawai: '', status_kerja: '', kode_unit: '', is_pj_data: false });
const selectedAddTims = ref<string[]>([]);
const addTimError = ref('');
const addErrors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

// Edit — is_pj_data & pj_data_tim hidden, auto-populated to preserve status
const showEdit = ref(false);
const editItem = ref<Pegawai | null>(null);
const formEdit = ref({ name: '', nip: '', password: '', base_role: '', status_pegawai: '', status_kerja: '', kode_unit: '', is_pj_data: false, pj_data_tim: '' });
const editErrors = ref<Record<string, string>>({});

// Delete
const showDelete = ref(false);
const deleteItem = ref<Pegawai | null>(null);

// PJ Data inline modal (tabel)
const pjDataModal = ref<{ user: Pegawai } | null>(null);
const selectedPjTims = ref<string[]>([]);
const pjTimError = ref('');

/* ====== Watchers ====== */
watch(() => formAdd.value.base_role, (val) => {
  if (val !== 'staf') {
    formAdd.value.is_pj_data = false;
    selectedAddTims.value = [];
    addTimError.value = '';
  }
});
watch(() => formAdd.value.is_pj_data, (val) => {
  if (!val) {
    selectedAddTims.value = [];
    addTimError.value = '';
  }
});
watch(() => formAdd.value.kode_unit, () => {
  formAdd.value.is_pj_data = false;
  selectedAddTims.value = [];
  addTimError.value = '';
});

watch(() => formEdit.value.kode_unit, () => {
  formEdit.value.is_pj_data = false;
  formEdit.value.pj_data_tim = '';
});

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
  const tims = getTimsForUnit(user.kode_unit);
  if (tims.length === 0) {
    // Unit tanpa tim: toggle langsung
    if (user.role.startsWith('PJ Data')) {
      savePjDataUpdate(user, false, '');
    } else {
      savePjDataUpdate(user, true, '');
    }
  } else {
    // Unit punya tim: selalu buka modal (pre-fill jika sudah PJ Data)
    selectedPjTims.value = getPjDataTims(user.role);
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
  router.put(`/manajemen-pegawai/${user.id}`, {
    name: user.name,
    nip: user.nip || '',
    password: '',
    base_role: 'staf',
    status_pegawai: user.status_pegawai || '',
    status_kerja: user.status_kerja || '',
    kode_unit: user.kode_unit || '',
    is_pj_data: isPj,
    pj_data_tim: timStr,
  }, { preserveScroll: true });
}

/* ====== Modal Actions ====== */
function openView(user: Pegawai) {
  viewItem.value = user;
  showView.value = true;
}

function openAdd() {
  formAdd.value = { name: '', nip: '', password: '', base_role: '', status_pegawai: '', status_kerja: '', kode_unit: '', is_pj_data: false };
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
              <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-700 w-28">PJ Data</th>
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

              <!-- Kolom PJ Data -->
              <td class="px-4 py-3 text-center">
                <template v-if="user.role === 'staf' || user.role.startsWith('PJ Data')">
                  <div class="flex items-center gap-1.5 justify-center flex-wrap">
                    <input
                      type="checkbox"
                      :checked="user.role.startsWith('PJ Data') || pjDataModal?.user.id === user.id"
                      :disabled="!user.kode_unit"
                      @change="handlePjDataCheckbox(user)"
                      class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500 cursor-pointer disabled:cursor-not-allowed"
                    />
                    <span v-if="getPjDataTims(user.role).length" class="text-xs text-teal-600 dark:text-teal-400 font-medium">
                      {{ getPjDataTims(user.role).join(', ') }}
                    </span>
                  </div>
                </template>
                <span v-else class="text-xs text-gray-400">-</span>
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
      <div v-if="totalPages > 1" class="flex items-center justify-between">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Menampilkan {{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }} dari {{ filteredUsers.length }} data
        </p>
        <div class="flex items-center gap-1">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="rounded px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200">&laquo;</button>
          <template v-for="p in totalPages" :key="p">
            <button @click="goToPage(p)" class="rounded px-3 py-1.5 text-sm border transition-colors" :class="p === currentPage ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200'">{{ p }}</button>
          </template>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" class="rounded px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200">&raquo;</button>
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

            <!-- PJ Data section (hanya staf + unit dipilih) -->
            <div v-if="formAdd.base_role === 'staf' && formAdd.kode_unit" class="rounded-lg border border-teal-200 bg-teal-50 dark:border-teal-800 dark:bg-teal-900/20 p-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="formAdd.is_pj_data" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                <span class="text-sm font-medium text-teal-800 dark:text-teal-300">PJ Data</span>
                <span class="text-xs text-teal-600 dark:text-teal-400">(dapat mengisi capaian indikator)</span>
              </label>
              <div v-if="formAdd.is_pj_data && getTimsForUnit(formAdd.kode_unit).length > 0" class="mt-3 ml-6">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Pilih Tim <span class="text-gray-400">(maks. 2)</span></p>
                <div v-for="tim in getTimsForUnit(formAdd.kode_unit)" :key="tim.id" class="flex items-center gap-2 mb-1.5">
                  <input type="checkbox" :value="tim.nama_tim" v-model="selectedAddTims" @change="onAddTimChange" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">{{ tim.nama_tim }}</span>
                </div>
                <p v-if="addTimError" class="text-xs text-red-500 mt-1">{{ addTimError }}</p>
              </div>
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
              <select v-model="formEdit.kode_unit" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
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
  </AppLayout>
</template>
