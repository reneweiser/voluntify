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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('volunteer_jobs')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->dateTime('send_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
