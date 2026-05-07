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
        Schema::table('guest_entries', function (Blueprint $table) {
            $table->timestamp('invitation_queued_at')->nullable()->after('invitation_sent_at');
            $table->timestamp('invitation_failed_at')->nullable()->after('invitation_queued_at');
            $table->index(['email', 'invitation_queued_at'], 'guest_entries_email_invitation_queued_at_index');
            $table->index(['email', 'invitation_failed_at'], 'guest_entries_email_invitation_failed_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_entries', function (Blueprint $table) {
            $table->dropIndex('guest_entries_email_invitation_queued_at_index');
            $table->dropIndex('guest_entries_email_invitation_failed_at_index');
            $table->dropColumn(['invitation_queued_at', 'invitation_failed_at']);
        });
    }
};
