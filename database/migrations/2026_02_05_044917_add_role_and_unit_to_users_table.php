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
        Schema::table('users', function (Blueprint $table) {
            // Role: admin_mutu, kepala_unit, staf
            $table->string('role')->default('staf')->after('email');
            // Kode unit untuk kepala_unit dan staf
            $table->string('kode_unit')->nullable()->after('role');

            // Foreign key ke tabel units
            $table->foreign('kode_unit')->references('kode_unit')->on('units')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['kode_unit']);
            $table->dropColumn(['role', 'kode_unit']);
        });
    }
};
