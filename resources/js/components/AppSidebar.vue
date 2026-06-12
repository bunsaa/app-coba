<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import {
  ClipboardPenLineIcon,
  ChartColumnIncreasingIcon,
  ChevronDown,
  GaugeCircleIcon,
  LayoutDashboardIcon,
  SearchCheckIcon,
  UsersIcon,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import AppLogo from './AppLogo.vue';

const openDashboard = ref(true);

const page = usePage();
const currentPath = computed(() => page?.url || '');

const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => user.value?.role === 'admin_mutu');

const modeAkses = computed(() => (page.props as any).modeAkses);
const isMutuMode = computed(() => modeAkses.value === 'mutu');

const footerNavItems: never[] = [];
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <!-- Logo -->
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <Link :href="isMutuMode ? dashboard() : '/penilaian-perilaku-home'">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <!-- Menu -->
    <SidebarContent>
      <SidebarMenu>

        <!-- ======= MODE MUTU: hanya menu MUTU (tanpa Penilaian Perilaku) ======= -->
        <template v-if="isMutuMode">
          <SidebarMenuItem>
            <SidebarMenuButton as-child>
              <button
                type="button"
                class="w-full flex items-center gap-2"
                @click="openDashboard = !openDashboard"
              >
                <GaugeCircleIcon :size="18" />
                <span class="flex-1 text-left">MUTU</span>
                <ChevronDown
                  :size="16"
                  class="transition-transform"
                  :class="openDashboard ? 'rotate-180' : ''"
                />
              </button>
            </SidebarMenuButton>

            <transition name="fade" mode="out-in">
              <div v-show="openDashboard" class="mt-1 ml-6 space-y-1">
                <SidebarMenuButton as-child>
                  <Link
                    href="/dashboard"
                    class="flex items-center gap-2"
                    :class="currentPath.startsWith('/dashboard') ? 'bg-accent/50 text-foreground rounded-md' : ''"
                  >
                    <LayoutDashboardIcon :size="16" />
                    <span>Dashboard</span>
                  </Link>
                </SidebarMenuButton>

                <SidebarMenuButton v-if="isAdmin" as-child>
                  <Link
                    href="/indikator"
                    class="flex items-center gap-2"
                    :class="currentPath.startsWith('/indikator') ? 'bg-accent/50 text-foreground rounded-md' : ''"
                  >
                    <ChartColumnIncreasingIcon :size="16" />
                    <span>Indikator</span>
                  </Link>
                </SidebarMenuButton>

                <SidebarMenuButton v-if="!isAdmin" as-child>
                  <Link
                    href="/capaian-indikator"
                    class="flex items-center gap-2"
                    :class="currentPath.startsWith('/capaian-indikator') ? 'bg-accent/50 text-foreground rounded-md' : ''"
                  >
                    <ClipboardPenLineIcon :size="16" />
                    <span>Capaian Indikator</span>
                  </Link>
                </SidebarMenuButton>

                <SidebarMenuButton v-if="isAdmin" as-child>
                  <Link
                    href="/verifikasi-capaian-indikator"
                    class="flex items-center gap-2"
                    :class="currentPath.startsWith('/verifikasi-capaian-indikator') ? 'bg-accent/50 text-foreground rounded-md' : ''"
                  >
                    <SearchCheckIcon :size="16" />
                    <span>Verifikasi Capaian Indikator</span>
                  </Link>
                </SidebarMenuButton>

                <SidebarMenuButton v-if="isAdmin" as-child>
                  <Link
                    href="/manajemen-pegawai"
                    class="flex items-center gap-2"
                    :class="currentPath.startsWith('/manajemen-pegawai') ? 'bg-accent/50 text-foreground rounded-md' : ''"
                  >
                    <UsersIcon :size="16" />
                    <span>Manajemen Pegawai</span>
                  </Link>
                </SidebarMenuButton>
              </div>
            </transition>
          </SidebarMenuItem>
        </template>


      </SidebarMenu>
    </SidebarContent>

    <!-- Footer -->
    <SidebarFooter>
      <NavFooter :items="footerNavItems" />
      <NavUser />
    </SidebarFooter>
  </Sidebar>

  <slot />
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
