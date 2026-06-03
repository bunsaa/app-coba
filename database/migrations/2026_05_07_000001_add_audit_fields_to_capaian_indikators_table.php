<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            // Kolom nama indikator (redundant copy untuk kemudahan audit/export)
            $table->string('nama_indikator')->nullable()->after('indikator_id');

            // Kolom audit: siapa yang mengisi & update – ditempatkan setelah des_d
            $table->string('created_by')->nullable()->after('des_d');
            $table->string('updated_by')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $table->dropColumn(['nama_indikator', 'created_by', 'updated_by']);
        });
    }
};
