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
        Schema::create('guest_entry_gear', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_gear_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('picked_up_count')->default(0);
            $table->string('selection')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->unique(['guest_entry_id', 'project_gear_item_id']);
        });
    }
};
