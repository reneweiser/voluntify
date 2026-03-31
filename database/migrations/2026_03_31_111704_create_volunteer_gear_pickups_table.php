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
        Schema::create('volunteer_gear_pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_gear_id')->constrained('volunteer_gear')->cascadeOnDelete();
            $table->foreignId('picked_up_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('picked_up_at');
            $table->string('state')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_gear_pickups');
    }
};
