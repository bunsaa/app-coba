<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$data = DB::table('penilaian_pj_data')
    ->where('kode_unit', 'Datin')
    ->whereIn('bulan', [7, 8])
    ->where('tahun', 2026)
    ->get();

foreach ($data as $d) {
    echo $d->nama_tim . ' | Bulan ' . $d->bulan . ' | Nilai=' . $d->nilai . ' | submitted=' . $d->is_submitted . PHP_EOL;
}
echo 'Total: ' . count($data) . ' records' . PHP_EOL;
