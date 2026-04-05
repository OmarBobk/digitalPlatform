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

<div class="admin-customer-funds flex h-full w-full flex-1 flex-col gap-8">
    <header class="cf-reveal relative grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
        <div class="max-w-2xl space-y-3">
            <p class="cf-display text-xs font-semibold tracking-[0.2em] text-[var(--cf-primary)] uppercase">
                {{ __('messages.nav_financials') }}
            </p>
            <flux:heading size="lg" class="cf-display text-3xl tracking-tight text-[var(--cf-foreground)] md:text-4xl">
                {{ __('messages.customer_funds') }}
            </flux:heading>
            <flux:text class="max-w-xl text-sm leading-relaxed text-[var(--cf-muted-foreground)]">
                {{ __('messages.customer_funds_intro') }}
            </flux:text>
        </div>
        <div
            class="hidden h-24 w-full max-w-xs skew-x-[-8deg] rounded-xl border border-[var(--cf-border)] bg-[var(--cf-card-elevated)] opacity-90 lg:block"
            aria-hidden="true"
        >
            <div class="h-full w-full rounded-[inherit] bg-gradient-to-br from-[var(--cf-primary-soft)] to-transparent"></div>
        </div>
    </header>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="cf-reveal cf-reveal-delay-1 cf-stat-card cf-stat-card--primary">
            <div class="flex items-start gap-3">
                <div class="cf-icon-ring shrink-0">
                    <flux:icon icon="banknotes" class="size-5" />
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <flux:text class="text-sm font-medium text-[var(--cf-muted-foreground)]">
                        {{ __('messages.total_customer_liability') }}
                    </flux:text>
                    <flux:text class="cf-display block text-3xl font-bold tracking-tight text-[var(--cf-primary)] tabular-nums" dir="ltr">
                        {{ config('billing.currency_symbol', '$') }}{{ number_format($this->totalLiability, 2) }}
                    </flux:text>
                    <flux:text class="text-xs text-[var(--cf-muted-foreground)]">
                        {{ __('messages.customer_funds_liability_hint') }}
                    </flux:text>
                </div>
            </div>
        </div>
        <div class="cf-reveal cf-reveal-delay-2 cf-stat-card cf-stat-card--secondary">
            <div class="flex items-start gap-3">
                <div class="cf-icon-ring cf-icon-ring--cool shrink-0">
                    <flux:icon icon="users" class="size-5" />
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <flux:text class="text-sm font-medium text-[var(--cf-muted-foreground)]">
                        {{ __('messages.customer_wallets_count') }}
                    </flux:text>
                    <flux:text class="cf-display block text-3xl font-bold tracking-tight text-[var(--cf-foreground)] tabular-nums">
                        {{ number_format($this->customerWalletsCount) }}
                    </flux:text>
                    <flux:text class="text-xs text-[var(--cf-muted-foreground)]">
                        {{ __('messages.customer_wallets_count_hint') }}
                    </flux:text>
                </div>
            </div>
        </div>
    </div>

    <section class="cf-reveal cf-reveal-delay-3 cf-table-shell">
        <div class="cf-table-head px-5 py-4">
            <flux:heading size="sm" class="cf-display text-[var(--cf-foreground)]">
                {{ __('messages.customer_balances_breakdown') }}
            </flux:heading>
        </div>
        <div class="overflow-x-auto">
            @if ($this->customerWallets->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                    <flux:heading size="sm" class="cf-display text-[var(--cf-foreground)]">
                        {{ __('messages.no_customer_wallets') }}
                    </flux:heading>
                    <flux:text class="max-w-sm text-sm text-[var(--cf-muted-foreground)]">
                        {{ __('messages.no_customer_wallets_hint') }}
                    </flux:text>
                </div>
            @else
                <table class="min-w-full divide-y divide-[var(--cf-border)] text-sm">
                    <thead class="text-xs tracking-wide text-[var(--cf-muted-foreground)] uppercase">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('messages.customer') }}</th>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('messages.email') }}</th>
                            <th class="px-5 py-3 text-end font-semibold">{{ __('messages.balance') }}</th>
                            <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--cf-border)]">
                        @foreach ($this->customerWallets as $wallet)
                            @php
                                $balance = (float) $wallet->balance;
                                $balanceBadge =
                                    $balance > 0
                                        ? 'bg-[var(--cf-success-soft)] text-[var(--cf-success)]'
                                        : ($balance < 0
                                            ? 'bg-[var(--cf-destructive-soft)] text-[var(--cf-destructive)]'
                                            : 'bg-[var(--cf-card-elevated)] text-[var(--cf-muted-foreground)]');
                            @endphp
                            <tr
                                class="transition-colors duration-200 hover:bg-[var(--cf-card-elevated)]"
                                wire:key="wallet-{{ $wallet->id }}"
                            >
                                <td class="px-5 py-4">
                                    <div class="font-medium text-[var(--cf-foreground)]">{{ $wallet->user?->name ?? __('messages.unknown') }}</div>
                                    @if ($wallet->user?->username)
                                        <div class="text-xs text-[var(--cf-muted-foreground)]">@{{ $wallet->user->username }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-[var(--cf-muted-foreground)]">
                                    {{ $wallet->user?->email ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-end">
                                    <span
                                        class="inline-flex items-center rounded-lg px-2.5 py-1 font-semibold tabular-nums {{ $balanceBadge }}"
                                        dir="ltr"
                                    >
                                        {{ config('billing.currency_symbol', '$') }}{{ number_format($balance, 2) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-end">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="information-circle"
                                        class="text-[var(--cf-primary)] hover:bg-[var(--cf-primary-soft)] hover:text-[var(--cf-primary)]"
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
            <div class="cf-pagination border-t border-[var(--cf-border)] px-5 py-4">
                {{ $this->customerWallets->links() }}
            </div>
        @endif
    </section>

    <flux:modal
        wire:model.self="showDetailModal"
        variant="floating"
        class="admin-themed-modal w-[calc(100%-2rem)] max-w-2xl p-4 sm:p-6 sm:pt-14"
        @close="closeDetailModal"
        @cancel="closeDetailModal"
    >
        @if ($this->selectedWallet)
            <div class="space-y-4 text-[var(--cf-foreground)]">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <flux:heading size="lg" class="cf-display text-[var(--cf-foreground)]">
                            {{ $this->selectedWallet->user?->name ?? __('messages.unknown') }}
                        </flux:heading>
                        <flux:text class="mt-1 block truncate text-sm text-[var(--cf-muted-foreground)]">
                            {{ $this->selectedWallet->user?->email ?? '—' }}
                        </flux:text>
                    </div>
                    <div
                        class="shrink-0 self-start rounded-xl border border-[var(--cf-border)] bg-[var(--cf-success-soft)] px-4 py-2"
                    >
                        <flux:text class="text-xs font-medium text-[var(--cf-success)]">
                            {{ __('messages.balance') }}
                        </flux:text>
                        <flux:text class="cf-display block text-xl font-bold text-[var(--cf-foreground)] tabular-nums" dir="ltr">
                            {{ config('billing.currency_symbol', '$') }}{{ number_format((float) $this->selectedWallet->balance, 2) }}
                        </flux:text>
                    </div>
                </div>

                <flux:text class="block text-sm text-[var(--cf-muted-foreground)]">
                    {{ __('messages.balance_breakdown_intro') }}
                </flux:text>

                @if ($this->balanceBreakdown->isEmpty())
                    <div class="rounded-xl border border-[var(--cf-border)] bg-[var(--cf-card-elevated)] px-4 py-8 text-center">
                        <flux:text class="text-sm text-[var(--cf-muted-foreground)]">
                            {{ __('messages.no_wallet_transactions') }}
                        </flux:text>
                    </div>
                @else
                    {{-- Mobile: card list --}}
                    <div class="max-h-[50vh] overflow-auto sm:hidden">
                        <div class="flex flex-col gap-3" role="list" aria-label="{{ __('messages.balance_details') }}">
                            @foreach ($this->balanceBreakdown as $row)
                                <article
                                    wire:key="breakdown-mob-{{ $row['type']->value }}"
                                    class="flex flex-col gap-2 rounded-xl border border-[var(--cf-border)] bg-[var(--cf-background)] p-4"
                                    role="listitem"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-[var(--cf-foreground)]">{{ $row['typeLabel'] }}</span>
                                        <span
                                            class="shrink-0 font-semibold tabular-nums {{ $row['net'] >= 0 ? 'text-[var(--cf-success)]' : 'text-[var(--cf-destructive)]' }}"
                                            dir="ltr"
                                        >
                                            {{ $row['net'] >= 0 ? '+' : '-' }}{{ config('billing.currency_symbol', '$') }}{{ number_format(abs($row['net']), 2) }}
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--cf-muted-foreground)]">
                                        @if ($row['credits'] > 0)
                                            <span dir="ltr">{{ __('messages.credits') }}: {{ config('billing.currency_symbol', '$') }}{{ number_format($row['credits'], 2) }}</span>
                                        @endif
                                        @if ($row['debits'] > 0)
                                            <span dir="ltr">{{ __('messages.debits') }}: {{ config('billing.currency_symbol', '$') }}{{ number_format($row['debits'], 2) }}</span>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>

                    {{-- Desktop: table --}}
                    <div class="hidden max-h-[50vh] overflow-auto rounded-xl border border-[var(--cf-border)] sm:block">
                        <table class="min-w-full divide-y divide-[var(--cf-border)] text-sm">
                            <thead class="sticky top-0 z-10 bg-[var(--cf-card)] text-xs tracking-wide text-[var(--cf-muted-foreground)] uppercase">
                                <tr>
                                    <th class="px-4 py-3 text-start font-semibold">{{ __('messages.type') }}</th>
                                    <th class="px-4 py-3 text-end font-semibold">{{ __('messages.credits') }}</th>
                                    <th class="px-4 py-3 text-end font-semibold">{{ __('messages.debits') }}</th>
                                    <th class="px-4 py-3 text-end font-semibold">{{ __('messages.balance') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--cf-border)]">
                                @foreach ($this->balanceBreakdown as $row)
                                    <tr
                                        wire:key="breakdown-{{ $row['type']->value }}"
                                        class="transition-colors duration-200 hover:bg-[var(--cf-card-elevated)]"
                                    >
                                        <td class="px-4 py-3 font-medium text-[var(--cf-foreground)]">
                                            {{ $row['typeLabel'] }}
                                        </td>
                                        <td class="px-4 py-3 text-end tabular-nums text-[var(--cf-success)]" dir="ltr">
                                            {{ $row['credits'] > 0 ? config('billing.currency_symbol', '$').number_format($row['credits'], 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-end tabular-nums text-[var(--cf-destructive)]" dir="ltr">
                                            {{ $row['debits'] > 0 ? config('billing.currency_symbol', '$').number_format($row['debits'], 2) : '—' }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-end font-semibold tabular-nums {{ $row['net'] >= 0 ? 'text-[var(--cf-success)]' : 'text-[var(--cf-destructive)]' }}"
                                            dir="ltr"
                                        >
                                            {{ $row['net'] >= 0 ? '+' : '-' }}{{ config('billing.currency_symbol', '$') }}{{ number_format(abs($row['net']), 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:button
                        variant="ghost"
                        class="text-[var(--cf-muted-foreground)] hover:bg-[var(--cf-card-elevated)] hover:text-[var(--cf-foreground)]"
                        wire:click="closeDetailModal"
                    >
                        {{ __('messages.close') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
