<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->assertScannerConfigurationIsClean();
        $this->deduplicateArrivals();

        Schema::table('project_scanners', function (Blueprint $table) {
            $table->dropForeign(['entry_event_id']);
        });

        Schema::table('project_scanners', function (Blueprint $table) {
            $table->unsignedBigInteger('entry_event_id')->nullable(false)->change();
            $table->json('pool_event_ids')->nullable(false)->change();
        });

        Schema::table('project_scanners', function (Blueprint $table) {
            $table->foreign('entry_event_id')->references('id')->on('events')->restrictOnDelete();
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('event_arrivals', function (Blueprint $table) {
            $table->unique(['ticket_id', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_scanners', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });

        DB::table('project_scanners')->update([
            'event_id' => DB::raw('entry_event_id'),
        ]);

        Schema::table('project_scanners', function (Blueprint $table) {
            $table->dropForeign(['entry_event_id']);
        });

        Schema::table('project_scanners', function (Blueprint $table) {
            $table->unsignedBigInteger('entry_event_id')->nullable()->change();
            $table->json('pool_event_ids')->nullable()->change();
        });

        Schema::table('project_scanners', function (Blueprint $table) {
            $table->foreign('entry_event_id')->references('id')->on('events')->nullOnDelete();
        });

        Schema::table('event_arrivals', function (Blueprint $table) {
            $table->dropUnique(['ticket_id', 'event_id']);
        });
    }

    private function assertScannerConfigurationIsClean(): void
    {
        $invalidScanners = DB::table('project_scanners')
            ->select(['id', 'entry_event_id', 'pool_event_ids', 'requires_configuration_review'])
            ->get()
            ->filter(function (object $scanner): bool {
                $poolEventIds = json_decode((string) $scanner->pool_event_ids, true);

                return $scanner->requires_configuration_review
                    || $scanner->entry_event_id === null
                    || ! is_array($poolEventIds)
                    || $poolEventIds === []
                    || ! in_array((int) $scanner->entry_event_id, array_map('intval', $poolEventIds), true);
            });

        if ($invalidScanners->isNotEmpty()) {
            throw new RuntimeException('Cannot finalize scanner configuration while review-required or incomplete scanners still exist.');
        }
    }

    private function deduplicateArrivals(): void
    {
        DB::table('event_arrivals')
            ->select(['ticket_id', 'event_id'])
            ->groupBy('ticket_id', 'event_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('ticket_id')
            ->orderBy('event_id')
            ->chunk(100, function (Collection $duplicateGroups): void {
                foreach ($duplicateGroups as $group) {
                    $arrivals = DB::table('event_arrivals')
                        ->where('ticket_id', $group->ticket_id)
                        ->where('event_id', $group->event_id)
                        ->orderBy('flagged')
                        ->orderBy('id')
                        ->get();

                    $arrivalToKeep = $arrivals->firstWhere('flagged', false) ?? $arrivals->first();

                    if ($arrivalToKeep === null) {
                        continue;
                    }

                    $arrivalIdsToDelete = $arrivals
                        ->pluck('id')
                        ->reject(fn (int $arrivalId): bool => $arrivalId === $arrivalToKeep->id)
                        ->all();

                    if ($arrivalIdsToDelete !== []) {
                        DB::table('event_arrivals')->whereIn('id', $arrivalIdsToDelete)->delete();
                    }
                }
            });
    }
};
