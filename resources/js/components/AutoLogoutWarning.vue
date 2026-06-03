<script setup lang="ts">
import { computed } from 'vue'
import { AlertTriangle, Clock, RefreshCw, LogOut } from 'lucide-vue-next'

const props = defineProps<{
    seconds: number
}>()

const emit = defineEmits<{
    'stay-active': []
    'logout-now': []
}>()

const formattedTime = computed(() => {
    const m = Math.floor(props.seconds / 60)
    const s = props.seconds % 60
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const urgencyClass = computed(() => {
    if (props.seconds <= 60) return 'text-red-600 dark:text-red-400'
    if (props.seconds <= 120) return 'text-orange-500 dark:text-orange-400'
    return 'text-yellow-600 dark:text-yellow-400'
})
</script>

<template>
    <!-- Overlay backdrop -->
    <div class="fixed inset-0 z-[9998] bg-black/50 backdrop-blur-sm" />

    <!-- Modal -->
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div
            class="w-full max-w-sm rounded-2xl border border-yellow-200 bg-white shadow-2xl dark:border-yellow-800 dark:bg-gray-900"
        >
            <!-- Header -->
            <div class="flex items-center gap-3 border-b border-yellow-100 px-6 pt-5 pb-4 dark:border-yellow-900">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900/50">
                    <AlertTriangle class="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Sesi Akan Berakhir
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Tidak ada aktivitas terdeteksi
                    </p>
                </div>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 text-center">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                    Sesi Anda akan berakhir otomatis dalam
                </p>

                <!-- Countdown -->
                <div class="mb-4 flex items-center justify-center gap-2">
                    <Clock class="h-5 w-5" :class="urgencyClass" />
                    <span class="text-4xl font-bold tabular-nums" :class="urgencyClass">
                        {{ formattedTime }}
                    </span>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Semua isian yang belum disimpan akan otomatis tersimpan sebelum logout.
                </p>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    @click="emit('stay-active')"
                >
                    <RefreshCw class="h-4 w-4" />
                    Perpanjang Sesi
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-900"
                    @click="emit('logout-now')"
                >
                    <LogOut class="h-4 w-4" />
                    Logout
                </button>
            </div>
        </div>
    </div>
</template>
