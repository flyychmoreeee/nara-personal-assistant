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
        Schema::table('kas_weeks', function (Blueprint $table) {
            $table->enum('type', ['routine', 'incidental'])->default('routine')->after('title');
            $table->text('description')->nullable()->after('type');
            $table->unsignedTinyInteger('week')->nullable()->change();
            $table->unsignedTinyInteger('month')->nullable()->change();
            $table->date('due_date')->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kas_weeks', function (Blueprint $table) {
            $table->dropColumn(['type', 'description', 'due_date']);
            $table->unsignedTinyInteger('week')->nullable(false)->change();
            $table->unsignedTinyInteger('month')->nullable(false)->change();
        });
    }
};
