import '../css/app.css';

import axios from 'axios';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { initializeDarkMode } from './composables/useDarkMode';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Configure axios CSRF - interceptor agar token selalu fresh setiap request
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = false;
axios.interceptors.request.use((config) => {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
});

// Handle 419 (session expired) dari axios requests
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            alert('Sesi Anda telah berakhir. Halaman akan dimuat ulang untuk login kembali.');
            window.location.reload();
        }
        return Promise.reject(error);
    }
);

// Handle 419 (session expired) dari Inertia router requests
router.on('invalid', (event) => {
    if ((event.detail as any).response?.status === 419) {
        event.preventDefault();
        alert('Sesi Anda telah berakhir. Halaman akan dimuat ulang untuk login kembali.');
        window.location.reload();
    }
});

// Initialize dark mode ASAP to prevent flash of wrong theme
initializeDarkMode();

createInertiaApp({
    title: () => appName,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
