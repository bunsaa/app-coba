<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalkan semua varian non-ASN lama ke "Pegawai BLUD"
        DB::table('users')
            ->whereIn('status_pegawai', [
                'Pegawai Blud (Tetap Non ASN)',
                'Pegawai Lainnya Non ASN',
                'PJLP',
                'Mitra',
            ])
            ->update(['status_pegawai' => 'Pegawai BLUD']);
    }

    public function down(): void
    {
        // Tidak dapat dikembalikan karena informasi tipe aslinya sudah hilang
    }
};
