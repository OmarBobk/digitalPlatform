<?php

declare(strict_types=1);

use App\Enums\ProductAmountMode;
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
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('amount_mode')
                ->default(ProductAmountMode::Fixed->value)
                ->after('quantity');
            $table->unsignedBigInteger('requested_amount')->nullable()->after('amount_mode');
            $table->string('amount_unit_label')->nullable()->after('requested_amount');
            $table->json('pricing_meta')->nullable()->after('amount_unit_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'amount_mode',
                'requested_amount',
                'amount_unit_label',
                'pricing_meta',
            ]);
        });
    }
};
