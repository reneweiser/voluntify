<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('deletion_requested_at');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('deletion_requested_at');
        });
    }
};
