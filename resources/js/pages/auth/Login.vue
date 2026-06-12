<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const showPassword = ref(false);
const captchaImage = ref('');
const captchaInput = ref('');
const captchaLoading = ref(false);

const modePerilaku = ref(false);
const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const refreshCaptcha = async () => {
    captchaLoading.value = true;
    try {
        const { data } = await axios.get('/captcha');
        captchaImage.value = data.image;
    } catch {
        captchaImage.value = '';
    } finally {
        captchaLoading.value = false;
    }
};
const onFormError = () => { captchaInput.value = ''; refreshCaptcha(); };
onMounted(() => refreshCaptcha());
</script>

<template>
    <Head title="Login — MUTU RSUD Tarakan" />

    <!-- Full-screen dark background -->
    <div
        class="relative flex h-screen w-screen items-center justify-center overflow-hidden"
        style="background: linear-gradient(135deg, #020617 0%, #0c1a3e 35%, #0d2d6e 65%, #0c4a8a 100%);"
    >
        <!-- Grid overlay -->
        <div class="pointer-events-none absolute inset-0"
            style="background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px); background-size: 60px 60px;"></div>

        <!-- Glow orbs -->
        <div class="pointer-events-none absolute -left-40 -top-40 h-[500px] w-[500px] rounded-full opacity-25"
            style="background: radial-gradient(circle, #1d4ed8 0%, transparent 60%); filter: blur(80px);"></div>
        <div class="pointer-events-none absolute -bottom-40 -right-20 h-[400px] w-[400px] rounded-full opacity-20"
            style="background: radial-gradient(circle, #0891b2 0%, transparent 60%); filter: blur(80px);"></div>
        <div class="pointer-events-none absolute left-1/2 top-1/3 h-96 w-96 -translate-x-1/2 rounded-full opacity-10"
            style="background: radial-gradient(circle, #6366f1 0%, transparent 60%); filter: blur(60px);"></div>

        <!-- Medical cross watermark -->
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-[0.025]">
            <svg width="600" height="600" viewBox="0 0 600 600" fill="white">
                <rect x="220" y="0" width="160" height="600" rx="30"/>
                <rect x="0" y="220" width="600" height="160" rx="30"/>
            </svg>
        </div>

        <!-- ════════════════════════════
             MAIN CARD
        ════════════════════════════ -->
        <div
            class="relative z-10 flex w-full max-w-[860px] overflow-hidden rounded-3xl shadow-[0_40px_100px_-20px_rgba(0,0,0,0.7)] dark:ring-1 dark:ring-white/[0.07]"
            style="max-height: calc(100vh - 32px);"
        >

            <!-- ── LEFT: Branding panel ── -->
            <div
                class="relative hidden w-[42%] flex-col justify-between overflow-hidden p-9 lg:flex"
                style="background: linear-gradient(160deg, #050d24 0%, #0f2461 50%, #0c3d87 100%);"
            >
                <!-- Inner grid -->
                <div class="pointer-events-none absolute inset-0"
                    style="background-image: linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px); background-size: 30px 30px;"></div>

                <!-- Glow inside left panel -->
                <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full opacity-30"
                    style="background: radial-gradient(circle, #0ea5e9 0%, transparent 60%); filter: blur(50px);"></div>
                <div class="pointer-events-none absolute -top-10 -left-10 h-48 w-48 rounded-full opacity-20"
                    style="background: radial-gradient(circle, #6366f1 0%, transparent 60%); filter: blur(40px);"></div>

                <!-- Hospital cross decoration -->
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-[0.04]">
                    <svg width="300" height="300" viewBox="0 0 300 300" fill="white">
                        <rect x="110" y="0" width="80" height="300" rx="15"/>
                        <rect x="0" y="110" width="300" height="80" rx="15"/>
                    </svg>
                </div>

                <!-- TOP: Logo + nama RS -->
                <div class="relative z-10 flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/10 ring-1 ring-white/20 backdrop-blur-sm">
                        <img src="/images/logo-rsud-tarakan.png" alt="Logo"
                            class="h-8 w-8 object-contain"
                            @error="($event.target as HTMLImageElement).style.display='none'"/>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-sky-300/70">RSUD Tarakan</p>
                        <!-- <p class="text-xs font-bold text-white/90">Kota Tarakan</p> -->
                    </div>
                </div>

                <!-- MIDDLE: Main branding -->
                <div class="relative z-10">
                    <!-- <div class="mb-5 inline-flex items-center gap-1.5 rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 backdrop-blur-sm">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
                        <span class="text-[11px] font-medium text-sky-300">Sistem Online</span>
                    </div> -->

                    <h1 class="mb-3 text-[26px] font-black leading-none tracking-tight text-white">
                        Sistem<br/>Manajemen<br/>
                        <span style="background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            Mutu Terpadu
                        </span>
                    </h1>
                    <p class="mb-6 text-[13px] leading-relaxed text-slate-400">
                        Platform digital monitoring mutu pegawai RSUD Tarakan.
                    </p>

                    <!-- 2×2 feature grid -->
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-white/10 bg-white/[0.05] p-3 backdrop-blur-sm">
                            <div class="mb-1.5 flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/25">
                                <svg class="h-3.5 w-3.5 text-blue-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            </div>
                            <p class="text-[11px] font-semibold text-white/80">Monitoring</p>
                            <p class="text-[10px] text-white/40">Capaian indikator</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/[0.05] p-3 backdrop-blur-sm">
                            <div class="mb-1.5 flex h-7 w-7 items-center justify-center rounded-lg bg-cyan-500/25">
                                <svg class="h-3.5 w-3.5 text-cyan-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                            </div>
                            <p class="text-[11px] font-semibold text-white/80">Validasi</p>
                            <p class="text-[10px] text-white/40">Approval terstruktur</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/[0.05] p-3 backdrop-blur-sm">
                            <div class="mb-1.5 flex h-7 w-7 items-center justify-center rounded-lg bg-violet-500/25">
                                <svg class="h-3.5 w-3.5 text-violet-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <p class="text-[11px] font-semibold text-white/80">Penilaian</p>
                            <p class="text-[10px] text-white/40">Perilaku pegawai</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/[0.05] p-3 backdrop-blur-sm">
                            <div class="mb-1.5 flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/25">
                                <svg class="h-3.5 w-3.5 text-emerald-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            </div>
                            <p class="text-[11px] font-semibold text-white/80">Laporan</p>
                            <p class="text-[10px] text-white/40">Dashboard komprehensif</p>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM -->
                <div class="relative z-10">
                    <p class="text-[10px] text-white/25">
                        &copy; {{ new Date().getFullYear() }} by ukfia &mdash; Datin RSUD Tarakan
                    </p>
                </div>
            </div>

            <!-- ── RIGHT: Form panel ── -->
            <div class="flex flex-1 flex-col items-center justify-center overflow-y-auto bg-white px-8 py-8 sm:px-10 dark:bg-[#131c2e] dark:border-l dark:border-white/[0.06]">
                <div class="w-full max-w-[320px]">

                    <!-- Logo + Heading -->
                    <div class="mb-6 text-center">
                        <div class="mb-4 flex justify-center">
                            <img src="/images/logo-rsud-tarakan.png" alt="RSUD Tarakan"
                                class="h-16 w-auto object-contain drop-shadow"
                                @error="($event.target as HTMLImageElement).style.display='none'"/>
                        </div>
                        <h2 class="text-[22px] font-extrabold text-slate-800 dark:text-slate-100">Selamat Datang</h2>
                        <p class="mt-1 text-[13px] text-slate-400 dark:text-slate-400">Masuk menggunakan NIP &amp; password</p>
                    </div>

                    <!-- Status alert -->
                    <div v-if="status"
                        class="mb-5 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-[13px] font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:ring-emerald-800/50">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        {{ status }}
                    </div>

                    <!-- Form -->
                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password', 'captcha']"
                        @error="onFormError"
                        v-slot="{ errors, processing }"
                        class="space-y-4"
                    >
                        <input type="hidden" name="_token" :value="csrfToken">
                        <!-- NIP -->
                        <div>
                            <label for="nip" class="mb-1.5 block text-[12px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">NIP</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[15px] w-[15px] -translate-y-1/2 text-slate-300 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <Input id="nip" type="text" name="nip" required autofocus :tabindex="1" autocomplete="off"
                                    placeholder="Masukkan NIP Anda"
                                    class="h-11 rounded-xl border-slate-200 bg-slate-50 pl-10 text-[13px] font-medium text-slate-700 placeholder:font-normal placeholder:text-slate-300 transition-all focus-visible:border-blue-400 focus-visible:bg-white focus-visible:ring-4 focus-visible:ring-blue-50 dark:border-white/[0.1] dark:bg-[#0d1626] dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus-visible:border-blue-500/60 dark:focus-visible:bg-[#0d1626] dark:focus-visible:ring-blue-500/10"/>
                            </div>
                            <InputError class="mt-1 text-[11px]" :message="errors.nip"/>
                        </div>

                        <!-- Password -->
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="password" class="text-[12px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Password</label>
                                <Link v-if="canResetPassword" :href="request()" :tabindex="6"
                                    class="text-[11px] font-semibold text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                    Lupa password?
                                </Link>
                            </div>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[15px] w-[15px] -translate-y-1/2 text-slate-300 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                <Input id="password" :type="showPassword ? 'text' : 'password'"
                                    name="password" required :tabindex="2" autocomplete="current-password"
                                    placeholder="Masukkan password"
                                    class="h-11 rounded-xl border-slate-200 bg-slate-50 pl-10 pr-11 text-[13px] font-medium text-slate-700 placeholder:font-normal placeholder:text-slate-300 transition-all focus-visible:border-blue-400 focus-visible:bg-white focus-visible:ring-4 focus-visible:ring-blue-50 dark:border-white/[0.1] dark:bg-[#0d1626] dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus-visible:border-blue-500/60 dark:focus-visible:bg-[#0d1626] dark:focus-visible:ring-blue-500/10"/>
                                <button type="button" @click="showPassword = !showPassword" :tabindex="7"
                                    class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-300 transition-colors hover:text-slate-500 dark:text-slate-500 dark:hover:text-slate-300">
                                    <svg v-if="!showPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                                </button>
                            </div>
                            <InputError class="mt-1 text-[11px]" :message="errors.password"/>
                        </div>

                        <!-- Captcha -->
                        <div>
                            <label class="mb-1.5 block text-[12px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Verifikasi</label>
                            <div class="flex gap-2">
                                <div class="flex h-11 w-[110px] shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-white/[0.1] dark:bg-[#0d1626]">
                                    <img v-if="captchaImage && !captchaLoading" :src="captchaImage" alt="Captcha" class="h-full w-full object-contain"/>
                                    <div v-else class="flex items-center gap-1.5 text-[11px] text-slate-400">
                                        <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Memuat...
                                    </div>
                                </div>
                                <button type="button" @click="refreshCaptcha" :disabled="captchaLoading" title="Muat ulang"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-300 transition-all hover:border-blue-300 hover:bg-blue-50 hover:text-blue-500 disabled:opacity-40 dark:border-white/[0.1] dark:text-slate-500 dark:hover:border-blue-500/50 dark:hover:bg-blue-500/10 dark:hover:text-blue-400"
                                    :class="captchaLoading ? 'animate-spin' : ''">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                                </button>
                                <Input id="captcha" type="text" name="captcha" v-model="captchaInput"
                                    required :tabindex="3" autocomplete="off" placeholder="Jawaban"
                                    class="h-11 flex-1 rounded-xl border-slate-200 bg-slate-50 text-[13px] font-medium text-slate-700 placeholder:font-normal placeholder:text-slate-300 transition-all focus-visible:border-blue-400 focus-visible:bg-white focus-visible:ring-4 focus-visible:ring-blue-50 dark:border-white/[0.1] dark:bg-[#0d1626] dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus-visible:border-blue-500/60 dark:focus-visible:bg-[#0d1626] dark:focus-visible:ring-blue-500/10"/>
                            </div>
                            <InputError class="mt-1 text-[11px]" :message="errors.captcha"/>
                        </div>

                        <!-- Remember + Mode Penilaian (satu baris) -->
                        <input type="hidden" name="mode" :value="modePerilaku ? 'perilaku' : 'mutu'">
                        <div class="flex items-center justify-between">
                            <label for="remember" class="flex cursor-pointer items-center gap-2.5">
                                <Checkbox id="remember" name="remember" :tabindex="4"/>
                                <span class="text-[12px] text-slate-500 dark:text-slate-400">Ingat saya di perangkat ini</span>
                            </label>
                            <label for="mode_perilaku" class="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" id="mode_perilaku" v-model="modePerilaku" class="sr-only" :tabindex="4">
                                <div class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition-all"
                                    :class="modePerilaku ? 'border-violet-500 bg-violet-500' : 'border-slate-300 bg-white dark:border-slate-600 dark:bg-transparent'">
                                    <svg v-if="modePerilaku" class="h-2.5 w-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                </div>
                                <span class="text-[12px] transition-colors"
                                    :class="modePerilaku ? 'text-violet-600 font-medium dark:text-violet-400' : 'text-slate-500 dark:text-slate-400'">
                                    <!-- Penilaian Perilaku -->
                                    <!-- <span class="text-[10px] font-normal opacity-60">(tgl 15–5)</span> -->
                                </span>
                            </label>
                        </div>

                        <!-- Submit -->
                        <button type="submit" :tabindex="5" :disabled="processing"
                            class="group relative flex h-11 w-full items-center justify-center gap-2 overflow-hidden rounded-xl text-[13px] font-bold text-white shadow-lg shadow-blue-600/30 transition-all hover:shadow-blue-600/50 hover:brightness-105 active:scale-[.99] disabled:opacity-70"
                            style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #0ea5e9 100%);">
                            <span class="pointer-events-none absolute inset-0 -translate-x-full skew-x-[-20deg] bg-white/10 transition-transform duration-500 group-hover:translate-x-[200%]"></span>
                            <span class="relative flex items-center gap-2">
                                <Spinner v-if="processing"/>
                                {{ processing ? 'Memproses...' : 'Masuk ke Sistem' }}
                                <svg v-if="!processing" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </span>
                        </button>
                    </Form>

                    <!-- Footer -->
                    <p class="mt-5 text-center text-[11px] text-slate-300 dark:text-slate-600">
                        &copy; {{ new Date().getFullYear() }} <span class="font-medium text-slate-400 dark:text-slate-400">ukfia</span> &mdash; Datin RSUD Tarakan
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
