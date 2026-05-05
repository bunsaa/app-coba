<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Moon, Settings, Sun } from 'lucide-vue-next';
import { useDarkMode } from '@/composables/useDarkMode';
import { ref, computed } from 'vue';
import axios from 'axios';

interface Props {
    user: User;
}

const handleLogout = () => {
    router.flushAll();
};

const { isDark, toggleDarkMode } = useDarkMode();

defineProps<Props>();

// Mode toggle
const page = usePage();
const modeAkses = computed(() => (page.props as any).modeAkses);
const isMutuMode = computed(() => modeAkses.value === 'mutu');
const isPerilakuMode = computed(() => modeAkses.value === 'perilaku');
const toggling = ref(false);

async function toggleMode() {
    if (toggling.value) return;
    toggling.value = true;
    const newMode = isMutuMode.value ? 'perilaku' : 'mutu';
    try {
        await axios.post('/set-mode', { mode: newMode });
        if (newMode === 'mutu') {
            router.visit('/dashboard');
        } else {
            router.visit('/penilaian-perilaku-home');
        }
    } finally {
        toggling.value = false;
    }
}
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit()" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem @click="toggleDarkMode" class="cursor-pointer">
            <Sun v-if="isDark" class="mr-2 h-4 w-4" />
            <Moon v-else class="mr-2 h-4 w-4" />
            {{ isDark ? 'Light Mode' : 'Dark Mode' }}
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <!-- Mode Toggle (hanya tampil jika mode sudah dipilih) -->
    <template v-if="isMutuMode || isPerilakuMode">
        <DropdownMenuSeparator />
        <div class="px-2 py-1.5">
            <p class="mb-1.5 px-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Mode Akses</p>
            <button
                type="button"
                :disabled="toggling"
                @click="toggleMode"
                class="flex w-full items-center justify-between rounded-lg px-3 py-2 transition-colors hover:bg-accent disabled:opacity-60"
                :class="isMutuMode ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-indigo-50 dark:bg-indigo-900/20'"
            >
                <!-- Label MUTU -->
                <span
                    class="text-xs font-semibold"
                    :class="isMutuMode ? 'text-blue-600 dark:text-blue-400' : 'text-muted-foreground'"
                >MUTU</span>

                <!-- Toggle switch -->
                <div
                    class="relative h-6 w-11 rounded-full transition-colors duration-300"
                    :class="isPerilakuMode ? 'bg-indigo-500' : 'bg-blue-500'"
                >
                    <span
                        class="absolute top-1 h-4 w-4 rounded-full bg-white shadow-md transition-transform duration-300"
                        :class="isPerilakuMode ? 'left-1 translate-x-5' : 'left-1 translate-x-0'"
                    />
                    <span v-if="toggling" class="absolute inset-0 flex items-center justify-center">
                        <svg class="h-3 w-3 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </span>
                </div>

                <!-- Label Perilaku -->
                <span
                    class="text-xs font-semibold"
                    :class="isPerilakuMode ? 'text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground'"
                >Perilaku</span>
            </button>
        </div>
    </template>

    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
