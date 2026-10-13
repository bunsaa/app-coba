<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validasi_data_indikators', function (Blueprint $table) {
            $table->id();
            $table->string('kode_unit');
            $table->string('nama_tim')->nullable();
            $table->integer('tahun');
            $table->tinyInteger('triwulan'); // 1-4

            // Detail per-indikator (JSON array)
            // [{indikator_id, nama_indikator, pj1_jawaban (D/N/null), pj2_jawaban (D/N/null), nilai (1/0/null)}]
            $table->json('detail')->nullable();

            // Hasil kalkulasi
            $table->integer('jumlah_kesesuaian')->nullable();
            $table->decimal('persentase_kesesuaian', 5, 2)->nullable();
            $table->boolean('is_valid')->nullable();

            // Diset admin: apakah validasi diwajibkan untuk TW ini
            $table->boolean('is_required')->default(false);

            // PJ Data yang mengisi
            $table->unsignedBigInteger('pj_data_1_id')->nullable();
            $table->unsignedBigInteger('pj_data_2_id')->nullable();
            $table->timestamp('pj1_submitted_at')->nullable();
            $table->timestamp('pj2_submitted_at')->nullable();
            $table->boolean('is_submitted')->default(false);

            $table->timestamps();

            $table->unique(['kode_unit', 'nama_tim', 'tahun', 'triwulan'], 'validasi_unique');

            $table->foreign('pj_data_1_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('pj_data_2_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validasi_data_indikators');
    }
};
