<?php

declare(strict_types=1);

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyTier;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTierConfig;
use App\Models\User;
use App\Services\LoyaltySpendService;

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

        $user->update([
            'loyalty_tier' => $tierName,
            'loyalty_evaluated_at' => now(),
        ]);

        return true;
    }
}
