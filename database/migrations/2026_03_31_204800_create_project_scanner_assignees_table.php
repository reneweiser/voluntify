<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_scanner_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_scanner_id')
                ->constrained('project_scanners')
                ->cascadeOnDelete();
            $table->string('email');
            $table->dateTime('link_sent_at')->nullable();
            $table->dateTime('authenticated_at')->nullable();
            $table->timestamps();

            $table->index('project_scanner_id');
            $table->unique(['project_scanner_id', 'email']);
        });
    }
};
