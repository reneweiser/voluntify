<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('cancellation_enabled')->default(false)->after('contact_email');
            $table->unsignedInteger('cancellation_cutoff_hours')->nullable()->after('cancellation_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['cancellation_enabled', 'cancellation_cutoff_hours']);
        });
    }
};
