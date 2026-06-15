<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penilaian_pj_data', function (Blueprint $table) {
            // Drop the old unique index that didn't include nama_tim,
            // which prevented saving penilaian for multiple tims in the same unit/month.
            $table->dropUnique('penilaian_pj_data_kode_unit_tahun_bulan_unique');
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_pj_data', function (Blueprint $table) {
            $table->unique(['kode_unit', 'tahun', 'bulan'], 'penilaian_pj_data_kode_unit_tahun_bulan_unique');
        });
    }
};
