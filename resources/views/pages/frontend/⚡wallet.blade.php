<?php

use App\Enums\OrderStatus;
use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\TopupRequest;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::frontend')] class extends Component {

    public ?string $topupAmount = null;
    public string $topupMethod = TopupMethod::ShamCash->value;

    public ?string $noticeMessage = null;
    public ?string $noticeVariant = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'topupAmount' => ['required', 'numeric', 'min:0.01'],
            'topupMethod' => ['required', Rule::in(TopupMethod::values())],
        ];
    }

    public function submitTopup(): void
    {
        $this->reset('noticeMessage', 'noticeVariant');

        $validated = $this->validate();
        $user = auth()->user();
        $wallet = Wallet::forUser($user);

        TopupRequest::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'method' => TopupMethod::from($validated['topupMethod']),
            'amount' => $validated['topupAmount'],
            'currency' => $wallet->currency,
            'status' => TopupRequestStatus::Pending,
        ]);

        $this->reset('topupAmount');
        $this->topupMethod = TopupMethod::ShamCash->value;

        $this->noticeVariant = 'success';
        $this->noticeMessage = __('messages.topup_request_created');
    }

    public function getWalletProperty(): Wallet
    {
        return Wallet::forUser(auth()->user());
    }

    /**
     * @return Collection<int, WalletTransaction>
     */
    public function getWalletTransactionsProperty(): Collection
    {
        return WalletTransaction::query()
            ->where('wallet_id', $this->wallet->id)
            ->with('reference')
            ->latest('created_at')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, TopupRequest>
     */
    public function getTopupRequestsProperty(): Collection
    {
        return TopupRequest::query()
            ->where('user_id', auth()->id())
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    public function getRecentOrdersProperty(): Collection
    {
        return Order::query()
            ->where('user_id', auth()->id())
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function getTopupMethodOptionsProperty(): array
    {
        return [
            TopupMethod::ShamCash->value => __('messages.topup_method_sham_cash'),
            TopupMethod::EftTransfer->value => __('messages.topup_method_eft_transfer'),
        ];
    }

    protected function orderStatusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PendingPayment => __('messages.order_status_pending_payment'),
            OrderStatus::Paid => __('messages.order_status_paid'),
            OrderStatus::Processing => __('messages.order_status_processing'),
            OrderStatus::Fulfilled => __('messages.order_status_fulfilled'),
            OrderStatus::Failed => __('messages.order_status_failed'),
            OrderStatus::Refunded => __('messages.order_status_refunded'),
            OrderStatus::Cancelled => __('messages.order_status_cancelled'),
        };
    }

    protected function orderStatusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Fulfilled => 'green',
            OrderStatus::Failed, OrderStatus::Cancelled => 'red',
            OrderStatus::Refunded => 'gray',
            OrderStatus::Paid => 'blue',
            default => 'amber',
        };
    }

    /**
     * @return array{label: string, url: ?string}
     */
    protected function transactionDetails(WalletTransaction $transaction): array
    {
        if ($transaction->reference_type === Order::class) {
            $orderNumber = data_get($transaction->meta, 'order_number');

            if ($orderNumber === null && $transaction->reference instanceof Order) {
                $orderNumber = $transaction->reference->order_number;
            }

            if ($orderNumber !== null) {
                return [
                    'label' => __('messages.order_number').': '.$orderNumber,
                    'url' => route('orders.show', $orderNumber),
                ];
            }

            return [
                'label' => __('messages.order').' #'.$transaction->reference_id,
                'url' => null,
            ];
        }

        if ($transaction->type === WalletTransactionType::Refund) {
            $orderNumber = data_get($transaction->meta, 'order_number');

            if ($orderNumber !== null) {
                return [
                    'label' => __('messages.order_number').': '.$orderNumber,
                    'url' => route('orders.show', $orderNumber),
                ];
            }

            return [
                'label' => __('messages.order').' #'.data_get($transaction->meta, 'order_id', $transaction->reference_id),
                'url' => null,
            ];
        }

        if ($transaction->reference_type === TopupRequest::class) {
            $methodValue = data_get($transaction->meta, 'method');
            $method = $methodValue ? TopupMethod::tryFrom($methodValue) : null;

            if ($method === null && $transaction->reference instanceof TopupRequest) {
                $method = $transaction->reference->method;
            }

            $label = $method
                ? __('messages.topup_method_'.$method->value)
                : __('messages.topup_request');

            return [
                'label' => __('messages.topup_request').': '.$label,
                'url' => null,
            ];
        }

        return [
            'label' => __('messages.no_details'),
            'url' => null,
        ];
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.wallet'));
    }
};
?>

<div class="mx-auto w-full max-w-7xl px-3 py-6 sm:px-0 sm:py-10">
    <div class="mb-4 flex items-center">
        <x-back-button />
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.wallet_balance') }}
                    </flux:heading>
                </div>
                <div class="mt-4 text-3xl font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                    {{ $this->wallet->balance }} {{ $this->wallet->currency }}
                </div>
                <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('messages.wallet_balance_hint') }}
                </flux:text>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.wallet_transactions') }}
                    </flux:heading>
                </div>

                <div class="mt-4">
                    @if ($this->walletTransactions->isEmpty())
                        <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-zinc-100 px-6 py-12 text-center dark:border-zinc-800">
                            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                                {{ __('messages.no_wallet_transactions') }}
                            </flux:heading>
                            <flux:text class="text-zinc-600 dark:text-zinc-400">
                                {{ __('messages.no_wallet_transactions_hint') }}
                            </flux:text>
                        </div>
                    @else
                        <div class="grid gap-3 sm:hidden">
                            @foreach ($this->walletTransactions as $transaction)
                                @php
                                    $typeLabel = match ($transaction->type) {
                                        WalletTransactionType::Topup => __('messages.wallet_transaction_type_topup'),
                                        WalletTransactionType::Purchase => __('messages.wallet_transaction_type_purchase'),
                                        WalletTransactionType::Refund => __('messages.wallet_transaction_type_refund'),
                                        WalletTransactionType::Adjustment => __('messages.wallet_transaction_type_adjustment'),
                                    };
                                    $directionLabel = $transaction->direction === WalletTransactionDirection::Credit
                                        ? __('messages.credit')
                                        : __('messages.debit');
                                    $directionColor = $transaction->direction === WalletTransactionDirection::Credit ? 'green' : 'red';
                                    $details = $this->transactionDetails($transaction);
                                    $note = data_get($transaction->meta, 'note');
                                    $statusColor = match ($transaction->status) {
                                        WalletTransaction::STATUS_POSTED => 'green',
                                        WalletTransaction::STATUS_REJECTED => 'red',
                                        default => 'amber',
                                    };
                                @endphp
                                <div class="grid gap-2 rounded-xl border border-zinc-100 bg-white p-4 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $typeLabel }}
                                        </div>
                                        <flux:badge color="{{ $statusColor }}">
                                            {{ __('messages.'.$transaction->status) }}
                                        </flux:badge>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:badge color="{{ $directionColor }}">{{ $directionLabel }}</flux:badge>
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                                            {{ $transaction->amount }} {{ $this->wallet->currency }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        @if ($details['url'])
                                            <a
                                                href="{{ $details['url'] }}"
                                                wire:navigate
                                                class="font-semibold text-zinc-900 hover:underline dark:text-zinc-100"
                                            >
                                                {{ $details['label'] }}
                                            </a>
                                        @else
                                            <span>{{ $details['label'] }}</span>
                                        @endif
                                    </div>
                                    @if ($note)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $note }}
                                        </div>
                                    @endif
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $transaction->created_at?->format('M d, Y') ?? '—' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="hidden overflow-hidden rounded-xl border border-zinc-100 dark:border-zinc-800 sm:block">
                            <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                                    <tr>
                                        <th class="px-5 py-3 text-start font-semibold">{{ __('messages.type') }}</th>
                                        <th class="px-5 py-3 text-start font-semibold">{{ __('messages.direction') }}</th>
                                        <th class="px-5 py-3 text-start font-semibold">{{ __('messages.amount') }}</th>
                                        <th class="px-5 py-3 text-start font-semibold">{{ __('messages.status') }}</th>
                                        <th class="px-5 py-3 text-start font-semibold">{{ __('messages.details') }}</th>
                                        <th class="px-5 py-3 text-start font-semibold">{{ __('messages.note') }}</th>
                                        <th class="px-5 py-3 text-start font-semibold">{{ __('messages.created') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($this->walletTransactions as $transaction)
                                        @php
                                            $typeLabel = match ($transaction->type) {
                                                WalletTransactionType::Topup => __('messages.wallet_transaction_type_topup'),
                                                WalletTransactionType::Purchase => __('messages.wallet_transaction_type_purchase'),
                                                WalletTransactionType::Refund => __('messages.wallet_transaction_type_refund'),
                                                WalletTransactionType::Adjustment => __('messages.wallet_transaction_type_adjustment'),
                                            };
                                            $directionLabel = $transaction->direction === WalletTransactionDirection::Credit
                                                ? __('messages.credit')
                                                : __('messages.debit');
                                            $directionColor = $transaction->direction === WalletTransactionDirection::Credit ? 'green' : 'red';
                                            $details = $this->transactionDetails($transaction);
                                            $note = data_get($transaction->meta, 'note');
                                            $statusColor = match ($transaction->status) {
                                                WalletTransaction::STATUS_POSTED => 'green',
                                                WalletTransaction::STATUS_REJECTED => 'red',
                                                default => 'amber',
                                            };
                                        @endphp
                                        <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                            <td class="px-5 py-4 text-zinc-700 dark:text-zinc-200">
                                                {{ $typeLabel }}
                                            </td>
                                            <td class="px-5 py-4">
                                                <flux:badge color="{{ $directionColor }}">{{ $directionLabel }}</flux:badge>
                                            </td>
                                            <td class="px-5 py-4 text-zinc-700 dark:text-zinc-200" dir="ltr">
                                                {{ $transaction->amount }} {{ $this->wallet->currency }}
                                            </td>
                                            <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                                <flux:badge color="{{ $statusColor }}">
                                                    {{ __('messages.'.$transaction->status) }}
                                                </flux:badge>
                                            </td>
                                            <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                                @if ($details['url'])
                                                    <a
                                                        href="{{ $details['url'] }}"
                                                        wire:navigate
                                                        class="font-semibold text-zinc-900 hover:underline dark:text-zinc-100"
                                                    >
                                                        {{ $details['label'] }}
                                                    </a>
                                                @else
                                                    <span>{{ $details['label'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                                {{ $note ?: '—' }}
                                            </td>
                                            <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                                {{ $transaction->created_at?->format('M d, Y') ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.orders') }}
                    </flux:heading>
                </div>

                <div class="mt-4 space-y-3">
                    @if ($this->recentOrders->isEmpty())
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_orders_yet') }}
                        </flux:text>
                    @else
                        @foreach ($this->recentOrders as $order)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/60">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $order->order_number }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $order->created_at?->format('M d, Y') ?? '—' }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400" dir="ltr">
                                        {{ $order->total }} {{ $order->currency }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:badge color="{{ $this->orderStatusColor($order->status) }}">
                                        {{ $this->orderStatusLabel($order->status) }}
                                    </flux:badge>
                                    <flux:button
                                        as="a"
                                        href="{{ route('orders.show', $order->order_number) }}"
                                        wire:navigate
                                        variant="ghost"
                                        size="sm"
                                    >
                                        {{ __('messages.view') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.request_topup') }}
                </flux:heading>

                @if ($noticeMessage)
                    <div class="mt-4">
                        <flux:callout variant="subtle" icon="check-circle">
                            {{ $noticeMessage }}
                        </flux:callout>
                    </div>
                @endif

                <form class="mt-4 space-y-4" wire:submit.prevent="submitTopup">
                    <div class="grid gap-2">
                        <flux:input
                            name="topupAmount"
                            label="{{ __('messages.amount') }}"
                            wire:model.defer="topupAmount"
                            placeholder="0.00"
                        />
                        @error('topupAmount')
                            <flux:text class="text-xs text-red-600">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="grid gap-2">
                        <flux:select
                            name="topupMethod"
                            label="{{ __('messages.method') }}"
                            wire:model.defer="topupMethod"
                        >
                            @foreach ($this->topupMethodOptions as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('topupMethod')
                            <flux:text class="text-xs text-red-600">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <flux:button
                        type="submit"
                        variant="primary"
                        class="w-full justify-center !bg-accent !text-accent-foreground hover:!bg-accent-hover"
                        wire:loading.attr="disabled"
                        wire:target="submitTopup"
                    >
                        {{ __('messages.submit_topup') }}
                    </flux:button>
                </form>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.topup_requests') }}
                </flux:heading>

                <div class="mt-4 space-y-3">
                    @if ($this->topupRequests->isEmpty())
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_topups_yet') }}
                        </flux:text>
                    @else
                        @foreach ($this->topupRequests as $topupRequest)
                            @php
                                $statusColor = match ($topupRequest->status) {
                                    TopupRequestStatus::Approved => 'green',
                                    TopupRequestStatus::Rejected => 'red',
                                    default => 'amber',
                                };
                            @endphp
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/60">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $topupRequest->amount }} {{ $topupRequest->currency }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $topupRequest->created_at?->format('M d, Y') ?? '—' }}
                                    </div>
                                </div>
                                <flux:badge color="{{ $statusColor }}">
                                    {{ __('messages.'.$topupRequest->status->value) }}
                                </flux:badge>
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>
        </aside>
    </div>
</div>
