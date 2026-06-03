import { ref, onMounted, onUnmounted } from 'vue'

const IDLE_TIMEOUT = 60 * 60 * 1000       // 60 menit dalam ms
const WARNING_BEFORE = 5 * 60 * 1000      // Tampilkan peringatan 5 menit sebelum logout
const CHECK_INTERVAL = 10 * 1000          // Cek setiap 10 detik

/**
 * Composable untuk mendeteksi idle dan auto-logout.
 *
 * @param onLogout - Fungsi async yang dipanggil saat timeout. Harus melakukan auto-save + redirect.
 */
export function useIdleTimer(onLogout: () => Promise<void>) {
    const isWarningVisible = ref(false)
    const secondsRemaining = ref(Math.floor(WARNING_BEFORE / 1000))

    let lastActivityTime = Date.now()
    let checkIntervalId: ReturnType<typeof setInterval> | null = null
    let countdownIntervalId: ReturnType<typeof setInterval> | null = null
    let isLoggingOut = false

    const stopCountdown = () => {
        if (countdownIntervalId !== null) {
            clearInterval(countdownIntervalId)
            countdownIntervalId = null
        }
    }

    const startCountdown = () => {
        if (isWarningVisible.value) return // Sudah berjalan
        isWarningVisible.value = true
        secondsRemaining.value = Math.floor(WARNING_BEFORE / 1000)

        countdownIntervalId = setInterval(() => {
            secondsRemaining.value--
            if (secondsRemaining.value <= 0) {
                stopCountdown()
                triggerLogout()
            }
        }, 1000)
    }

    const triggerLogout = () => {
        if (isLoggingOut) return
        isLoggingOut = true
        stopCountdown()
        onLogout().catch((err) => {
            console.error('Auto-logout error:', err)
        })
    }

    /**
     * Reset timer - dipanggil saat user masih aktif atau klik "Perpanjang Sesi".
     */
    const resetActivity = () => {
        if (isLoggingOut) return
        lastActivityTime = Date.now()
        isWarningVisible.value = false
        secondsRemaining.value = Math.floor(WARNING_BEFORE / 1000)
        stopCountdown()
    }

    const checkIdle = () => {
        if (isLoggingOut) return
        const idleMs = Date.now() - lastActivityTime

        if (idleMs >= IDLE_TIMEOUT) {
            triggerLogout()
        } else if (idleMs >= IDLE_TIMEOUT - WARNING_BEFORE && !isWarningVisible.value) {
            startCountdown()
        }
    }

    const activityEvents: (keyof WindowEventMap)[] = [
        'mousemove',
        'keydown',
        'click',
        'scroll',
        'touchstart',
    ]

    onMounted(() => {
        activityEvents.forEach((evt) => {
            window.addEventListener(evt, resetActivity, { passive: true })
        })
        checkIntervalId = setInterval(checkIdle, CHECK_INTERVAL)
    })

    onUnmounted(() => {
        activityEvents.forEach((evt) => {
            window.removeEventListener(evt, resetActivity)
        })
        if (checkIntervalId !== null) clearInterval(checkIntervalId)
        stopCountdown()
    })

    return { isWarningVisible, secondsRemaining, resetActivity }
}
