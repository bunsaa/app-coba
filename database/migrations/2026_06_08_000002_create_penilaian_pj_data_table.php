<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penilaian_pj_data', function (Blueprint $table) {
            $table->id();
            $table->string('kode_unit');
            $table->integer('tahun');
            $table->integer('bulan');
            $table->enum('nilai', ['Sangat Baik', 'Baik', 'Cukup', 'Kurang']);
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('penilai_id')->nullable();
            $table->timestamps();
            $table->unique(['kode_unit', 'tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian_pj_data');
    }
};
