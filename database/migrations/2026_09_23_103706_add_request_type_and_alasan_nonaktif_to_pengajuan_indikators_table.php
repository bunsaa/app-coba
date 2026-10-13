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
            $table->string('request_type', 20)->nullable()->after('original_indikator_id'); // 'revisi' | 'nonaktif'
            $table->text('alasan_nonaktif')->nullable()->after('request_type');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_indikators', function (Blueprint $table) {
            $table->dropColumn(['request_type', 'alasan_nonaktif']);
        });
    }
};
