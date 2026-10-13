<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_indikators', function (Blueprint $table) {
            $table->tinyInteger('skor_a')->nullable()->after('penanggung_jawab'); // High Risk Process
            $table->tinyInteger('skor_b')->nullable()->after('skor_a');           // High Volume
            $table->tinyInteger('skor_c')->nullable()->after('skor_b');           // High Cost
            $table->tinyInteger('skor_d')->nullable()->after('skor_c');           // Ketidakpuasan pasien & staf
            $table->tinyInteger('skor_e')->nullable()->after('skor_d');           // Sesuai tujuan strategis RS
            $table->tinyInteger('total_skor')->nullable()->after('skor_e');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_indikators', function (Blueprint $table) {
            $table->dropColumn(['skor_a', 'skor_b', 'skor_c', 'skor_d', 'skor_e', 'total_skor']);
        });
    }
};
