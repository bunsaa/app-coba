<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\User;
use App\Http\Controllers\PenilaianPerilakuController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-sync penilaian_aktif semua kepala_unit setiap hari tengah malam
// Aktif: tgl 15 s/d akhir bulan + tgl 1–5; Nonaktif: tgl 6–14
Schedule::call(function () {
    $shouldBeAktif = PenilaianPerilakuController::isPenilaianPeriod();
    User::where('role', 'kepala_unit')->update(['penilaian_aktif' => $shouldBeAktif]);
})->dailyAt('00:01')->name('penilaian-auto-sync')->withoutOverlapping();
