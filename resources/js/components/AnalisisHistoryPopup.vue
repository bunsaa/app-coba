<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { Clock, X } from 'lucide-vue-next';

export type AnalisisHistoryEntry = {
    analisis: string;
    rtl: string;
    changed_at: string;
    changed_by: string;
    changed_by_role: string;
};

const props = defineProps<{
    history: AnalisisHistoryEntry[];
    bulan: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="emit('close')">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col mx-4">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <Clock :size="16" class="text-indigo-500" />
                    Riwayat Analisis &amp; RTL
                    <span class="uppercase text-indigo-600 font-bold">{{ bulan }}</span>
                </div>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <X :size="18" />
                </button>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto flex-1 px-4 py-3 space-y-4">
                <p v-if="history.length === 0" class="text-sm text-gray-500 text-center py-4">
                    Belum ada riwayat perubahan.
                </p>
                <div
                    v-for="(entry, idx) in history"
                    :key="idx"
                    class="border border-gray-200 rounded-lg p-3 bg-gray-50 text-sm"
                >
                    <!-- Meta -->
                    <div class="flex items-center gap-1 text-xs text-gray-500 mb-2 flex-wrap">
                        <span class="font-medium text-gray-700">{{ entry.changed_by }}</span>
                        <span>&bull;</span>
                        <span class="text-indigo-600">{{ entry.changed_by_role }}</span>
                        <span>&bull;</span>
                        <span>{{ formatDate(entry.changed_at) }}</span>
                    </div>
                    <!-- Analisis -->
                    <div v-if="entry.analisis" class="mb-1">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Analisis:</span>
                        <p class="text-gray-700 whitespace-pre-wrap mt-0.5">{{ entry.analisis }}</p>
                    </div>
                    <!-- RTL -->
                    <div v-if="entry.rtl">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">RTL:</span>
                        <p class="text-gray-700 whitespace-pre-wrap mt-0.5">{{ entry.rtl }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
