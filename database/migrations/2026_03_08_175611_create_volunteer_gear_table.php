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
        Schema::create('volunteer_gear', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_gear_item_id')->constrained('project_gear_items')->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained()->cascadeOnDelete();
            $table->string('size')->nullable();
            $table->timestamps();

            $table->unique(['project_gear_item_id', 'volunteer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_gear');
    }
};
