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
            $table->index('jenis_indikator', 'idx_indikators_jenis');
            $table->index(['jenis_indikator', 'is_active'], 'idx_indikators_jenis_active');
        });
    }

    public function down(): void
    {
        Schema::table('indikators', function (Blueprint $table) {
            $table->dropIndex('idx_indikators_jenis');
            $table->dropIndex('idx_indikators_jenis_active');
        });
    }
};
