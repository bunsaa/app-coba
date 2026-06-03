// Module-level singleton - tidak menggunakan Vue reactivity
// agar callback tetap terdaftar saat komponen berganti

type AutoSaveCallback = () => Promise<void>

const callbacks: AutoSaveCallback[] = []

/**
 * Daftarkan fungsi auto-save untuk dipanggil sebelum auto-logout.
 * @returns Fungsi unregister - panggil saat komponen di-unmount
 */
export function registerAutoSave(fn: AutoSaveCallback): () => void {
    callbacks.push(fn)
    return () => {
        const idx = callbacks.indexOf(fn)
        if (idx !== -1) callbacks.splice(idx, 1)
    }
}

/**
 * Jalankan semua callback auto-save yang terdaftar.
 * Menggunakan Promise.allSettled agar tidak berhenti jika 1 callback gagal.
 */
export async function executeAllAutoSaves(): Promise<void> {
    if (callbacks.length === 0) return
    await Promise.allSettled(callbacks.map((fn) => fn()))
}
