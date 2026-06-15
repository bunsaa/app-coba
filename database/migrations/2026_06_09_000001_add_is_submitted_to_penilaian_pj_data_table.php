<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penilaian_pj_data', function (Blueprint $table) {
            $table->boolean('is_submitted')->default(false)->after('penilai_id');
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_pj_data', function (Blueprint $table) {
            $table->dropColumn('is_submitted');
        });
    }
};
