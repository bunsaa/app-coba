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
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];
            foreach ($months as $month) {
                $table->text($month . '_rekomendasi')->nullable()->after($month . '_rtl');
            }
        });
    }

    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];
            foreach ($months as $month) {
                $table->dropColumn($month . '_rekomendasi');
            }
        });
    }
};
