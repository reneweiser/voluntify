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
        Schema::table('project_gear_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity_per_volunteer')->nullable()->after('type');
            $table->json('job_ids')->nullable()->after('quantity_per_volunteer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_gear_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_per_volunteer', 'job_ids']);
        });
    }
};
