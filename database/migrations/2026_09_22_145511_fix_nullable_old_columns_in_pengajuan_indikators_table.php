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
            $table->string('target_pencapaian')->nullable()->default(null)->change();
            $table->text('formula')->nullable()->default(null)->change();
            $table->string('periode_pengumpulan_data')->nullable()->default(null)->change();
            $table->string('periode_analisis_pelaporan')->nullable()->default(null)->change();
            $table->string('penyajian_data')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // No rollback needed for nullable changes
    }
};
