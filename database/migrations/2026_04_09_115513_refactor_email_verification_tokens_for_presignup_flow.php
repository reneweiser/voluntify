<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->dropForeign(['volunteer_id']);
        });

        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('volunteer_id')->nullable()->change();
            $table->json('shift_ids')->nullable()->change();

            $table->foreign('volunteer_id')->references('id')->on('volunteers')->nullOnDelete();

            $table->string('email')->nullable()->after('project_id');
            $table->timestamp('verified_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->dropForeign(['volunteer_id']);
            $table->dropColumn(['email', 'verified_at']);
        });

        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('volunteer_id')->nullable(false)->change();
            $table->json('shift_ids')->nullable(false)->change();

            $table->foreign('volunteer_id')->references('id')->on('volunteers')->cascadeOnDelete();
        });
    }
};
