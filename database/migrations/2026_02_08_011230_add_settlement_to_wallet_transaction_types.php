<?php

declare(strict_types=1);

use App\Enums\WalletTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'settlement' to wallet_transactions.type enum.
     * Builds enum from actual distinct values in table to avoid "Data truncated"
     * when existing rows have values that differ from the PHP enum.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        $existing = DB::table('wallet_transactions')
            ->distinct()
            ->pluck('type')
            ->map(fn ($v) => (string) $v)
            ->filter()
            ->values()
            ->all();

        $values = array_unique(array_merge(
            $existing ?: WalletTransactionType::values(),
            ['settlement']
        ));
        $enum = implode("','", array_map(fn ($v) => addslashes((string) $v), $values));

        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN type ENUM('{$enum}') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $existing = DB::table('wallet_transactions')
            ->distinct()
            ->pluck('type')
            ->map(fn ($v) => (string) $v)
            ->filter()
            ->values()
            ->all();

        $values = array_values(array_filter($existing, fn ($v) => $v !== 'settlement'));
        $enum = implode("','", array_map(fn ($v) => addslashes((string) $v), $values));

        if ($values !== []) {
            DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN type ENUM('{$enum}') NOT NULL");
        }
    }
};
