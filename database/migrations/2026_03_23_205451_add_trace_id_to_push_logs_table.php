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
        Schema::table('push_logs', function (Blueprint $table) {
            $table->uuid('trace_id')->nullable()->after('notification_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('push_logs', function (Blueprint $table) {
            $table->dropColumn('trace_id');
        });
    }
};
