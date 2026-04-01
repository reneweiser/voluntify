<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_scanners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->json('modes')->nullable();
            $table->json('gear_item_ids')->nullable();
            $table->text('hint_text')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('auth_code');
            $table->string('scanner_token', 64)->unique();
            $table->timestamps();

            $table->index('project_id');
            $table->index('scanner_token');
        });
    }
};
