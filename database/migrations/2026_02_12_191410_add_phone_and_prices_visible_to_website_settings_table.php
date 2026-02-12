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
        Schema::table('website_settings', function (Blueprint $table) {
            $table->string('primary_phone')->nullable()->after('contact_email');
            $table->string('secondary_phone')->nullable()->after('primary_phone');
            $table->boolean('prices_visible')->default(true)->after('secondary_phone');
            $table->dropColumn(['site_name', 'tagline']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->string('site_name')->nullable()->after('id');
            $table->string('tagline')->nullable()->after('contact_email');
            $table->dropColumn(['primary_phone', 'secondary_phone', 'prices_visible']);
        });
    }
};
