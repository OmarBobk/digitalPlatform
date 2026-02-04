<?php

declare(strict_types=1);

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
        Schema::table('fulfillments', function (Blueprint $table) {
            $table->dropUnique(['order_item_id']);
            $table->index('order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fulfillments', function (Blueprint $table) {
            $table->dropIndex(['order_item_id']);
            $table->unique('order_item_id');
        });
    }
};
