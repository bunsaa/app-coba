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
            // Tambah kolom untuk menyimpan multi-unit (JSON format)
            $table->json('pic_units')->nullable()->after('pic');
            // Ubah kode_unit dan tim_unit jadi nullable karena sekarang indikator tidak terikat ke satu unit saja
            $table->string('kode_unit')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indikators', function (Blueprint $table) {
            $table->dropColumn('pic_units');
            $table->string('kode_unit')->nullable(false)->change();
        });
    }
};
