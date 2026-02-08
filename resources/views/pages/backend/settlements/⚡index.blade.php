<?php

use App\Models\Settlement;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $perPage = 15;

    public ?string $noticeMessage = null;
    public ?string $noticeVariant = null;
    public bool $isSettling = false;

    public bool $showDetailModal = false;
    public ?int $selectedSettlementId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage_settlements'), 403);
    }

    public function runSettlement(): void
    {
        $this->reset('noticeMessage', 'noticeVariant');
        $this->isSettling = true;

        try {
            Artisan::call('profit:settle');
            $output = trim(Artisan::output());

            if ($output !== '') {
                $this->noticeVariant = 'success';
                $this->noticeMessage = $output;
            } else {
                $this->noticeVariant = 'success';
                $this->noticeMessage = __('messages.settlement_completed');
            }
        } catch (\Throwable $e) {
            $this->noticeVariant = 'danger';
            $this->noticeMessage = $e->getMessage();
        } finally {
            $this->isSettling = false;
        }
    }

    public function openDetailModal(int $settlementId): void
    {
        $this->selectedSettlementId = $settlementId;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->reset('showDetailModal', 'selectedSettlementId');
    }

    public function getPlatformWalletProperty(): ?Wallet
    {
        try {
            return Wallet::forPlatform();
        } catch (\Throwable) {
            return null;
        }
    }

    public function getSettlementsCountProperty(): int
    {
        return Settlement::query()->count();
    }

    public function getSettlementsProperty(): LengthAwarePaginator
    {
        return Settlement::query()
            ->withCount('fulfillments')
            ->latest()
            ->paginate($this->perPage);
    }

    public function getSelectedSettlementProperty(): ?Settlement
    {
        if ($this->selectedSettlementId === null) {
            return null;
        }

        return Settlement::query()
            ->with(['fulfillments.orderItem.order'])
            ->find($this->selectedSettlementId);
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.settlements'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.settlements') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.settlements_intro') }}
                </flux:text>
            </div>
            <flux:button
                variant="primary"
                wire:click="runSettlement"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="runSettlement">{{ __('messages.settle_now') }}</span>
                <span wire:loading wire:target="runSettlement">{{ __('messages.settling') }}</span>
            </flux:button>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @if ($this->platformWallet)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800/60 dark:bg-emerald-950/30">
                    <div class="flex items-center gap-2">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/50">
                            <flux:icon icon="banknotes" class="size-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <flux:text class="text-sm font-medium text-emerald-800 dark:text-emerald-200">
                                {{ __('messages.platform_wallet_balance') }}
                            </flux:text>
                            <flux:text class="mt-0.5 block text-2xl font-bold text-emerald-900 dark:text-emerald-100">
                                {{ config('billing.currency_symbol', '$') }}{{ number_format((float) $this->platformWallet->balance, 2) }}
                            </flux:text>
                            <flux:text class="text-xs text-emerald-700/80 dark:text-emerald-300/80">
                                {{ __('messages.platform_wallet_balance_hint') }}
                            </flux:text>
                        </div>
                    </div>
                </div>
            @endif
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-800/60 dark:bg-sky-950/30">
                <div class="flex items-center gap-2">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/50">
                        <flux:icon icon="circle-stack" class="size-5 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <flux:text class="text-sm font-medium text-sky-800 dark:text-sky-200">
                            {{ __('messages.settlements_count') }}
                        </flux:text>
                        <flux:text class="mt-0.5 block text-2xl font-bold text-sky-900 dark:text-sky-100">
                            {{ number_format($this->settlementsCount) }}
                        </flux:text>
                        <flux:text class="text-xs text-sky-700/80 dark:text-sky-300/80">
                            {{ __('messages.settlements_count_hint') }}
                        </flux:text>
                    </div>
                </div>
            </div>
        </div>

        @if ($noticeMessage)
            <div class="mt-4">
                <flux:callout
                    variant="subtle"
                    icon="{{ $noticeVariant === 'success' ? 'check-circle' : 'exclamation-triangle' }}"
                >
                    {{ $noticeMessage }}
                </flux:callout>
            </div>
        @endif

        <div class="mt-6 overflow-hidden rounded-2xl border border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.settlements') }}
                </flux:heading>
            </div>
            <div class="overflow-x-auto">
                @if ($this->settlements->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_settlements_yet') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_settlements_hint') }}
                        </flux:text>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800" data-test="settlements-table">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.settlement') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.amount') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.created') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->settlements as $settlement)
                                @php
                                    $amount = (float) $settlement->total_amount;
                                    $amountBadgeClass = $amount > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400';
                                @endphp
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:key="settlement-{{ $settlement->id }}">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">#{{ $settlement->id }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $settlement->fulfillments_count }} {{ __('messages.fulfillments') }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center rounded-lg px-2.5 py-1 font-semibold tabular-nums {{ $amountBadgeClass }}" dir="ltr">
                                            {{ config('billing.currency_symbol', '$') }}{{ number_format($amount, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $settlement->created_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="information-circle"
                                            wire:click="openDetailModal({{ $settlement->id }})"
                                            aria-label="{{ __('messages.settlement_details') }}"
                                        >
                                            {{ __('messages.view') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($this->settlements->isNotEmpty())
                <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    {{ $this->settlements->links() }}
                </div>
            @endif
        </div>
    </section>

    <flux:modal
        wire:model.self="showDetailModal"
        variant="floating"
        class="max-w-4xl pt-14"
        @close="closeDetailModal"
        @cancel="closeDetailModal"
    >
        @if ($this->selectedSettlement)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.settlement_details') }} #{{ $this->selectedSettlement->id }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $this->selectedSettlement->fulfillments->count() }} {{ __('messages.fulfillments') }} · {{ __('messages.profit') }}
                        </flux:text>
                    </div>
                    <div class="shrink-0 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 dark:border-emerald-800/60 dark:bg-emerald-950/30">
                        <flux:text class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                            {{ __('messages.amount') }}
                        </flux:text>
                        <flux:text class="block text-xl font-bold text-emerald-800 dark:text-emerald-200" dir="ltr">
                            {{ config('billing.currency_symbol', '$') }}{{ number_format((float) $this->selectedSettlement->total_amount, 2) }}
                        </flux:text>
                    </div>
                </div>

                <div class="max-h-[50vh] overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                        <thead class="sticky top-0 z-10 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold">{{ __('messages.order_number') }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __('messages.product') }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __('messages.unit_price') }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __('messages.entry_price') }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __('messages.profit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->selectedSettlement->fulfillments as $fulfillment)
                                @php
                                    $item = $fulfillment->orderItem;
                                    $unitPrice = (float) ($item?->unit_price ?? 0);
                                    $entryPrice = (float) ($item?->entry_price ?? 0);
                                    $profit = max(0, round($unitPrice - $entryPrice, 2));
                                @endphp
                                <tr wire:key="detail-fulfillment-{{ $fulfillment->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $item?->order?->order_number ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-zinc-700 dark:text-zinc-200">
                                        {{ $item?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-300" dir="ltr">
                                        {{ config('billing.currency_symbol', '$') }}{{ number_format($unitPrice, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-300" dir="ltr">
                                        {{ config('billing.currency_symbol', '$') }}{{ number_format($entryPrice, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <span class="inline-flex rounded-md px-2 py-0.5 font-semibold tabular-nums text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/40" dir="ltr">
                                            {{ config('billing.currency_symbol', '$') }}{{ number_format($profit, 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="closeDetailModal">
                        {{ __('messages.close') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
