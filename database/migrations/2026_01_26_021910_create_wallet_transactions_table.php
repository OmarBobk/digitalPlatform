<?php

declare(strict_types=1);

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
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
        /** Ledger of all wallet balance changes for auditability. */
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->enum('type', WalletTransactionType::values());
            $table->enum('direction', WalletTransactionDirection::values());
            $table->decimal('amount', 10, 2)->unsigned();
            $table->string('status');
            $table->nullableMorphs('reference');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
