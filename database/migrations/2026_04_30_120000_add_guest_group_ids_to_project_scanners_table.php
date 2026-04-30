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
        Schema::table('project_scanners', function (Blueprint $table) {
            $table->json('guest_group_ids')->nullable()->after('pool_event_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_scanners', function (Blueprint $table) {
            $table->dropColumn('guest_group_ids');
        });
    }
};
