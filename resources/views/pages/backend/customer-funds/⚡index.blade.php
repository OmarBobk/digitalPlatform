<?php

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Enums\WalletType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $perPage = 15;

    public bool $showDetailModal = false;

    public ?int $selectedWalletId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage_topups'), 403);
    }

    public function openDetailModal(int $walletId): void
    {
        $this->selectedWalletId = $walletId;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->reset('showDetailModal', 'selectedWalletId');
    }

    public function getTotalLiabilityProperty(): float
    {
        return (float) Wallet::query()
            ->where('type', WalletType::Customer)
            ->sum('balance');
    }

    public function getCustomerWalletsCountProperty(): int
    {
        return Wallet::query()
            ->where('type', WalletType::Customer)
            ->count();
    }

    /**
     * @return LengthAwarePaginator<Wallet>
     */
    public function getCustomerWalletsProperty(): LengthAwarePaginator
    {
        return Wallet::query()
            ->where('type', WalletType::Customer)
            ->with('user:id,name,email,username')
            ->orderByDesc('balance')
            ->paginate($this->perPage);
    }

    public function getSelectedWalletProperty(): ?Wallet
    {
        if ($this->selectedWalletId === null) {
            return null;
        }

        return Wallet::query()
            ->with('user:id,name,email,username')
            ->find($this->selectedWalletId);
    }

    /**
     * @return Collection<int, array{type: WalletTransactionType, typeLabel: string, credits: float, debits: float, net: float}>
     */
    public function getBalanceBreakdownProperty(): Collection
    {
        $wallet = $this->selectedWallet;

        if ($wallet === null) {
            return collect();
        }

        $posted = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('status', WalletTransaction::STATUS_POSTED)
            ->get();

        $typeLabels = [
            'topup' => __('messages.wallet_transaction_type_topup'),
            'purchase' => __('messages.wallet_transaction_type_purchase'),
            'refund' => __('messages.wallet_transaction_type_refund'),
            'adjustment' => __('messages.wallet_transaction_type_adjustment'),
            'settlement' => __('messages.wallet_transaction_type_settlement'),
        ];

        $byType = [];
        foreach (WalletTransactionType::cases() as $type) {
            $byType[$type->value] = ['credits' => 0.0, 'debits' => 0.0];
        }

        foreach ($posted as $tx) {
            $amount = (float) $tx->amount;
            if ($tx->direction === WalletTransactionDirection::Credit) {
                $byType[$tx->type->value]['credits'] += $amount;
            } else {
                $byType[$tx->type->value]['debits'] += $amount;
            }
        }

        return collect($byType)
            ->map(fn (array $data, string $typeValue) => [
                'type' => WalletTransactionType::from($typeValue),
                'typeLabel' => $typeLabels[$typeValue] ?? $typeValue,
                'credits' => round($data['credits'], 2),
                'debits' => round($data['debits'], 2),
                'net' => round($data['credits'] - $data['debits'], 2),
            ])
            ->filter(fn (array $row) => $row['credits'] !== 0.0 || $row['debits'] !== 0.0)
            ->values();
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.customer_funds'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="space-y-1">
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                {{ __('messages.customer_funds') }}
            </flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('messages.customer_funds_intro') }}
            </flux:text>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/60 dark:bg-amber-950/30">
                <div class="flex items-center gap-2">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/50">
                        <flux:icon icon="banknotes" class="size-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <flux:text class="text-sm font-medium text-amber-800 dark:text-amber-200">
                            {{ __('messages.total_customer_liability') }}
                        </flux:text>
                        <flux:text class="mt-0.5 block text-2xl font-bold text-amber-900 dark:text-amber-100">
                            {{ config('billing.currency_symbol', '$') }}{{ number_format($this->totalLiability, 2) }}
                        </flux:text>
                        <flux:text class="text-xs text-amber-700/80 dark:text-amber-300/80">
                            {{ __('messages.customer_funds_liability_hint') }}
                        </flux:text>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-800/60 dark:bg-sky-950/30">
                <div class="flex items-center gap-2">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/50">
                        <flux:icon icon="users" class="size-5 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <flux:text class="text-sm font-medium text-sky-800 dark:text-sky-200">
                            {{ __('messages.customer_wallets_count') }}
                        </flux:text>
                        <flux:text class="mt-0.5 block text-2xl font-bold text-sky-900 dark:text-sky-100">
                            {{ number_format($this->customerWalletsCount) }}
                        </flux:text>
                        <flux:text class="text-xs text-sky-700/80 dark:text-sky-300/80">
                            {{ __('messages.customer_wallets_count_hint') }}
                        </flux:text>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.customer_balances_breakdown') }}
                </flux:heading>
            </div>
            <div class="overflow-x-auto">
                @if ($this->customerWallets->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_customer_wallets') }}
                        </flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_customer_wallets_hint') }}
                        </flux:text>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.customer') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.email') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.balance') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->customerWallets as $wallet)
                                @php
                                    $balance = (float) $wallet->balance;
                                    $balanceColor = $balance > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($balance < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-zinc-400');
                                @endphp
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:key="wallet-{{ $wallet->id }}">
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $wallet->user?->name ?? __('messages.unknown') }}</div>
                                        @if ($wallet->user?->username)
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">@{{ $wallet->user->username }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $wallet->user?->email ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <span class="inline-flex items-center rounded-lg px-2.5 py-1 font-semibold tabular-nums {{ $balance > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : ($balance < 0 ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400') }}" dir="ltr">
                                            {{ config('billing.currency_symbol', '$') }}{{ number_format($balance, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="information-circle"
                                            wire:click="openDetailModal({{ $wallet->id }})"
                                            aria-label="{{ __('messages.view_balance_details') }}"
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

            @if ($this->customerWallets->isNotEmpty())
                <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    {{ $this->customerWallets->links() }}
                </div>
            @endif
        </div>
    </section>

    <flux:modal
        wire:model.self="showDetailModal"
        variant="floating"
        class="max-w-2xl pt-14"
        @close="closeDetailModal"
        @cancel="closeDetailModal"
    >
        @if ($this->selectedWallet)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            {{ $this->selectedWallet->user?->name ?? __('messages.unknown') }}
                        </flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $this->selectedWallet->user?->email ?? '—' }}
                        </flux:text>
                    </div>
                    <div class="shrink-0 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 dark:border-emerald-800/60 dark:bg-emerald-950/30">
                        <flux:text class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                            {{ __('messages.balance') }}
                        </flux:text>
                        <flux:text class="block text-xl font-bold text-emerald-800 dark:text-emerald-200" dir="ltr">
                            {{ config('billing.currency_symbol', '$') }}{{ number_format((float) $this->selectedWallet->balance, 2) }}
                        </flux:text>
                    </div>
                </div>

                <flux:text class="block text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.balance_breakdown_intro') }}
                </flux:text>

                @if ($this->balanceBreakdown->isEmpty())
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-8 text-center dark:border-zinc-700 dark:bg-zinc-800/60">
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('messages.no_wallet_transactions') }}
                        </flux:text>
                    </div>
                @else
                    <div class="max-h-[50vh] overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            <thead class="sticky top-0 z-10 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                                <tr>
                                    <th class="px-4 py-3 text-start font-semibold">{{ __('messages.type') }}</th>
                                    <th class="px-4 py-3 text-end font-semibold">{{ __('messages.credits') }}</th>
                                    <th class="px-4 py-3 text-end font-semibold">{{ __('messages.debits') }}</th>
                                    <th class="px-4 py-3 text-end font-semibold">{{ __('messages.balance') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($this->balanceBreakdown as $row)
                                    <tr wire:key="breakdown-{{ $row['type']->value }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $row['typeLabel'] }}
                                        </td>
                                        <td class="px-4 py-3 text-end tabular-nums text-emerald-600 dark:text-emerald-400" dir="ltr">
                                            {{ $row['credits'] > 0 ? config('billing.currency_symbol', '$').number_format($row['credits'], 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-end tabular-nums text-red-600 dark:text-red-400" dir="ltr">
                                            {{ $row['debits'] > 0 ? config('billing.currency_symbol', '$').number_format($row['debits'], 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-end font-semibold tabular-nums {{ $row['net'] >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}" dir="ltr">
                                            {{ $row['net'] >= 0 ? '+' : '-' }}{{ config('billing.currency_symbol', '$') }}{{ number_format(abs($row['net']), 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="closeDetailModal">
                        {{ __('messages.close') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
