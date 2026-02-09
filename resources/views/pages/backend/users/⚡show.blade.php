<?php

use App\Models\LoyaltyTierConfig;
use App\Models\User;
use App\Services\LoyaltySpendService;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public User $user;

    public ?string $lockUntilDate = null;

    public ?string $lockUntilTime = null;

    public function mount(User $user): void
    {
        $this->authorize('view', $user);
        $this->user = $user->load('loyaltyOverrideBy');
        if ($user->loyalty_locked_until !== null) {
            $this->lockUntilDate = $user->loyalty_locked_until->format('Y-m-d');
            $this->lockUntilTime = $user->loyalty_locked_until->format('H:i');
        }
    }

    public function getRollingSpendProperty(): float
    {
        $windowDays = \App\Models\LoyaltySetting::getRollingWindowDays();

        return app(LoyaltySpendService::class)->computeRollingSpend($this->user, $windowDays);
    }

    public function getTierConfigProperty(): ?LoyaltyTierConfig
    {
        $role = $this->user->loyaltyRole();
        if ($role === null) {
            return null;
        }
        $tierName = $this->user->loyalty_tier?->value ?? 'bronze';

        return LoyaltyTierConfig::query()->forRole($role)->where('name', $tierName)->first();
    }

    public function getNextTierProperty(): ?LoyaltyTierConfig
    {
        $role = $this->user->loyaltyRole();
        if ($role === null) {
            return null;
        }
        $spend = $this->rollingSpend;

        return LoyaltyTierConfig::query()
            ->forRole($role)
            ->where('min_spend', '>', $spend)
            ->orderBy('min_spend')
            ->first();
    }

    public function saveLoyaltyLock(): void
    {
        $this->authorize('update', $this->user);
        $lockUntil = null;
        if ($this->lockUntilDate !== null && $this->lockUntilDate !== '') {
            $date = $this->lockUntilTime !== null && $this->lockUntilTime !== ''
                ? $this->lockUntilDate.' '.$this->lockUntilTime.':00'
                : $this->lockUntilDate.' 23:59:59';
            $lockUntil = \Carbon\Carbon::parse($date);
        }
        $previous = $this->user->loyalty_locked_until?->toIso8601String();
        $this->user->update([
            'loyalty_locked_until' => $lockUntil,
            'loyalty_override_by' => $lockUntil !== null ? auth()->id() : null,
        ]);
        activity()
            ->inLog('loyalty')
            ->event('loyalty.override')
            ->performedOn($this->user)
            ->causedBy(auth()->user())
            ->withProperties([
                'user_id' => $this->user->id,
                'lock_until_before' => $previous,
                'lock_until_after' => $lockUntil?->toIso8601String(),
                'admin_id' => auth()->id(),
            ])
            ->log('Loyalty tier lock updated');
        $this->user->refresh();
        $this->dispatch('loyalty-lock-saved');
    }

    public function clearLoyaltyLock(): void
    {
        $this->authorize('update', $this->user);
        $this->user->update([
            'loyalty_locked_until' => null,
            'loyalty_override_by' => null,
        ]);
        activity()
            ->inLog('loyalty')
            ->event('loyalty.override_cleared')
            ->performedOn($this->user)
            ->causedBy(auth()->user())
            ->withProperties(['user_id' => $this->user->id, 'admin_id' => auth()->id()])
            ->log('Loyalty tier lock cleared');
        $this->lockUntilDate = null;
        $this->lockUntilTime = null;
        $this->user->refresh();
        $this->dispatch('loyalty-lock-saved');
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.customer_detail'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6" data-test="admin-user-show-page">
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <flux:button variant="ghost" icon="arrow-left" icon-position="left" :href="route('admin.users.index')" wire:navigate>
                    {{ __('messages.back') }}
                </flux:button>
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">{{ $user->name }}</flux:heading>
            </div>
        </div>
        <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><span class="text-zinc-500 dark:text-zinc-400">{{ __('messages.email') }}:</span> <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->email }}</span></div>
            <div><span class="text-zinc-500 dark:text-zinc-400">{{ __('messages.username') }}:</span> <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->username ?? '—' }}</span></div>
        </div>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="sm" class="mb-4 text-zinc-900 dark:text-zinc-100">{{ __('messages.loyalty_tier') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.tier') }}</div>
                <flux:badge class="mt-1 capitalize">{{ ucfirst($user->loyalty_tier?->value ?? 'bronze') }}</flux:badge>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_rolling_spend', ['days' => \App\Models\LoyaltySetting::getRollingWindowDays()]) }}</div>
                <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">${{ number_format($this->rollingSpend, 2) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.discount_percent') }}</div>
                <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->tierConfig ? number_format((float) $this->tierConfig->discount_percentage, 1).'%' : '0%' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_evaluated_at') }}</div>
                <div class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $user->loyalty_evaluated_at?->format('M d, Y H:i') ?? '—' }}</div>
            </div>
            @if ($this->nextTier !== null)
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_next_tier_threshold') }}</div>
                    <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">${{ number_format((float) $this->nextTier->min_spend, 2) }} ({{ ucfirst($this->nextTier->name) }})</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_remaining_spend') }}</div>
                    <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">${{ number_format(max(0, (float) $this->nextTier->min_spend - $this->rollingSpend), 2) }}</div>
                </div>
            @endif
        </div>
        @if ($user->isLoyaltyLocked())
            <div class="mt-3 flex items-center gap-2 text-amber-600 dark:text-amber-400">
                <flux:icon icon="lock-closed" class="size-4" />
                <span>{{ __('messages.loyalty_locked_until', ['date' => $user->loyalty_locked_until?->format('M d, Y H:i')]) }}</span>
                @if ($user->loyaltyOverrideBy)
                    <span class="text-zinc-500 dark:text-zinc-400">({{ __('messages.by') }} {{ $user->loyaltyOverrideBy->name }})</span>
                @endif
            </div>
        @endif

        @can('update', $user)
            <div class="mt-6 rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:heading size="xs" class="mb-3 text-zinc-900 dark:text-zinc-100">{{ __('messages.loyalty_override') }}</flux:heading>
                <form wire:submit="saveLoyaltyLock" class="flex flex-wrap items-end gap-3">
                    <flux:field class="min-w-40">
                        <flux:label>{{ __('messages.lock_until_date') }}</flux:label>
                        <flux:input type="date" wire:model="lockUntilDate" />
                    </flux:field>
                    <flux:field class="min-w-32">
                        <flux:label>{{ __('messages.time') }}</flux:label>
                        <flux:input type="time" wire:model="lockUntilTime" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" size="sm">{{ __('messages.save') }}</flux:button>
                    @if ($user->loyalty_locked_until)
                        <flux:button type="button" variant="ghost" size="sm" wire:click="clearLoyaltyLock">{{ __('messages.clear_lock') }}</flux:button>
                    @endif
                </form>
                <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_override_hint') }}</flux:text>
            </div>
        @endcan
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="sm" class="mb-3 text-zinc-900 dark:text-zinc-100">{{ __('messages.audit_log') }}</flux:heading>
        <flux:button variant="ghost" size="sm" :href="route('admin.activities.index')" wire:navigate>
            {{ __('messages.view_activity_for_user') }}
        </flux:button>
        <flux:text class="mt-2 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_audit_hint') }}</flux:text>
    </section>
</div>
