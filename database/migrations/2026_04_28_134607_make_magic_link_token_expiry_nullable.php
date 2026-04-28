<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('magic_link_tokens', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->change();
        });

        DB::table('magic_link_tokens')->update([
            'expires_at' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('magic_link_tokens')
            ->whereNull('expires_at')
            ->orderBy('id')
            ->eachById(function (object $token): void {
                DB::table('magic_link_tokens')
                    ->where('id', $token->id)
                    ->update([
                        'expires_at' => Carbon::parse($token->created_at)->addHours(72),
                    ]);
            });

        Schema::table('magic_link_tokens', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable(false)->change();
        });
    }
};
