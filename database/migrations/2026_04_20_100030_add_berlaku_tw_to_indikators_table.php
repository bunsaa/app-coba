<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indikators', function (Blueprint $table) {
            // Array TW yang berlaku: [1,2,3,4] = semua, [1,2] = hanya TW1 & TW2, dst
            $table->json('berlaku_tw')->nullable()->after('is_active');
        });

        // Set default [1,2,3,4] untuk semua indikator yang sudah ada
        DB::statement("UPDATE indikators SET berlaku_tw = '[1,2,3,4]' WHERE berlaku_tw IS NULL");
    }

    public function down(): void
    {
        Schema::table('indikators', function (Blueprint $table) {
            $table->dropColumn('berlaku_tw');
        });
    }
};
