<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    // Task 2: Truncate capaian & penilaian
    $c = DB::table('capaian_indikators')->count();
    $p = DB::table('penilaian_pj_data')->count();
    DB::table('capaian_indikators')->truncate();
    DB::table('penilaian_pj_data')->truncate();
    echo "OK - Hapus {$c} capaian_indikators, {$p} penilaian_pj_data\n";

    // Task 3: Hapus indikator pendaftaran online di unit Datin
    $deleted = DB::table('indikators')
        ->where('kode_unit', 'Datin')
        ->where('indikator', 'like', '%pendaftaran%online%')
        ->delete();
    echo "OK - Hapus {$deleted} indikator pendaftaran online di unit Datin\n";

    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "SELESAI\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
