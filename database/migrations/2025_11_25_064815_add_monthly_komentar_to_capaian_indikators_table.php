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
            // Add monthly comment fields
            $table->text('jan_komentar')->nullable()->after('jan_validated');
            $table->boolean('jan_komentar_dibaca')->default(false)->after('jan_komentar');
            $table->text('feb_komentar')->nullable()->after('feb_validated');
            $table->boolean('feb_komentar_dibaca')->default(false)->after('feb_komentar');
            $table->text('mar_komentar')->nullable()->after('mar_validated');
            $table->boolean('mar_komentar_dibaca')->default(false)->after('mar_komentar');
            $table->text('apr_komentar')->nullable()->after('apr_validated');
            $table->boolean('apr_komentar_dibaca')->default(false)->after('apr_komentar');
            $table->text('may_komentar')->nullable()->after('may_validated');
            $table->boolean('may_komentar_dibaca')->default(false)->after('may_komentar');
            $table->text('jun_komentar')->nullable()->after('jun_validated');
            $table->boolean('jun_komentar_dibaca')->default(false)->after('jun_komentar');
            $table->text('jul_komentar')->nullable()->after('jul_validated');
            $table->boolean('jul_komentar_dibaca')->default(false)->after('jul_komentar');
            $table->text('aug_komentar')->nullable()->after('aug_validated');
            $table->boolean('aug_komentar_dibaca')->default(false)->after('aug_komentar');
            $table->text('sep_komentar')->nullable()->after('sep_validated');
            $table->boolean('sep_komentar_dibaca')->default(false)->after('sep_komentar');
            $table->text('oct_komentar')->nullable()->after('oct_validated');
            $table->boolean('oct_komentar_dibaca')->default(false)->after('oct_komentar');
            $table->text('nov_komentar')->nullable()->after('nov_validated');
            $table->boolean('nov_komentar_dibaca')->default(false)->after('nov_komentar');
            $table->text('des_komentar')->nullable()->after('des_validated');
            $table->boolean('des_komentar_dibaca')->default(false)->after('des_komentar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $table->dropColumn([
                'jan_komentar', 'jan_komentar_dibaca',
                'feb_komentar', 'feb_komentar_dibaca',
                'mar_komentar', 'mar_komentar_dibaca',
                'apr_komentar', 'apr_komentar_dibaca',
                'may_komentar', 'may_komentar_dibaca',
                'jun_komentar', 'jun_komentar_dibaca',
                'jul_komentar', 'jul_komentar_dibaca',
                'aug_komentar', 'aug_komentar_dibaca',
                'sep_komentar', 'sep_komentar_dibaca',
                'oct_komentar', 'oct_komentar_dibaca',
                'nov_komentar', 'nov_komentar_dibaca',
                'des_komentar', 'des_komentar_dibaca',
            ]);
        });
    }
};
