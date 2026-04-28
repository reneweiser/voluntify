<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('is_project_wide')->default(false)->after('shift_id');
        });

        DB::table('announcements')
            ->whereNull('event_id')
            ->whereNull('job_id')
            ->whereNull('shift_id')
            ->update(['is_project_wide' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('is_project_wide');
        });
    }
};
