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
            // Approval fields per month (approved by kepala unit before validation)
            $table->boolean('jan_approved')->default(false)->after('des_validated');
            $table->boolean('feb_approved')->default(false)->after('jan_approved');
            $table->boolean('mar_approved')->default(false)->after('feb_approved');
            $table->boolean('apr_approved')->default(false)->after('mar_approved');
            $table->boolean('may_approved')->default(false)->after('apr_approved');
            $table->boolean('jun_approved')->default(false)->after('may_approved');
            $table->boolean('jul_approved')->default(false)->after('jun_approved');
            $table->boolean('aug_approved')->default(false)->after('jul_approved');
            $table->boolean('sep_approved')->default(false)->after('aug_approved');
            $table->boolean('oct_approved')->default(false)->after('sep_approved');
            $table->boolean('nov_approved')->default(false)->after('oct_approved');
            $table->boolean('des_approved')->default(false)->after('nov_approved');

            // Track who approved
            $table->unsignedBigInteger('approved_by')->nullable()->after('des_approved');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $table->dropColumn([
                'jan_approved', 'feb_approved', 'mar_approved',
                'apr_approved', 'may_approved', 'jun_approved',
                'jul_approved', 'aug_approved', 'sep_approved',
                'oct_approved', 'nov_approved', 'des_approved',
                'approved_by', 'approved_at'
            ]);
        });
    }
};
