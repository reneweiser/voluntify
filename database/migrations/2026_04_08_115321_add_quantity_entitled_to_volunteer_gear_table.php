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
        Schema::table('volunteer_gear', function (Blueprint $table) {
            $table->unsignedInteger('quantity_entitled')->nullable()->after('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volunteer_gear', function (Blueprint $table) {
            $table->dropColumn('quantity_entitled');
        });
    }
};
