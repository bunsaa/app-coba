<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_indikators', function (Blueprint $table) {
            // These old columns are no longer used in the form but still exist in the table
            // Make them nullable so INSERT doesn't fail without them
            // Guard with hasColumn in case the create migration already omits them
            if (Schema::hasColumn('pengajuan_indikators', 'target_pencapaian'))
                $table->string('target_pencapaian')->nullable()->default(null)->change();
            if (Schema::hasColumn('pengajuan_indikators', 'formula'))
                $table->text('formula')->nullable()->default(null)->change();
            if (Schema::hasColumn('pengajuan_indikators', 'periode_pengumpulan_data'))
                $table->string('periode_pengumpulan_data')->nullable()->default(null)->change();
            if (Schema::hasColumn('pengajuan_indikators', 'periode_analisis_pelaporan'))
                $table->string('periode_analisis_pelaporan')->nullable()->default(null)->change();
            if (Schema::hasColumn('pengajuan_indikators', 'penyajian_data'))
                $table->string('penyajian_data')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // No rollback needed for nullable changes
    }
};
