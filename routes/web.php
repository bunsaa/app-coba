<?php

use App\Http\Controllers\CapaianIndikatorController;
use App\Http\Controllers\IndikatorsController;
use App\Http\Controllers\UnitsController;
use App\Http\Controllers\ValidasiCapaianIndikatorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PenilaianPerilakuController;
use App\Http\Controllers\SetModeController;
use App\Http\Controllers\RekomendasiAIController;
use App\Http\Controllers\PenilaianPjDataController;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Captcha (public, no auth needed)
Route::get('/captcha', [CaptchaController::class, 'generate']);

// Reset password to default (public, no auth needed)
Route::post('/reset-password-default', [PasswordResetController::class, 'resetToDefault']);

// Force change password (auth needed, before entering app)
Route::middleware(['auth'])->group(function () {
    Route::get('/force-change-password', [PasswordResetController::class, 'showForceChange']);
    Route::post('/force-change-password', [PasswordResetController::class, 'processForceChange']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Set mode akses (mutu / perilaku) - dipanggil dari modal
    Route::post('/set-mode', [SetModeController::class, 'store'])->name('set-mode');

    // Reset mode akses - kembali ke pilihan modal
    Route::post('/ganti-mode', function () {
        session()->forget('mode_akses');
        return response()->json(['success' => true]);
    })->name('ganti-mode');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Rekomendasi AI
    Route::post('/rekomendasi-ai', [RekomendasiAIController::class, 'generate'])->name('rekomendasi-ai');
    
    // Indikator Routes
    Route::get('/indikator', [IndikatorsController::class, 'index'])->name('indikator.index');
    Route::post('/indikator', [IndikatorsController::class, 'store'])->name('indikator.store');
    Route::post('/indikator/get-by-unit', [IndikatorsController::class, 'getByUnit'])->name('indikator.getByUnit');
    Route::post('/indikator/get-all', [IndikatorsController::class, 'getAllIndikators'])->name('indikator.getAll');
    Route::put('/indikator/{id}', [IndikatorsController::class, 'update'])->name('indikator.update');
    Route::post('/indikator/toggle-active/{id}', [IndikatorsController::class, 'toggleActive']);
    Route::post('/indikator/toggle-prioritas', [IndikatorsController::class, 'togglePrioritas']);
    Route::post('/indikator/rekap-update', [IndikatorsController::class, 'rekapUpdate']);
    Route::post('/indikator/toggle-active-group', [IndikatorsController::class, 'toggleActiveGroup']);
    Route::delete('/indikator/{id}', [IndikatorsController::class, 'destroy'])->name('indikator.destroy');

    // Verifikasi Capaian Indikator Routes
    Route::get('/verifikasi-capaian-indikator', [ValidasiCapaianIndikatorController::class, 'index'])->name('verifikasi-capaian-indikator.index');
    Route::post('/verifikasi-capaian-indikator/get-detail', [ValidasiCapaianIndikatorController::class, 'getDetailCapaian']);
    Route::post('/verifikasi-capaian-indikator/validate-single', [ValidasiCapaianIndikatorController::class, 'validateSingle']);
    Route::post('/verifikasi-capaian-indikator/validate-unit', [ValidasiCapaianIndikatorController::class, 'validateUnit']);
    Route::post('/verifikasi-capaian-indikator/validate-all', [ValidasiCapaianIndikatorController::class, 'validateAll']);
    Route::post('/verifikasi-capaian-indikator/reject-all', [ValidasiCapaianIndikatorController::class, 'rejectAll']);
    Route::post('/verifikasi-capaian-indikator/send-komentar', [ValidasiCapaianIndikatorController::class, 'sendKomentar']);
    Route::post('/verifikasi-capaian-indikator/clear-komentar', [ValidasiCapaianIndikatorController::class, 'clearKomentar']);
    Route::post('/verifikasi-capaian-indikator/save-analisis', [ValidasiCapaianIndikatorController::class, 'saveAnalisisAdmin']);
    Route::post('/verifikasi-capaian-indikator/generate-rekomendasi', [ValidasiCapaianIndikatorController::class, 'generateRekomendasi']);

    // Manajemen Pegawai Routes
    Route::get('/manajemen-pegawai', [PegawaiController::class, 'index'])->name('pegawai.index');
    Route::post('/manajemen-pegawai', [PegawaiController::class, 'store'])->name('pegawai.store');
    Route::put('/manajemen-pegawai/{id}', [PegawaiController::class, 'update'])->name('pegawai.update');
    Route::put('/manajemen-pegawai/{id}/pj-role', [PegawaiController::class, 'updatePjRole'])->name('pegawai.updatePjRole');
    Route::delete('/manajemen-pegawai/{id}', [PegawaiController::class, 'destroy'])->name('pegawai.destroy');

    // Penilaian Perilaku Routes
    Route::get('/penilaian-perilaku-home', [PenilaianPerilakuController::class, 'home'])->name('penilaian-perilaku.home');
    Route::get('/penilaian-perilaku-pegawai', [PenilaianPerilakuController::class, 'index'])->name('penilaian-perilaku.index');
    Route::post('/penilaian-perilaku-pegawai', [PenilaianPerilakuController::class, 'store'])->name('penilaian-perilaku.store');
    Route::put('/penilaian-perilaku-pegawai/{id}', [PenilaianPerilakuController::class, 'update'])->name('penilaian-perilaku.update');
    Route::post('/penilaian-perilaku-pegawai/show', [PenilaianPerilakuController::class, 'show']);
    Route::get('/penilaian-perilaku-saya', [PenilaianPerilakuController::class, 'indexSaya'])->name('penilaian-perilaku-saya.index');
    Route::get('/pengaturan-penilaian', [PenilaianPerilakuController::class, 'pengaturan'])->name('pengaturan-penilaian.index');
    Route::post('/pengaturan-penilaian/toggle/{id}', [PenilaianPerilakuController::class, 'togglePenilaian']);
    Route::post('/pengaturan-penilaian/toggle-all', [PenilaianPerilakuController::class, 'toggleAllPenilaian']);
    Route::get('/penilaian-perilaku-pegawai/export', [PenilaianPerilakuController::class, 'export'])->name('penilaian-perilaku.export');

    // Penilaian PJ Data Routes
    Route::post('/penilaian-pj-data/store', [PenilaianPjDataController::class, 'store'])->name('penilaian-pj-data.store');
    Route::post('/penilaian-pj-data/get', [PenilaianPjDataController::class, 'get'])->name('penilaian-pj-data.get');

    // Capaian Indikator Routes
    Route::get('/capaian-indikator', [CapaianIndikatorController::class, 'index'])->name('capaian-indikator.index');
    Route::post('/capaian-indikator/save', [CapaianIndikatorController::class, 'saveCapaian']);
    Route::post('/capaian-indikator/analisis', [CapaianIndikatorController::class, 'saveAnalisis']);
    Route::post('/capaian-indikator/upload', [CapaianIndikatorController::class, 'uploadLampiran']);
    Route::get('/capaian-indikator/lampiran/download/{filename}', [CapaianIndikatorController::class, 'downloadLampiran'])->name('capaian-indikator.lampiran.download');
    Route::get('/capaian-indikator/lampiran/{filename}', [CapaianIndikatorController::class, 'viewLampiran'])->name('capaian-indikator.lampiran.view');
    Route::post('/capaian-indikator/get-komentar', [CapaianIndikatorController::class, 'getKomentarForUnit']);
    Route::post('/capaian-indikator/mark-komentar-dibaca', [CapaianIndikatorController::class, 'markKomentarDibacaCapaian']);
    Route::post('/capaian-indikator/approve', [CapaianIndikatorController::class, 'approveCapaian']);
    Route::post('/capaian-indikator/approve-all', [CapaianIndikatorController::class, 'approveAllCapaian']);
    Route::post('/capaian-indikator/get-tim-indikator', [CapaianIndikatorController::class, 'getTimIndikatorForApproval']);
    Route::post('/capaian-indikator/get-indicator-detail', [CapaianIndikatorController::class, 'getIndicatorDetail']);
    Route::post('/capaian-indikator/approve-selected', [CapaianIndikatorController::class, 'approveSelectedIndicators']);
    Route::post('/capaian-indikator/reject-selected', [CapaianIndikatorController::class, 'rejectSelectedIndicators']);
    Route::post('/capaian-indikator/mark-revised', [CapaianIndikatorController::class, 'markRevised']);
});

require __DIR__.'/settings.php';