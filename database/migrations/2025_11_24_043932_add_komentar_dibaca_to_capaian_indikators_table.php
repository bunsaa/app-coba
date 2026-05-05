<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            // Cek dulu apakah kolom komentar ada
            if (!Schema::hasColumn('capaian_indikators', 'komentar')) {
                $table->text('komentar')->nullable()->after('rtl_q4');
            }
            
            // Tambah kolom komentar_dibaca
            if (!Schema::hasColumn('capaian_indikators', 'komentar_dibaca')) {
                $table->boolean('komentar_dibaca')->default(false)->after('komentar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            if (Schema::hasColumn('capaian_indikators', 'komentar_dibaca')) {
                $table->dropColumn('komentar_dibaca');
            }
        });
    }
};