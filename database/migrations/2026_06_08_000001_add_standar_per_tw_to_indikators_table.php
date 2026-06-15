<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indikators', function (Blueprint $table) {
            $table->string('standar_tw1')->nullable()->after('standar');
            $table->string('satuan_tw1')->nullable()->after('standar_tw1');
            $table->string('satuan_waktu_tw1')->nullable()->after('satuan_tw1');
            $table->string('standar_tw2')->nullable()->after('satuan_waktu_tw1');
            $table->string('satuan_tw2')->nullable()->after('standar_tw2');
            $table->string('satuan_waktu_tw2')->nullable()->after('satuan_tw2');
            $table->string('standar_tw3')->nullable()->after('satuan_waktu_tw2');
            $table->string('satuan_tw3')->nullable()->after('standar_tw3');
            $table->string('satuan_waktu_tw3')->nullable()->after('satuan_tw3');
            $table->string('standar_tw4')->nullable()->after('satuan_waktu_tw3');
            $table->string('satuan_tw4')->nullable()->after('standar_tw4');
            $table->string('satuan_waktu_tw4')->nullable()->after('satuan_tw4');
        });
    }

    public function down(): void
    {
        Schema::table('indikators', function (Blueprint $table) {
            $table->dropColumn([
                'standar_tw1','satuan_tw1','satuan_waktu_tw1',
                'standar_tw2','satuan_tw2','satuan_waktu_tw2',
                'standar_tw3','satuan_tw3','satuan_waktu_tw3',
                'standar_tw4','satuan_tw4','satuan_waktu_tw4',
            ]);
        });
    }
};
