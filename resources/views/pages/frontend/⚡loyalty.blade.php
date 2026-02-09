<?php

use App\Actions\Loyalty\EvaluateLoyaltyForUserAction;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTierConfig;
use App\Services\LoyaltySpendService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::frontend')] class extends Component
{
    public function mount(EvaluateLoyaltyForUserAction $evaluateLoyalty): void
    {
        abort_unless(auth()->check(), 403);
        $user = auth()->user();
        if ($user?->loyaltyRole() === null) {
            abort(404);
        }
        $evaluateLoyalty->handle($user);
    }

    #[Computed]
    public function loyaltyRollingSpend(): float
    {
        $windowDays = LoyaltySetting::getRollingWindowDays();

        return app(LoyaltySpendService::class)->computeRollingSpend(auth()->user(), $windowDays);
    }

    #[Computed]
    public function loyaltyCurrentTierConfig(): ?LoyaltyTierConfig
    {
        $user = auth()->user();
        $role = $user?->loyaltyRole();
        if ($role === null) {
            return null;
        }
        $tierName = $user->loyalty_tier?->value ?? 'bronze';

        return LoyaltyTierConfig::query()->forRole($role)->where('name', $tierName)->first();
    }

    #[Computed]
    public function loyaltyNextTier(): ?LoyaltyTierConfig
    {
        $user = auth()->user();
        $role = $user?->loyaltyRole();
        if ($role === null) {
            return null;
        }
        return LoyaltyTierConfig::query()
            ->forRole($role)
            ->where('min_spend', '>', $this->loyaltyRollingSpend)
            ->orderBy('min_spend')
            ->first();
    }

    #[Computed]
    public function loyaltyProgressPercent(): ?float
    {
        $next = $this->loyaltyNextTier;
        if ($next === null) {
            return null;
        }
        $threshold = (float) $next->min_spend;
        if ($threshold <= 0) {
            return 100.0;
        }
        return min(100.0, round(($this->loyaltyRollingSpend / $threshold) * 100, 1));
    }

    #[Computed]
    public function loyaltyAmountToNextTier(): ?float
    {
        $next = $this->loyaltyNextTier;
        if ($next === null) {
            return null;
        }
        return max(0.0, (float) $next->min_spend - $this->loyaltyRollingSpend);
    }

    /**
     * @return array<int, array{name: string, min_spend: float, discount_percentage: float}>
     */
    #[Computed]
    public function loyaltyTiersForLadder(): array
    {
        $user = auth()->user();
        $role = $user?->loyaltyRole();
        if ($role === null) {
            return [];
        }
        return LoyaltyTierConfig::query()
            ->forRole($role)
            ->orderBy('min_spend')
            ->get()
            ->map(fn (LoyaltyTierConfig $t) => [
                'name' => $t->name,
                'min_spend' => (float) $t->min_spend,
                'discount_percentage' => (float) $t->discount_percentage,
            ])
            ->all();
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.loyalty_tier'));
    }
};
?>

<div class="mx-auto w-full max-w-4xl px-3 py-6 sm:px-0 sm:py-10">
    <div class="mb-4 flex items-center">
        <x-back-button />
    </div>

    <section class="mb-6 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">{{ __('messages.loyalty_tier') }}</flux:heading>
        <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('messages.loyalty_page_intro') }}</flux:text>
        <flux:text class="mt-2 block text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('messages.loyalty_evaluation_window', ['days' => \App\Models\LoyaltySetting::getRollingWindowDays()]) }}
        </flux:text>
    </section>

    @if ($this->loyaltyCurrentTierConfig !== null)
        <div class="grid gap-6 lg:grid-cols-2">
            <section>
                <x-loyalty.tier-card
                    :current-tier-name="auth()->user()?->loyalty_tier?->value ?? 'bronze'"
                    :discount-percent="(float) $this->loyaltyCurrentTierConfig->discount_percentage"
                    :rolling-spend="$this->loyaltyRollingSpend"
                    :next-tier-name="$this->loyaltyNextTier?->name"
                    :next-tier-min-spend="$this->loyaltyNextTier ? (float) $this->loyaltyNextTier->min_spend : null"
                    :amount-to-next="$this->loyaltyAmountToNextTier"
                    :progress-percent="$this->loyaltyProgressPercent"
                    :window-days="\App\Models\LoyaltySetting::getRollingWindowDays()"
                    layout="full"
                />
            </section>
            <section>
                <x-loyalty.tier-ladder
                    :tiers="$this->loyaltyTiersForLadder"
                    :current-tier-name="auth()->user()?->loyalty_tier?->value ?? 'bronze'"
                    :rolling-spend="$this->loyaltyRollingSpend"
                    orientation="vertical"
                />
            </section>
        </div>
    @endif
</div>
