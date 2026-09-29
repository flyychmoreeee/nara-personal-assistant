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
        Schema::create('kas_weeks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedTinyInteger('week');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('amount')->default(5000);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kas_weeks');
    }
};
