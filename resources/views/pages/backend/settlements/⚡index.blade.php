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

        @if ($this->platformWallet)
            <div class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
                <div class="flex items-center gap-2">
                    <flux:text class="font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('messages.platform_wallet_balance') }}:
                    </flux:text>
                    <flux:text class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                        {{ number_format((float) $this->platformWallet->balance, 2) }} {{ $this->platformWallet->currency }}
                    </flux:text>
                </div>
            </div>
        @endif

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

        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
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
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.settlement') }} #</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.amount') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.fulfillments') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.created') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->settlements as $settlement)
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:key="settlement-{{ $settlement->id }}">
                                    <td class="px-5 py-4 font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $settlement->id }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-700 dark:text-zinc-200">
                                        {{ number_format((float) $settlement->total_amount, 2) }} {{ config('billing.currency', 'USD') }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $settlement->fulfillments_count }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $settlement->created_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="openDetailModal({{ $settlement->id }})"
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
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.settlement_details') }} #{{ $this->selectedSettlement->id }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.settlement_details_intro', ['total' => number_format((float) $this->selectedSettlement->total_amount, 2), 'count' => $this->selectedSettlement->fulfillments->count()]) }}
                </flux:text>

                <div class="max-h-[60vh] overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                        <thead class="sticky top-0 z-10 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
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
                                    <td class="px-4 py-3 text-end text-zinc-600 dark:text-zinc-300">
                                        {{ number_format($unitPrice, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-end text-zinc-600 dark:text-zinc-300">
                                        {{ number_format($entryPrice, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-end font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($profit, 2) }}
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
