<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penilaian_pj_data', function (Blueprint $table) {
            $table->string('nama_tim', 100)->nullable()->after('kode_unit');
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_pj_data', function (Blueprint $table) {
            $table->dropColumn('nama_tim');
        });
    }
};
