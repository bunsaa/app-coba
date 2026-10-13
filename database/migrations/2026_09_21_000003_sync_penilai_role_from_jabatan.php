<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sync: staf yg sudah punya jabatan koordinator → role jadi penilai_pj_data
        DB::table('users')
            ->where('role', 'staf')
            ->whereNotNull('jabatan')
            ->where('jabatan', '!=', '')
            ->update(['role' => 'penilai_pj_data']);
    }

    public function down(): void
    {
        // Tidak dapat dikembalikan karena role asal sudah tidak bisa dibedakan
    }
};
