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
        Schema::table('indikators', function (Blueprint $table) {
            // Kolom satuan: rata-rata, persen, permil
            $table->enum('satuan', ['rata-rata', 'persen', 'permil'])->default('persen')->after('standar');
            // Kolom satuan_waktu: hanya untuk satuan rata-rata (hari, jam, menit)
            $table->enum('satuan_waktu', ['hari', 'jam', 'menit'])->nullable()->after('satuan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indikators', function (Blueprint $table) {
            $table->dropColumn(['satuan', 'satuan_waktu']);
        });
    }
};
