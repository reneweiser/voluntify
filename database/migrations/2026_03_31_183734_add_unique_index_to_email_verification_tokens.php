<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->unique('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
        });
    }
};
