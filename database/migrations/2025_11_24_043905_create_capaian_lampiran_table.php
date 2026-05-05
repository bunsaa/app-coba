<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capaian_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capaian_id')->constrained('capaian_indikators')->onDelete('cascade');
            $table->string('bulan'); // jan, feb, mar, dst
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capaian_lampiran');
    }
};