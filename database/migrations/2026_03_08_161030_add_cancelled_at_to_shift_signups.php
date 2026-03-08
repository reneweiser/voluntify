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
        Schema::table('shift_signups', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->index()->after('notification_4h_sent');
        });
    }

    public function down(): void
    {
        Schema::table('shift_signups', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
