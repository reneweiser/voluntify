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
        Schema::table('projects', function (Blueprint $table) {
            $table->text('website_description')->nullable()->after('description');
            $table->string('website_contact_info')->nullable()->after('website_description');
            $table->boolean('website_published')->default(false)->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['website_description', 'website_contact_info', 'website_published']);
        });
    }
};
