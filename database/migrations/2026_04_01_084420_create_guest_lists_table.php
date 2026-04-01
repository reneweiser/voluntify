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
        Schema::create('guest_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scanner_id')->constrained('project_scanners')->restrictOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->dateTime('confirmed_at')->nullable();
            $table->json('gear_items')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }
};
