<?php

declare(strict_types=1);

use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
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
        /** Top-up requests capture manual funding before ledger posting. */
        Schema::create('topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('wallet_id')->constrained();
            $table->enum('method', TopupMethod::values());
            $table->decimal('amount', 10, 2)->unsigned();
            $table->string('currency')->default('USD');
            $table->enum('status', TopupRequestStatus::values())->default(TopupRequestStatus::Pending->value);
            $table->text('note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
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
        Schema::dropIfExists('topup_requests');
    }
};
