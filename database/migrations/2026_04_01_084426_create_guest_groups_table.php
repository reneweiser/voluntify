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
        Schema::create('guest_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_list_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('guest_count');
            $table->timestamps();

            $table->index('guest_list_id');
        });
    }
};
