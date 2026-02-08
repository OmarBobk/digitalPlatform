<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settlements batch fulfillments for profit credit to platform wallet.
     * total_amount is derived from sum(unit_price - entry_price) of included fulfillments.
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total_amount', 12, 2)->unsigned();
            $table->timestamps();
        });

        Schema::create('settlement_fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fulfillment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('fulfillment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_fulfillments');
        Schema::dropIfExists('settlements');
    }
};
