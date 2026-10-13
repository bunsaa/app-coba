<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_indikators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('kode_unit');
            $table->string('nama_tim')->nullable();

            // Form fields
            $table->string('judul_indikator');
            $table->text('dasar_pemikiran');
            $table->string('dimensi_mutu');
            $table->text('tujuan');
            $table->text('definisi_operasional');
            $table->string('jenis_indikator');
            $table->text('numerator')->nullable();
            $table->text('denominator')->nullable();
            $table->string('target_tw1')->nullable();
            $table->string('target_tw2')->nullable();
            $table->string('target_tw3')->nullable();
            $table->string('target_tw4')->nullable();
            $table->string('satuan')->nullable();
            $table->string('satuan_waktu')->nullable();
            $table->text('kriteria_inklusi_eksklusi')->nullable();
            $table->string('metode_pengumpulan_data');
            $table->string('sumber_data');
            $table->string('instrumen_pengambilan_data');
            $table->text('populasi_sampel')->nullable();
            $table->string('penanggung_jawab');

            // Approval flow
            $table->string('status')->default('pending_kepala');
            $table->text('catatan_kepala')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->string('reviewed_by_kepala')->nullable();
            $table->string('reviewed_by_admin')->nullable();
            $table->timestamp('reviewed_at_kepala')->nullable();
            $table->timestamp('reviewed_at_admin')->nullable();

            // Link to created indikator (after admin approve)
            $table->unsignedBigInteger('indikator_id')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_indikators');
    }
};
