<?php

use App\Actions\PricingRules\DeletePricingRule;
use App\Actions\PricingRules\GetPricingRules;
use App\Actions\PricingRules\UpsertPricingRule;
use App\Models\PricingRule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public ?int $editingRuleId = null;
    public ?string $ruleMinPrice = null;
    public ?string $ruleMaxPrice = null;
    public ?string $ruleWholesalePercentage = null;
    public ?string $ruleRetailPercentage = null;
    public ?int $rulePriority = null;
    public bool $ruleIsActive = true;

    public bool $showDeleteModal = false;
    public ?int $deleteRuleId = null;
    public string $deleteRuleSummary = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage_products'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function ruleRules(): array
    {
        return [
            'ruleMinPrice' => ['required', 'numeric', 'min:0'],
            'ruleMaxPrice' => ['required', 'numeric', 'min:0', 'gt:ruleMinPrice'],
            'ruleWholesalePercentage' => ['required', 'numeric', 'min:-100'],
            'ruleRetailPercentage' => ['required', 'numeric', 'min:-100'],
            'rulePriority' => ['required', 'integer', 'min:0'],
            'ruleIsActive' => ['boolean'],
        ];
    }

    public function saveRule(): void
    {
        $validated = $this->validate($this->ruleRules());

        app(UpsertPricingRule::class)->handle(
            $this->editingRuleId,
            [
                'min_price' => $validated['ruleMinPrice'],
                'max_price' => $validated['ruleMaxPrice'],
                'wholesale_percentage' => $validated['ruleWholesalePercentage'],
                'retail_percentage' => $validated['ruleRetailPercentage'],
                'priority' => $validated['rulePriority'],
                'is_active' => $validated['ruleIsActive'],
            ],
            auth()->id()
        );

        $this->resetRuleForm();
        $this->dispatch('pricing-rule-saved');
    }

    public function startEditRule(int $ruleId): void
    {
        $rule = PricingRule::query()->findOrFail($ruleId);

        $this->editingRuleId = $rule->id;
        $this->ruleMinPrice = (string) $rule->min_price;
        $this->ruleMaxPrice = (string) $rule->max_price;
        $this->ruleWholesalePercentage = (string) $rule->wholesale_percentage;
        $this->ruleRetailPercentage = (string) $rule->retail_percentage;
        $this->rulePriority = $rule->priority;
        $this->ruleIsActive = $rule->is_active;

        $this->dispatch('open-rules-panel');
    }

    public function confirmDeleteRule(int $ruleId): void
    {
        $rule = PricingRule::query()->findOrFail($ruleId);

        $this->deleteRuleId = $rule->id;
        $this->deleteRuleSummary = sprintf(
            '%s – %s (retail %s%%, wholesale %s%%)',
            $rule->min_price,
            $rule->max_price,
            $rule->retail_percentage,
            $rule->wholesale_percentage
        );
        $this->showDeleteModal = true;
    }

    public function cancelDeleteRule(): void
    {
        $this->reset(['showDeleteModal', 'deleteRuleId', 'deleteRuleSummary']);
    }

    public function deleteRule(?int $ruleId = null): void
    {
        $ruleId = $ruleId ?? $this->deleteRuleId;

        if ($ruleId === null) {
            return;
        }

        app(DeletePricingRule::class)->handle($ruleId, auth()->id());

        $this->cancelDeleteRule();
    }

    public function resetRuleForm(): void
    {
        $this->reset([
            'editingRuleId',
            'ruleMinPrice',
            'ruleMaxPrice',
            'ruleWholesalePercentage',
            'ruleRetailPercentage',
            'rulePriority',
            'ruleIsActive',
        ]);
        $this->resetValidation();
    }

    /**
     * @return Collection<int, PricingRule>
     */
    public function getRulesProperty(): Collection
    {
        return app(GetPricingRules::class)->handle();
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.pricing_rules'));
    }
};
?>

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    x-data="{
        showForm: false,
        toggleForm() {
            this.showForm = !this.showForm;
            if (!this.showForm) $wire.resetRuleForm();
        },
    }"
    x-on:pricing-rule-saved.window="showForm = false"
    x-on:open-rules-panel.window="showForm = true"
    data-test="pricing-rules-page"
>
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.pricing_rules') }}
                </flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.pricing_rules_intro') }}
                </flux:text>
            </div>
            <flux:button
                type="button"
                variant="primary"
                icon="plus"
                class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                x-on:click="toggleForm()"
            >
                {{ __('messages.new_pricing_rule') }}
            </flux:button>
        </div>
    </section>

    <section
        class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
        x-show="showForm"
        x-cloak
    >
        <form class="grid gap-5" wire:submit.prevent="saveRule">
            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                {{ $editingRuleId ? __('messages.edit_pricing_rule') : __('messages.create_pricing_rule') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <flux:input
                    class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    name="ruleMinPrice"
                    :label="__('messages.pricing_rule_min_price')"
                    type="number"
                    min="0"
                    step="0.01"
                    wire:model.defer="ruleMinPrice"
                />
                @error('ruleMinPrice')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
                <flux:input
                    class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    name="ruleMaxPrice"
                    :label="__('messages.pricing_rule_max_price')"
                    type="number"
                    min="0"
                    step="0.01"
                    wire:model.defer="ruleMaxPrice"
                />
                @error('ruleMaxPrice')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
                <flux:input
                    class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    name="ruleWholesalePercentage"
                    :label="__('messages.pricing_rule_wholesale_pct')"
                    type="number"
                    step="0.01"
                    wire:model.defer="ruleWholesalePercentage"
                />
                @error('ruleWholesalePercentage')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
                <flux:input
                    class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    name="ruleRetailPercentage"
                    :label="__('messages.pricing_rule_retail_pct')"
                    type="number"
                    step="0.01"
                    wire:model.defer="ruleRetailPercentage"
                />
                @error('ruleRetailPercentage')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
                <flux:input
                    class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    name="rulePriority"
                    :label="__('messages.pricing_rule_priority')"
                    type="number"
                    min="0"
                    wire:model.defer="rulePriority"
                />
                @error('rulePriority')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
                <div class="flex items-center gap-3">
                    <flux:label>{{ __('messages.active') }}</flux:label>
                    <flux:switch wire:model.defer="ruleIsActive" />
                </div>
            </div>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" icon="plus" wire:loading.attr="disabled" wire:target="saveRule">
                    {{ $editingRuleId ? __('messages.update') : __('messages.create') }}
                </flux:button>
                <flux:button type="button" variant="ghost" x-on:click="toggleForm()">
                    {{ __('messages.cancel') }}
                </flux:button>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead>
                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                        <th class="px-5 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.pricing_rule_min_price') }}</th>
                        <th class="px-5 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.pricing_rule_max_price') }}</th>
                        <th class="px-5 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.pricing_rule_retail_pct') }}</th>
                        <th class="px-5 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.pricing_rule_wholesale_pct') }}</th>
                        <th class="px-5 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.pricing_rule_priority') }}</th>
                        <th class="px-5 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.status') }}</th>
                        <th class="px-5 py-3 text-end font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rules as $rule)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="rule-{{ $rule->id }}">
                            <td class="px-5 py-3 font-mono text-zinc-900 dark:text-zinc-100">{{ number_format($rule->min_price, 2) }}</td>
                            <td class="px-5 py-3 font-mono text-zinc-900 dark:text-zinc-100">{{ number_format($rule->max_price, 2) }}</td>
                            <td class="px-5 py-3">{{ $rule->retail_percentage }}%</td>
                            <td class="px-5 py-3">{{ $rule->wholesale_percentage }}%</td>
                            <td class="px-5 py-3">{{ $rule->priority }}</td>
                            <td class="px-5 py-3">
                                @if ($rule->is_active)
                                    <flux:badge color="green">{{ __('messages.active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc">{{ __('messages.inactive_status') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-end">
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEditRule({{ $rule->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" color="red" wire:click="confirmDeleteRule({{ $rule->id }})" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('messages.no_pricing_rules_yet') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <flux:modal name="delete-pricing-rule" wire:model="showDeleteModal">
        <div class="p-5">
            <flux:heading size="lg" class="mb-2">{{ __('messages.delete_pricing_rule_title') }}</flux:heading>
            <flux:text class="mb-4">{{ __('messages.delete_pricing_rule_body', ['summary' => $deleteRuleSummary]) }}</flux:text>
            <div class="flex gap-2">
                <flux:button variant="primary" color="red" wire:click="deleteRule()" wire:loading.attr="disabled">
                    {{ __('messages.delete') }}
                </flux:button>
                <flux:button variant="ghost" wire:click="cancelDeleteRule()">
                    {{ __('messages.cancel') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
