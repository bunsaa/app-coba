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
            // Rejected status per month (following existing naming: may, aug, oct)
            $table->boolean('jan_rejected')->default(false)->after('jan_approved');
            $table->boolean('feb_rejected')->default(false)->after('feb_approved');
            $table->boolean('mar_rejected')->default(false)->after('mar_approved');
            $table->boolean('apr_rejected')->default(false)->after('apr_approved');
            $table->boolean('may_rejected')->default(false)->after('may_approved');
            $table->boolean('jun_rejected')->default(false)->after('jun_approved');
            $table->boolean('jul_rejected')->default(false)->after('jul_approved');
            $table->boolean('aug_rejected')->default(false)->after('aug_approved');
            $table->boolean('sep_rejected')->default(false)->after('sep_approved');
            $table->boolean('oct_rejected')->default(false)->after('oct_approved');
            $table->boolean('nov_rejected')->default(false)->after('nov_approved');
            $table->boolean('des_rejected')->default(false)->after('des_approved');

            // Rejection reason per month
            $table->text('jan_reject_reason')->nullable()->after('jan_rejected');
            $table->text('feb_reject_reason')->nullable()->after('feb_rejected');
            $table->text('mar_reject_reason')->nullable()->after('mar_rejected');
            $table->text('apr_reject_reason')->nullable()->after('apr_rejected');
            $table->text('may_reject_reason')->nullable()->after('may_rejected');
            $table->text('jun_reject_reason')->nullable()->after('jun_rejected');
            $table->text('jul_reject_reason')->nullable()->after('jul_rejected');
            $table->text('aug_reject_reason')->nullable()->after('aug_rejected');
            $table->text('sep_reject_reason')->nullable()->after('sep_rejected');
            $table->text('oct_reject_reason')->nullable()->after('oct_rejected');
            $table->text('nov_reject_reason')->nullable()->after('nov_rejected');
            $table->text('des_reject_reason')->nullable()->after('des_rejected');
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
                $table->dropColumn("{$month}_rejected");
                $table->dropColumn("{$month}_reject_reason");
            }
        });
    }
};
