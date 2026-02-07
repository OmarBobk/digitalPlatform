<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 1: Add entry_price, backfill from retail_price, create pricing_rules.
     * retail_price / wholesale_price columns are kept for fallback when entry_price is null.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('entry_price', 10, 2)->nullable()->after('slug');
        });

        DB::table('products')->update(['entry_price' => DB::raw('retail_price')]);

        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->decimal('min_price', 12, 2);
            $table->decimal('max_price', 12, 2);
            $table->decimal('wholesale_percentage', 8, 2);
            $table->decimal('retail_percentage', 8, 2);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('pricing_rules')->insert([
            'min_price' => 0,
            'max_price' => 999999.99,
            'wholesale_percentage' => 0,
            'retail_percentage' => 0,
            'priority' => 999,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('entry_price');
        });
    }
};
