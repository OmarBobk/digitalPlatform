<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix legacy 'profit' type: migrate to 'settlement'.
     * The PHP enum WalletTransactionType has Settlement, not Profit.
     */
    public function up(): void
    {
        DB::table('wallet_transactions')
            ->where('type', 'profit')
            ->update(['type' => 'settlement']);
    }

    public function down(): void
    {
        // Irreversible: settlement is the correct type.
    }
};
