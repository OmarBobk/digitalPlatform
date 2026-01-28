<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
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
        /** Orders snapshot checkout totals for payment and audit. */
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('order_number')->unique();
            $table->string('currency')->default('USD');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->enum('status', OrderStatus::values())->default(OrderStatus::PendingPayment->value);
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
