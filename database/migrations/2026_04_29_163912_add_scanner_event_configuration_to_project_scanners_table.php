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
        Schema::table('project_scanners', function (Blueprint $table) {
            $table->foreignId('entry_event_id')->nullable()->after('event_id')->constrained('events')->nullOnDelete();
            $table->json('pool_event_ids')->nullable()->after('entry_event_id');
            $table->boolean('requires_configuration_review')->default(false)->after('pool_event_ids');
        });

        DB::table('project_scanners')
            ->orderBy('id')
            ->chunkById(100, function ($scanners): void {
                foreach ($scanners as $scanner) {
                    $poolEventIds = $scanner->event_id !== null
                        ? [(int) $scanner->event_id]
                        : DB::table('events')
                            ->where('project_id', $scanner->project_id)
                            ->whereNull('deletion_requested_at')
                            ->orderBy('starts_at')
                            ->orderBy('id')
                            ->pluck('id')
                            ->map(fn ($eventId) => (int) $eventId)
                            ->all();

                    DB::table('project_scanners')
                        ->where('id', $scanner->id)
                        ->update([
                            'entry_event_id' => $scanner->event_id !== null ? (int) $scanner->event_id : ($poolEventIds[0] ?? null),
                            'pool_event_ids' => json_encode($poolEventIds, JSON_THROW_ON_ERROR),
                            'requires_configuration_review' => $scanner->event_id === null,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_scanners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entry_event_id');
            $table->dropColumn(['pool_event_ids', 'requires_configuration_review']);
        });
    }
};
