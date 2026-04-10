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
        if (! Schema::hasColumn('website_settings', 'usd_try_rate')) {
            Schema::table('website_settings', function (Blueprint $table) {
                $table->decimal('usd_try_rate', 12, 6)->nullable()->after('prices_visible');
            });
        }

        if (! Schema::hasColumn('website_settings', 'usd_try_rate_updated_at')) {
            Schema::table('website_settings', function (Blueprint $table) {
                $table->timestamp('usd_try_rate_updated_at')->nullable()->after('usd_try_rate');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            if (Schema::hasColumn('website_settings', 'usd_try_rate_updated_at')) {
                $table->dropColumn('usd_try_rate_updated_at');
            }
            if (Schema::hasColumn('website_settings', 'usd_try_rate')) {
                $table->dropColumn('usd_try_rate');
            }
        });
    }
};
