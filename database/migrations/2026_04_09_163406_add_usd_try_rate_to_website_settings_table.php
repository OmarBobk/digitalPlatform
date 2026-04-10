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
            $table->decimal('usd_try_rate', 12, 6)->nullable()->after('prices_visible');
            $table->timestamp('usd_try_rate_updated_at')->nullable()->after('usd_try_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->dropColumn(['usd_try_rate', 'usd_try_rate_updated_at']);
        });
    }
};
