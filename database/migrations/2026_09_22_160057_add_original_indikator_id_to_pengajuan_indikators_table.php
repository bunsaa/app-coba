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
        Schema::table('pengajuan_indikators', function (Blueprint $table) {
            $table->unsignedBigInteger('original_indikator_id')->nullable()->after('indikator_id');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_indikators', function (Blueprint $table) {
            $table->dropColumn('original_indikator_id');
        });
    }
};
