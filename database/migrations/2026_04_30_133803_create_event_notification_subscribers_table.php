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
        Schema::create('event_notification_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('verification_token_hash')->nullable();
            $table->dateTime('verification_expires_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->string('unsubscribe_token_hash')->nullable();
            $table->dateTime('last_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_notification_subscribers');
    }
};
