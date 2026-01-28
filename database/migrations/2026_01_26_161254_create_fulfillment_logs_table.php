<?php

declare(strict_types=1);

use App\Enums\FulfillmentLogLevel;
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
        /** Fulfillment logs are admin-only audit/debug entries. */
        Schema::create('fulfillment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->enum('level', FulfillmentLogLevel::values());
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('fulfillment_id');
            $table->index('level');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_logs');
    }
};
