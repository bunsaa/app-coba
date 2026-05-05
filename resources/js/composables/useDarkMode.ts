import { onMounted, ref, watch } from 'vue';

// Check if dark mode is enabled from localStorage or system preference
const checkDarkMode = (): boolean => {
    const stored = localStorage.getItem('darkMode');
    if (stored !== null) {
        return stored === 'true';
    }
    // Check system preference
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

// Apply dark mode class to html element
const applyDarkMode = (isDark: boolean) => {
    if (isDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

// Initialize dark mode on page load (call this early)
export function initializeDarkMode() {
    const isDark = checkDarkMode();
    applyDarkMode(isDark);
}

export function useDarkMode() {
    const isDark = ref(checkDarkMode());

    // Toggle dark mode
    const toggleDarkMode = () => {
        isDark.value = !isDark.value;
    };

    // Watch for changes and save to localStorage
    watch(isDark, (newValue) => {
        localStorage.setItem('darkMode', String(newValue));
        applyDarkMode(newValue);
    });

    // Initialize on mount
    onMounted(() => {
        isDark.value = checkDarkMode();
        applyDarkMode(isDark.value);
    });

    return {
        isDark,
        toggleDarkMode,
    };
}
