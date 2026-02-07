<?php

namespace App\Actions\PricingRules;

use App\Models\PricingRule;
use App\Models\User;

class DeletePricingRule
{
    public function handle(int $ruleId, ?int $causedByUserId = null): void
    {
        $rule = PricingRule::query()->findOrFail($ruleId);

        $properties = [
            'rule_id' => $rule->id,
            'min_price' => $rule->min_price,
            'max_price' => $rule->max_price,
            'wholesale_percentage' => $rule->wholesale_percentage,
            'retail_percentage' => $rule->retail_percentage,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
        ];

        $rule->delete();

        $user = $causedByUserId !== null ? User::query()->find($causedByUserId) : null;

        activity()
            ->inLog('pricing_rules')
            ->event('pricing_rule.deleted')
            ->withProperties($properties)
            ->causedBy($user)
            ->log('Pricing rule deleted');
    }
}
