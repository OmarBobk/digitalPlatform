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
        Schema::table('products', function (Blueprint $table): void {
            $table->enum('amount_mode', ProductAmountMode::values())
                ->default(ProductAmountMode::Fixed->value)
                ->after('name');
            $table->string('amount_unit_label')->nullable()->after('amount_mode');
            $table->unsignedBigInteger('custom_amount_min')->nullable()->after('amount_unit_label');
            $table->unsignedBigInteger('custom_amount_max')->nullable()->after('custom_amount_min');
            $table->unsignedBigInteger('custom_amount_step')->nullable()->after('custom_amount_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'amount_mode',
                'amount_unit_label',
                'custom_amount_min',
                'custom_amount_max',
                'custom_amount_step',
            ]);
        });
    }
};
