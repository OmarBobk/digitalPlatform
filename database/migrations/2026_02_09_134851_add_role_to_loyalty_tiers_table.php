<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_tiers', function (Blueprint $table) {
            $table->string('role', 32)->default('customer')->after('id');
        });

        DB::table('loyalty_tiers')->update(['role' => 'customer']);

        Schema::table('loyalty_tiers', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['role', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_tiers', function (Blueprint $table) {
            $table->dropUnique(['role', 'name']);
            $table->unique('name');
        });

        Schema::table('loyalty_tiers', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
