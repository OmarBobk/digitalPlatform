<?php

namespace App\Actions\PricingRules;

use App\Models\PricingRule;
use App\Models\User;

class UpsertPricingRule
{
    /**
     * @param  array{
     *     min_price:float|string,
     *     max_price:float|string,
     *     wholesale_percentage:float|string,
     *     retail_percentage:float|string,
     *     priority:int,
     *     is_active:bool
     * }  $data
     */
    public function handle(?int $ruleId, array $data, ?int $causedByUserId = null): PricingRule
    {
        $rule = $ruleId !== null
            ? PricingRule::query()->findOrFail($ruleId)
            : new PricingRule;

        $changed = $rule->exists
            ? [
                'min_price' => $rule->min_price,
                'max_price' => $rule->max_price,
                'wholesale_percentage' => $rule->wholesale_percentage,
                'retail_percentage' => $rule->retail_percentage,
                'priority' => $rule->priority,
                'is_active' => $rule->is_active,
            ]
            : [];

        $rule->fill([
            'min_price' => $data['min_price'],
            'max_price' => $data['max_price'],
            'wholesale_percentage' => $data['wholesale_percentage'],
            'retail_percentage' => $data['retail_percentage'],
            'priority' => (int) $data['priority'],
            'is_active' => $data['is_active'],
        ]);

        $rule->save();

        $user = $causedByUserId !== null ? User::query()->find($causedByUserId) : null;
        $event = $rule->wasRecentlyCreated ? 'pricing_rule.created' : 'pricing_rule.updated';

        activity()
            ->inLog('pricing_rules')
            ->event($event)
            ->performedOn($rule)
            ->causedBy($user)
            ->withProperties([
                'rule_id' => $rule->id,
                'min_price' => $rule->min_price,
                'max_price' => $rule->max_price,
                'wholesale_percentage' => $rule->wholesale_percentage,
                'retail_percentage' => $rule->retail_percentage,
                'priority' => $rule->priority,
                'is_active' => $rule->is_active,
                'changed' => $rule->wasRecentlyCreated ? null : $changed,
            ])
            ->log($rule->wasRecentlyCreated ? 'Pricing rule created' : 'Pricing rule updated');

        return $rule;
    }
}
