<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('volunteers')
            ->whereNull('email_verified_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('shift_signups')
                    ->whereColumn('shift_signups.volunteer_id', 'volunteers.id');
            })
            ->update([
                'email_verified_at' => DB::raw('(select min(shift_signups.signed_up_at) from shift_signups where shift_signups.volunteer_id = volunteers.id)'),
            ]);
    }

    public function down(): void {}
};
