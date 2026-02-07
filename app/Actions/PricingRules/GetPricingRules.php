<?php

namespace App\Actions\PricingRules;

use App\Models\PricingRule;
use Illuminate\Database\Eloquent\Collection;

class GetPricingRules
{
    /**
     * @return Collection<int, PricingRule>
     */
    public function handle(): Collection
    {
        return PricingRule::query()
            ->orderBy('priority')
            ->orderBy('min_price')
            ->get();
    }
}
