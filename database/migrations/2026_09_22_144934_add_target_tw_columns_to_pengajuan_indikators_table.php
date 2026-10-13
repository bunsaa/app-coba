<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_indikators', function (Blueprint $table) {
            $table->string('target_tw1', 100)->nullable()->after('denominator');
            $table->string('target_tw2', 100)->nullable()->after('target_tw1');
            $table->string('target_tw3', 100)->nullable()->after('target_tw2');
            $table->string('target_tw4', 100)->nullable()->after('target_tw3');
            $table->string('satuan', 100)->nullable()->after('target_tw4');
            $table->string('satuan_waktu', 50)->nullable()->after('satuan');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_indikators', function (Blueprint $table) {
            $table->dropColumn(['target_tw1', 'target_tw2', 'target_tw3', 'target_tw4', 'satuan', 'satuan_waktu']);
        });
    }
};
