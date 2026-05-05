<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('indikators', function (Blueprint $table) {
            $table->id();
            $table->string('kode_unit'); // Foreign key ke units
            $table->string('tim_unit')->nullable(); // Nama tim unit (nullable jika unit tidak punya tim)
            $table->text('indikator'); // Deskripsi indikator
            $table->string('standar'); // Standar (misal: ≥ 90%)
            $table->string('pic'); // PIC (otomatis terisi dari tim_unit atau nama_unit)
            $table->text('numerator')->nullable(); // Pembilang
            $table->text('denominator')->nullable(); // Penyebut
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('kode_unit')->references('kode_unit')->on('units')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indikators');
    }
};