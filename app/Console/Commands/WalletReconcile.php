<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WalletTransactionDirection;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;

class WalletReconcile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:reconcile
                            {--user= : Only reconcile wallets for a user ID}
                            {--dry-run : Show drift without updating balances}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile wallet balances against posted transactions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');

        if ($userId !== null && ! ctype_digit((string) $userId)) {
            $this->error('Invalid user id.');

            return self::FAILURE;
        }

        $wallets = Wallet::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', (int) $userId))
            ->get();

        if ($wallets->isEmpty()) {
            $this->line('No wallets found.');

            return self::SUCCESS;
        }

        $hasDrift = false;
        $updated = 0;

        foreach ($wallets as $wallet) {
            $expected = (float) WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('status', WalletTransaction::STATUS_POSTED)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE -amount END), 0) as balance',
                    [WalletTransactionDirection::Credit->value]
                )
                ->value('balance');

            $expected = round($expected, 2);
            $stored = round((float) $wallet->balance, 2);
            $diff = round($expected - $stored, 2);

            if ($diff === 0.0) {
                continue;
            }

            $hasDrift = true;
            $this->line(sprintf(
                'Wallet %d (user %d): stored=%.2f expected=%.2f diff=%.2f',
                $wallet->id,
                $wallet->user_id,
                $stored,
                $expected,
                $diff
            ));

            if (! $dryRun) {
                $wallet->update([
                    'balance' => number_format($expected, 2, '.', ''),
                ]);
                activity()
                    ->inLog('payments')
                    ->event('wallet.reconciled')
                    ->performedOn($wallet)
                    ->withProperties([
                        'wallet_id' => $wallet->id,
                        'user_id' => $wallet->user_id,
                        'stored_balance' => $stored,
                        'expected_balance' => $expected,
                        'diff' => $diff,
                    ])
                    ->log('Wallet reconciled');
                $updated++;
            }
        }

        if (! $hasDrift) {
            $this->info('No drift detected.');
        } elseif (! $dryRun) {
            $this->info(sprintf('Updated %d wallet(s).', $updated));
        }

        return self::SUCCESS;
    }
}
