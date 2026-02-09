<?php

use App\Models\LoyaltySetting;
use App\Models\LoyaltyTierConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $selectedRole = 'customer';

    public ?int $editingId = null;

    public string $editName = '';

    public string $editMinSpend = '';

    public string $editDiscountPercentage = '';

    public string $rollingWindowDays = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage_loyalty_tiers'), 403);
        $this->rollingWindowDays = (string) LoyaltySetting::getRollingWindowDays();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function roleOptions(): array
    {
        return [
            ['value' => 'customer', 'label' => __('messages.loyalty_role_customer')],
            ['value' => 'salesperson', 'label' => __('messages.loyalty_role_salesperson')],
        ];
    }

    /**
     * @return Collection<int, LoyaltyTierConfig>
     */
    public function getTiersProperty(): Collection
    {
        return LoyaltyTierConfig::query()
            ->forRole($this->selectedRole)
            ->orderBy('min_spend')
            ->get();
    }

    protected function editRules(): array
    {
        return [
            'editMinSpend' => ['required', 'numeric', 'min:0'],
            'editDiscountPercentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function startEdit(int $id): void
    {
        $tier = LoyaltyTierConfig::query()->findOrFail($id);
        $this->editingId = $tier->id;
        $this->editName = $tier->name;
        $this->editMinSpend = (string) $tier->min_spend;
        $this->editDiscountPercentage = (string) $tier->discount_percentage;
    }

    public function saveTier(): void
    {
        $this->validate($this->editRules());

        $tier = LoyaltyTierConfig::query()->findOrFail($this->editingId);
        $tier->update([
            'min_spend' => (float) $this->editMinSpend,
            'discount_percentage' => (float) $this->editDiscountPercentage,
        ]);

        $this->cancelEdit();
        $this->dispatch('tier-saved');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editName', 'editMinSpend', 'editDiscountPercentage']);
        $this->resetValidation();
    }

    public function tierCardColor(string $name): string
    {
        return match (strtolower($name)) {
            'bronze' => 'bronze',
            'silver' => 'silver',
            'gold' => 'gold',
            default => 'zinc',
        };
    }

    public function saveRollingWindow(): void
    {
        $this->validate([
            'rollingWindowDays' => ['required', 'integer', 'min:1', 'max:730'],
        ]);

        LoyaltySetting::instance()->update([
            'rolling_window_days' => (int) $this->rollingWindowDays,
        ]);

        $this->dispatch('rolling-window-saved');
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.loyalty_tiers'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6" data-test="loyalty-tiers-page">
    {{-- Hero --}}
    <section class="rounded-2xl border border-zinc-200 bg-gradient-to-br from-violet-500/10 via-transparent to-amber-500/10 p-6 shadow-sm dark:border-zinc-700 dark:from-violet-600/20 dark:to-amber-600/20 sm:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-400">
                    <flux:icon icon="sparkles" class="size-8" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.loyalty_tiers') }}
                    </flux:heading>
                    <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.loyalty_tiers_intro') }}
                    </flux:text>
                </div>
            </div>
        </div>
    </section>

    {{-- Role tabs --}}
    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
        @foreach ($this->roleOptions() as $opt)
            <button
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-medium transition {{ $selectedRole === $opt['value'] ? 'border-(--color-accent) text-(--color-accent)' : 'border-transparent text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
                wire:click="$set('selectedRole', '{{ $opt['value'] }}')"
            >
                {{ $opt['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Rolling window (days) --}}
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
        <flux:heading size="sm" class="mb-3 text-zinc-900 dark:text-zinc-100">{{ __('messages.loyalty_rolling_window_days') }}</flux:heading>
        <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_rolling_window_days_hint') }}</flux:text>
        <form wire:submit="saveRollingWindow" class="flex flex-wrap items-end gap-3">
            <flux:field class="min-w-0 flex-1 sm:max-w-[8rem]">
                <flux:label>{{ __('messages.loyalty_rolling_window_days') }}</flux:label>
                <flux:input type="number" min="1" max="730" step="1" wire:model="rollingWindowDays" />
                @error('rollingWindowDays')
                    <flux:text color="red" class="mt-1 text-sm">{{ $message }}</flux:text>
                @enderror
            </flux:field>
            <flux:button type="submit" variant="primary" size="sm" wire:loading.attr="disabled">{{ __('messages.save') }}</flux:button>
        </form>
        <x-action-message class="mt-3" on="rolling-window-saved">{{ __('messages.saved') }}</x-action-message>
    </section>

    {{-- Tier cards --}}
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->tiers as $tier)
            @php
                $name = strtolower($tier->name);
                $isBronze = $name === 'bronze';
                $isSilver = $name === 'silver';
                $isGold = $name === 'gold';
                $cardBg = $isBronze ? 'bg-amber-50/80 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800' : ($isSilver ? 'bg-slate-50/80 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700' : ($isGold ? 'bg-amber-50/80 dark:bg-amber-950/40 border-amber-300 dark:border-amber-700' : 'bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700'));
                $badgeColor = $isBronze ? 'amber' : ($isSilver ? 'zinc' : 'amber');
                $iconBg = $isBronze ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' : ($isSilver ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300' : 'bg-amber-200 text-amber-800 dark:bg-amber-800/60 dark:text-amber-200');
            @endphp
            <div
                class="rounded-2xl border p-5 shadow-sm transition hover:shadow-md {{ $cardBg }}"
                wire:key="tier-{{ $tier->id }}"
            >
                @if ($editingId === $tier->id)
                    <form wire:submit="saveTier" class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $iconBg }}">
                                <flux:icon icon="{{ $isGold ? 'star' : ($isSilver ? 'sparkles' : 'gift') }}" class="size-5" />
                            </div>
                            <flux:heading size="sm" class="capitalize text-zinc-900 dark:text-zinc-100">{{ $tier->name }}</flux:heading>
                        </div>
                        <flux:field>
                            <flux:label>{{ __('messages.loyalty_min_spend') }}</flux:label>
                            <flux:input type="number" min="0" step="0.01" wire:model="editMinSpend" />
                            @error('editMinSpend')
                                <flux:text color="red">{{ $message }}</flux:text>
                            @enderror
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('messages.discount_percent') }}</flux:label>
                            <flux:input type="number" min="0" max="100" step="0.01" wire:model="editDiscountPercentage" />
                            @error('editDiscountPercentage')
                                <flux:text color="red">{{ $message }}</flux:text>
                            @enderror
                        </flux:field>
                        <div class="flex gap-2 pt-2">
                            <flux:button type="submit" variant="primary" size="sm" wire:loading.attr="disabled">{{ __('messages.save') }}</flux:button>
                            <flux:button type="button" variant="ghost" size="sm" wire:click="cancelEdit">{{ __('messages.cancel') }}</flux:button>
                        </div>
                    </form>
                @else
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $iconBg }}">
                                <flux:icon icon="{{ $isGold ? 'star' : ($isSilver ? 'sparkles' : 'gift') }}" class="size-6" />
                            </div>
                            <div>
                                <flux:badge color="{{ $badgeColor }}" class="capitalize text-sm font-semibold">{{ $tier->name }}</flux:badge>
                                <div class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100" dir="ltr">${{ number_format((float) $tier->min_spend, 0) }}</div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_min_spend') }}</flux:text>
                            </div>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $tier->id }})" aria-label="{{ __('messages.edit') }}" />
                    </div>
                    <div class="mt-4 flex items-baseline gap-2 border-t border-zinc-200/80 pt-4 dark:border-zinc-600/50">
                        <span class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format((float) $tier->discount_percentage, 0) }}%</span>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('messages.discount_percent') }}</flux:text>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($this->tiers->isEmpty())
        <section class="flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-12 text-center dark:border-zinc-700 dark:bg-zinc-800/30">
            <div class="flex size-16 items-center justify-center rounded-full bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                <flux:icon icon="sparkles" class="size-8" />
            </div>
            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">{{ __('messages.no_loyalty_tiers') }}</flux:heading>
            <flux:text class="max-w-md text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.loyalty_tiers_seed_hint') }}</flux:text>
        </section>
    @endif
</div>
