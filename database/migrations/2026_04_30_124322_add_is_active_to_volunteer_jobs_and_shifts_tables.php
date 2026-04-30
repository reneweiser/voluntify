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
        Schema::table('volunteer_jobs', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('instructions');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('volunteer_jobs', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
