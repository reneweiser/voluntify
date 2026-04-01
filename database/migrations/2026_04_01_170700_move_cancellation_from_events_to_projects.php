<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copy cancellation settings from events to their projects
        $events = DB::table('events')
            ->whereNotNull('cancellation_cutoff_hours')
            ->get(['id', 'project_id', 'cancellation_cutoff_hours']);

        foreach ($events->groupBy('project_id') as $projectId => $projectEvents) {
            $maxCutoff = $projectEvents->max('cancellation_cutoff_hours');
            DB::table('projects')
                ->where('id', $projectId)
                ->where('cancellation_enabled', false)
                ->update([
                    'cancellation_enabled' => true,
                    'cancellation_cutoff_hours' => $maxCutoff,
                ]);
        }

        // Drop the event column
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('cancellation_cutoff_hours');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('cancellation_cutoff_hours')->nullable()->after('status');
        });
    }
};
