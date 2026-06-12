<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { SparklesIcon, RefreshCwIcon } from 'lucide-vue-next';

const props = defineProps<{
  indikator: string;
  jenis?: string;
  standar?: string;
  satuan?: string;
  n?: number | null;
  d?: number | null;
  bulan?: string;
  unit?: string;
  delay?: number;
  validated?: boolean;
  currentAnalisis?: string;
  currentRtl?: string;
}>();

const emit = defineEmits<{
  (e: 'apply', analisis: string, rtl: string): void;
}>();

const loading = ref(false);
const error = ref('');
const analisisResult = ref('');
const rtlResult = ref('');

// Jika analisis/RTL sudah ada isinya, tampilkan "Tambahkan Rekomendasi" bukan "Terapkan ke Form"
const hasExistingContent = computed(() =>
  !!(props.currentAnalisis?.trim() || props.currentRtl?.trim())
);

async function generate() {
  loading.value = true;
  error.value = '';
  analisisResult.value = '';
  rtlResult.value = '';

  try {
    const resp = await axios.post('/rekomendasi-ai', {
      indikator: props.indikator,
      jenis: props.jenis,
      standar: props.standar,
      satuan: props.satuan,
      n: props.n,
      d: props.d,
      bulan: props.bulan,
      unit: props.unit,
    });

    analisisResult.value = resp.data.analisis ?? '';
    rtlResult.value = resp.data.rtl ?? '';
  } catch (err: any) {
    error.value = err.response?.data?.error ?? 'Gagal memuat rekomendasi AI.';
  } finally {
    loading.value = false;
  }
}

function apply() {
  if (hasExistingContent.value) {
    // Tambahkan: gabungkan isi lama + rekomendasi AI
    const combined_analisis = [props.currentAnalisis?.trim(), analisisResult.value].filter(Boolean).join('\n');
    const combined_rtl = [props.currentRtl?.trim(), rtlResult.value].filter(Boolean).join('\n');
    emit('apply', combined_analisis, combined_rtl);
  } else {
    emit('apply', analisisResult.value, rtlResult.value);
  }
}

onMounted(() => {
  setTimeout(() => generate(), props.delay ?? 0);
});
</script>

<template>
  <div class="mt-2 rounded-lg border border-violet-200 bg-violet-50 dark:bg-violet-900/10 dark:border-violet-800 p-3 space-y-3">

    <!-- Header -->
    <div class="group flex items-center justify-between">
      <span class="flex items-center gap-1 text-xs font-semibold text-violet-700 dark:text-violet-300">
        <SparklesIcon :size="12" />
        Rekomendasi AI
      </span>
      <button
        type="button"
        @click="generate"
        :disabled="loading"
        class="flex items-center gap-1 rounded-md bg-violet-600 px-2 py-0.5 text-xs font-medium text-white hover:bg-violet-700 disabled:opacity-40 transition-all opacity-0 group-hover:opacity-100"
        title="Generate ulang"
      >
        <RefreshCwIcon :size="11" :class="loading ? 'animate-spin' : ''" />
        Refresh
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-2 animate-pulse">
      <div class="h-3 bg-violet-200 rounded w-3/4"></div>
      <div class="h-3 bg-violet-200 rounded w-full"></div>
      <div class="h-3 bg-violet-200 rounded w-5/6"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="space-y-2">
      <p class="text-xs text-red-500">{{ error }}</p>
      <button
        type="button"
        @click="generate"
        class="flex w-full items-center justify-center gap-1 rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100 transition-colors"
      >
        <RefreshCwIcon :size="11" />
        Coba Lagi
      </button>
    </div>

    <!-- Hasil -->
    <template v-else-if="analisisResult">
      <div>
        <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Analisis</p>
        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ analisisResult }}</p>
      </div>

      <div>
        <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Rencana Tindak Lanjut (RTL)</p>
        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ rtlResult }}</p>
      </div>

      <button
        v-if="!validated"
        type="button"
        @click="apply"
        class="w-full rounded-md bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-700 transition-colors"
      >
        {{ hasExistingContent ? 'Tambahkan Rekomendasi' : 'Terapkan ke Form' }}
      </button>
    </template>

  </div>
</template>
