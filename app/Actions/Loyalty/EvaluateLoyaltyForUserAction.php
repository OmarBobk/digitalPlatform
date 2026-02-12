<?php

declare(strict_types=1);

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyTier;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTierConfig;
use App\Models\User;
use App\Notifications\LoyaltyTierChangedNotification;
use App\Services\LoyaltySpendService;
use App\Services\SystemEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EvaluateLoyaltyForUserAction
{
    public function __construct(
        private readonly LoyaltySpendService $spendService
    ) {}

    /**
     * Idempotent: compute rolling spend, determine tier, update user. Skips if user is locked.
     */
    public function handle(User $user): bool
    {
        if ($user->isLoyaltyLocked()) {
            return false;
        }

        $role = $user->loyaltyRole();
        if ($role === null) {
            return false;
        }

        $windowDays = LoyaltySetting::getRollingWindowDays();
        $spend = $this->spendService->computeRollingSpend($user, $windowDays);

        $tierConfig = LoyaltyTierConfig::query()
            ->forRole($role)
            ->where('min_spend', '<=', $spend)
            ->orderByDesc('min_spend')
            ->first();

        $tierName = $tierConfig !== null ? $tierConfig->name : LoyaltyTier::Bronze->value;
        $previousTier = $user->loyalty_tier?->value ?? LoyaltyTier::Bronze->value;

        $user->update([
            'loyalty_tier' => $tierName,
            'loyalty_evaluated_at' => now(),
        ]);

        if ($previousTier !== $tierName && Schema::hasTable('activity_log')) {
            activity()
                ->inLog('loyalty')
                ->event('loyalty.tier_changed')
                ->performedOn($user)
                ->withProperties([
                    'user_id' => $user->id,
                    'previous_tier' => $previousTier,
                    'new_tier' => $tierName,
                ])
                ->log('Loyalty tier changed');
        }

        if ($previousTier !== $tierName) {
            $userId = $user->id;
            DB::afterCommit(function () use ($userId, $previousTier, $tierName): void {
                $u = User::query()->find($userId);
                if ($u !== null) {
                    app(SystemEventService::class)->record(
                        'tier.upgraded',
                        $u,
                        null,
                        [
                            'previous_tier' => $previousTier,
                            'new_tier' => $tierName,
                        ],
                        'info',
                        false,
                        $tierName,
                    );
                    $u->notify(LoyaltyTierChangedNotification::fromUser($u, $previousTier, $tierName));
                }
            });
        }

        return true;
    }
}
