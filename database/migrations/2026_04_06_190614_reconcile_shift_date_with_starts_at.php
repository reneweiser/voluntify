<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shifts')
            ->whereNotNull('starts_at')
            ->whereRaw('shift_date != DATE(starts_at)')
            ->update(['shift_date' => DB::raw('DATE(starts_at)')]);
    }
};
