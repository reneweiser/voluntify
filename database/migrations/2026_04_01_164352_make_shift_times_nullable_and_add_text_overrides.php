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
        Schema::table('shifts', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->change();
            $table->dateTime('ends_at')->nullable()->change();
            $table->date('shift_date')->nullable()->after('volunteer_job_id');
            $table->string('display_text')->nullable()->after('ends_at');
        });

        DB::table('shifts')->whereNotNull('starts_at')->update([
            'shift_date' => DB::raw('DATE(starts_at)'),
        ]);

        Schema::table('shifts', function (Blueprint $table) {
            $table->date('shift_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['shift_date', 'display_text']);
            $table->dateTime('starts_at')->nullable(false)->change();
            $table->dateTime('ends_at')->nullable(false)->change();
        });
    }
};
