<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WalletTransactionDirection;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\WalletReconciledNotification;
use App\Services\NotificationRecipientService;
use App\Services\OperationalIntelligenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            $result = DB::transaction(function () use ($wallet, $dryRun): ?array {
                $lockedWallet = Wallet::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expected = (float) WalletTransaction::query()
                    ->where('wallet_id', $lockedWallet->id)
                    ->where('status', WalletTransaction::STATUS_POSTED)
                    ->selectRaw(
                        'COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE -amount END), 0) as balance',
                        [WalletTransactionDirection::Credit->value]
                    )
                    ->value('balance');

                $expected = round($expected, 2);
                $stored = round((float) $lockedWallet->balance, 2);
                $diff = round($expected - $stored, 2);

                if ($diff === 0.0) {
                    return null;
                }

                $walletForDrift = $lockedWallet;
                $driftMeta = ['stored' => $stored, 'expected' => $expected, 'diff' => $diff];
                DB::afterCommit(function () use ($walletForDrift, $driftMeta): void {
                    app(OperationalIntelligenceService::class)->detectReconciliationDrift($walletForDrift, $driftMeta);
                });

                if (! $dryRun) {
                    $lockedWallet->update([
                        'balance' => number_format($expected, 2, '.', ''),
                    ]);
                    activity()
                        ->inLog('payments')
                        ->event('wallet.reconciled')
                        ->performedOn($lockedWallet)
                        ->withProperties([
                            'wallet_id' => $lockedWallet->id,
                            'user_id' => $lockedWallet->user_id,
                            'stored_balance' => $stored,
                            'expected_balance' => $expected,
                            'diff' => $diff,
                        ])
                        ->log('Wallet reconciled');

                    $notification = WalletReconciledNotification::fromWallet($lockedWallet, $stored, $expected, $diff);
                    app(NotificationRecipientService::class)->adminUsers()->each(fn ($admin) => $admin->notify($notification));

                    return ['stored' => $stored, 'expected' => $expected, 'diff' => $diff];
                }

                return ['stored' => $stored, 'expected' => $expected, 'diff' => $diff];
            });

            if ($result === null) {
                continue;
            }

            $hasDrift = true;
            $this->line(sprintf(
                'Wallet %d (user %d): stored=%.2f expected=%.2f diff=%.2f',
                $wallet->id,
                $wallet->user_id,
                $result['stored'],
                $result['expected'],
                $result['diff']
            ));

            if (! $dryRun) {
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
