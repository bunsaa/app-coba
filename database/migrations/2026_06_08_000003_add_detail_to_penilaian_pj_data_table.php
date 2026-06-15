<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop enum constraint on 'nilai' and change to nullable varchar
        DB::statement("ALTER TABLE penilaian_pj_data MODIFY COLUMN nilai VARCHAR(50) NULL");

        Schema::table('penilaian_pj_data', function (Blueprint $table) {
            $table->json('aspek')->nullable()->after('catatan');
            $table->string('temuan_kesesuaian', 50)->nullable()->after('aspek');
            $table->string('temuan_kelengkapan', 50)->nullable()->after('temuan_kesesuaian');
            $table->string('temuan_ketepatan', 50)->nullable()->after('temuan_kelengkapan');
            $table->string('temuan_validitas', 50)->nullable()->after('temuan_ketepatan');
            $table->json('rekomendasi')->nullable()->after('temuan_validitas');
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_pj_data', function (Blueprint $table) {
            $table->dropColumn(['aspek', 'temuan_kesesuaian', 'temuan_kelengkapan', 'temuan_ketepatan', 'temuan_validitas', 'rekomendasi']);
        });

        DB::statement("ALTER TABLE penilaian_pj_data MODIFY COLUMN nilai ENUM('Sangat Baik','Baik','Cukup','Kurang') NOT NULL");
    }
};
