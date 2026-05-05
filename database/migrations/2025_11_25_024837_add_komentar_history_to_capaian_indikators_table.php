<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            // JSON column untuk menyimpan history komentar
            if (!Schema::hasColumn('capaian_indikators', 'komentar_history')) {
                $table->json('komentar_history')->nullable()->after('komentar_dibaca');
            }
        });
    }

    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            if (Schema::hasColumn('capaian_indikators', 'komentar_history')) {
                $table->dropColumn('komentar_history');
            }
        });
    }
};
