<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('package_requirements')
            ->where('key', 'player_id')
            ->update(['key' => 'id']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('package_requirements')
            ->where('key', 'id')
            ->update(['key' => 'player_id']);
    }
};
