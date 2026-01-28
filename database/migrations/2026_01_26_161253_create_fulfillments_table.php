<?php

declare(strict_types=1);

use App\Enums\FulfillmentStatus;
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
        /** Fulfillments track per-item delivery state. */
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('order_item_id')->constrained('order_items');
            $table->string('provider');
            $table->enum('status', FulfillmentStatus::values())->default(FulfillmentStatus::Queued->value);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('order_item_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
