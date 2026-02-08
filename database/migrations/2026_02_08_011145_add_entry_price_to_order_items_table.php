<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add entry_price (COGS snapshot) to order_items for ledger-derived profit.
     * Backfill from products.entry_price for existing rows.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('entry_price', 12, 2)->nullable()->after('line_total');
        });

        $rows = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNotNull('products.entry_price')
            ->select('order_items.id', 'products.entry_price')
            ->get();

        foreach ($rows as $row) {
            DB::table('order_items')
                ->where('id', $row->id)
                ->update(['entry_price' => $row->entry_price]);
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('entry_price');
        });
    }
};
