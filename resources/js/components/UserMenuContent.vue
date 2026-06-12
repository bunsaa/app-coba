<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Moon, Settings, Sun } from 'lucide-vue-next';
import { useDarkMode } from '@/composables/useDarkMode';

interface Props {
    user: User;
}

const handleLogout = () => {
    router.post('/logout', {}, {
        onSuccess: () => { window.location.href = '/login'; },
        onError: () => { window.location.href = '/login'; },
    });
};

const { isDark, toggleDarkMode } = useDarkMode();

defineProps<Props>();

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

    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <button
            type="button"
            class="block w-full flex items-center"
            @click="handleLogout"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </button>
    </DropdownMenuItem>
</template>
