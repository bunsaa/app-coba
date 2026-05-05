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
                $table->decimal($month . '_rejected_n', 15, 2)->nullable()->after($month . '_reject_reason');
                $table->decimal($month . '_rejected_d', 15, 2)->nullable()->after($month . '_rejected_n');
                $table->timestamp($month . '_rejected_at')->nullable()->after($month . '_rejected_d');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];

            foreach ($months as $month) {
                $table->dropColumn($month . '_rejected_n');
                $table->dropColumn($month . '_rejected_d');
                $table->dropColumn($month . '_rejected_at');
            }
        });
    }
};
