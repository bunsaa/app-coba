<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];
            foreach ($months as $month) {
                $table->integer("{$month}_n_prev")->nullable();
                $table->integer("{$month}_d_prev")->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];
            foreach ($months as $month) {
                $table->dropColumn(["{$month}_n_prev", "{$month}_d_prev"]);
            }
        });
    }
};
