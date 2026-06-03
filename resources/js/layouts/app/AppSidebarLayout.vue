<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AutoLogoutWarning from '@/components/AutoLogoutWarning.vue';
import { useIdleTimer } from '@/composables/useIdleTimer';
import { executeAllAutoSaves } from '@/composables/useAutoSaveRegistry';
import type { BreadcrumbItemType } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const user = computed(() => (page.props as any).auth?.user);

// Auto-logout setelah 1 jam tidak aktif
const handleAutoLogout = async () => {
    await executeAllAutoSaves()
    router.post('/logout')
}

const { isWarningVisible, secondsRemaining, resetActivity } = useIdleTimer(handleAutoLogout)
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>

    <AutoLogoutWarning
        v-if="isWarningVisible"
        :seconds="secondsRemaining"
        @stay-active="resetActivity"
        @logout-now="handleAutoLogout"
    />
</template>
