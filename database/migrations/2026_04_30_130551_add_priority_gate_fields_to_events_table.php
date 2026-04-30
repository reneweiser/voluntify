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
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority_unlock_threshold_percent')->nullable()->after('notification_email');
            $table->dateTime('priority_gate_unlocked_at')->nullable()->after('priority_unlock_threshold_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['priority_unlock_threshold_percent', 'priority_gate_unlocked_at']);
        });
    }
};
