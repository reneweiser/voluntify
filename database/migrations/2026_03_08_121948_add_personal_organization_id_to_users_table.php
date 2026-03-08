<?php

use App\Enums\StaffRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('personal_organization_id')
                ->nullable()
                ->after('current_organization_id')
                ->constrained('organizations')
                ->nullOnDelete();
        });

        // Backfill: set personal_organization_id to the earliest org where user is Organizer
        DB::table('users')
            ->whereNull('personal_organization_id')
            ->eachById(function ($user) {
                $earliestOrgId = DB::table('organization_user')
                    ->where('user_id', $user->id)
                    ->where('role', StaffRole::Organizer->value)
                    ->orderBy('created_at')
                    ->value('organization_id');

                if ($earliestOrgId) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['personal_organization_id' => $earliestOrgId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['personal_organization_id']);
            $table->dropColumn('personal_organization_id');
        });
    }
};
