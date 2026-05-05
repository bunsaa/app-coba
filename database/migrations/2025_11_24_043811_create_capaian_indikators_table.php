<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capaian_indikators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_id')->constrained('indikators')->onDelete('cascade');
            $table->string('kode_unit'); // Foreign key ke units
            $table->integer('tahun'); // Tahun capaian (2024, 2025, dst)
            
            // Data bulanan (N & D)
            $table->integer('jan_n')->nullable();
            $table->integer('jan_d')->nullable();
            $table->integer('feb_n')->nullable();
            $table->integer('feb_d')->nullable();
            $table->integer('mar_n')->nullable();
            $table->integer('mar_d')->nullable();
            $table->integer('apr_n')->nullable();
            $table->integer('apr_d')->nullable();
            $table->integer('may_n')->nullable();
            $table->integer('may_d')->nullable();
            $table->integer('jun_n')->nullable();
            $table->integer('jun_d')->nullable();
            $table->integer('jul_n')->nullable();
            $table->integer('jul_d')->nullable();
            $table->integer('aug_n')->nullable();
            $table->integer('aug_d')->nullable();
            $table->integer('sep_n')->nullable();
            $table->integer('sep_d')->nullable();
            $table->integer('oct_n')->nullable();
            $table->integer('oct_d')->nullable();
            $table->integer('nov_n')->nullable();
            $table->integer('nov_d')->nullable();
            $table->integer('des_n')->nullable();
            $table->integer('des_d')->nullable();
            
            // Status validasi per bulan
            $table->boolean('jan_validated')->default(false);
            $table->boolean('feb_validated')->default(false);
            $table->boolean('mar_validated')->default(false);
            $table->boolean('apr_validated')->default(false);
            $table->boolean('may_validated')->default(false);
            $table->boolean('jun_validated')->default(false);
            $table->boolean('jul_validated')->default(false);
            $table->boolean('aug_validated')->default(false);
            $table->boolean('sep_validated')->default(false);
            $table->boolean('oct_validated')->default(false);
            $table->boolean('nov_validated')->default(false);
            $table->boolean('des_validated')->default(false);
            
            // Analisis & RTL per Triwulan
            $table->text('analisis_q1')->nullable();
            $table->text('rtl_q1')->nullable();
            $table->text('analisis_q2')->nullable();
            $table->text('rtl_q2')->nullable();
            $table->text('analisis_q3')->nullable();
            $table->text('rtl_q3')->nullable();
            $table->text('analisis_q4')->nullable();
            $table->text('rtl_q4')->nullable();
            
            $table->timestamps();
            
            // Unique constraint: satu indikator per unit per tahun
            $table->unique(['indikator_id', 'kode_unit', 'tahun']);
            
            // Foreign key
            $table->foreign('kode_unit')->references('kode_unit')->on('units')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capaian_indikators');
    }
};