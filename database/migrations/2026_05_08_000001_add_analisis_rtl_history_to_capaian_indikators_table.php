<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            // Kolom JSON untuk menyimpan history perubahan analisis & RTL per bulan.
            // Struktur: { "jan": [{ analisis, rtl, changed_at, changed_by, changed_by_role }], ... }
            $table->json('analisis_rtl_history')->nullable()->after('des_rtl');
        });
    }

    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $table->dropColumn('analisis_rtl_history');
        });
    }
};
