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
        Schema::create('guest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('qr_token', 64)->nullable()->unique();
            $table->dateTime('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->timestamps();

            $table->index('guest_group_id');
            $table->foreign('checked_in_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
