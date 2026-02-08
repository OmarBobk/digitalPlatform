<?php

use App\Actions\Refunds\ApproveRefundRequest;
use App\Actions\Refunds\GetRefundRequests;
use App\Actions\Refunds\RejectRefundRequest;
use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $perPage = 10;

    public ?string $noticeMessage = null;
    public ?string $noticeVariant = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view_refunds'), 403);
    }

    public function approveRefund(int $transactionId): void
    {
        abort_unless(auth()->user()?->can('process_refunds'), 403);
        $this->reset('noticeMessage', 'noticeVariant');

        app(ApproveRefundRequest::class)->handle($transactionId, auth()->id());

        $this->noticeVariant = 'success';
        $this->noticeMessage = __('messages.refund_approved');
    }

    public function rejectRefund(int $transactionId): void
    {
        abort_unless(auth()->user()?->can('process_refunds'), 403);
        $this->reset('noticeMessage', 'noticeVariant');

        app(RejectRefundRequest::class)->handle($transactionId, auth()->id());

        $this->noticeVariant = 'danger';
        $this->noticeMessage = __('messages.refund_rejected');
    }

    public function getRefundRequestsProperty(): LengthAwarePaginator
    {
        return app(GetRefundRequests::class)->handle($this->perPage);
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.refund_requests'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.refund_requests') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.refund_requests_intro') }}
                </flux:text>
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

        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                @if ($this->refundRequests->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_refund_requests') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_refund_requests_hint') }}
                        </flux:text>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800" data-test="refunds-table">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.created') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.user') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.order_details') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.amount') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.failure_reason') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.note') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.status') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->refundRequests as $transaction)
                                @php
                                    $fulfillment = $transaction->reference instanceof Fulfillment ? $transaction->reference : null;
                                    $orderItem = $fulfillment?->orderItem
                                        ?? ($transaction->reference instanceof OrderItem ? $transaction->reference : null);
                                    $order = $fulfillment?->order ?? $orderItem?->order;
                                    $user = $order?->user;
                                    $metaFulfillmentId = (int) data_get($transaction->meta, 'fulfillment_id', 0);
                                    $legacyFulfillment = $metaFulfillmentId > 0
                                        ? $orderItem?->fulfillments?->firstWhere('id', $metaFulfillmentId)
                                        : $orderItem?->fulfillments?->first();
                                    $displayFulfillment = $fulfillment ?? $legacyFulfillment;
                                    $note = data_get($transaction->meta, 'note');
                                    $isRefunded = $order?->status === OrderStatus::Refunded;
                                    $canApprove = $transaction->status === WalletTransaction::STATUS_PENDING && ! $isRefunded;
                                    $statusColor = match ($transaction->status) {
                                        WalletTransaction::STATUS_POSTED => 'green',
                                        WalletTransaction::STATUS_REJECTED => 'red',
                                        default => 'amber',
                                    };
                                @endphp
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:key="refund-{{ $transaction->id }}">
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $transaction->created_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $user?->name ?? __('messages.unknown_user') }}
                                        </div>
                                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $user?->email ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $order?->order_number ?? __('messages.no_details') }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            #{{ $orderItem?->id ?? '—' }} / #{{ $displayFulfillment?->id ?? data_get($transaction->meta, 'fulfillment_id', '—') }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-700 dark:text-zinc-200" dir="ltr">
                                        {{ config('billing.currency_symbol', '$') }}{{ number_format((float) $transaction->amount, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $displayFulfillment?->last_error ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $note ?: '—' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <flux:badge color="{{ $statusColor }}">
                                            {{ __('messages.'.$transaction->status) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        @can('process_refunds')
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button
                                                size="sm"
                                                variant="primary"
                                                wire:click="approveRefund({{ $transaction->id }})"
                                                :disabled="! $canApprove"
                                            >
                                                {{ __('messages.approve') }}
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="rejectRefund({{ $transaction->id }})"
                                            >
                                                {{ __('messages.reject') }}
                                            </flux:button>
                                        </div>
                                        @else
                                        <span class="text-zinc-500 dark:text-zinc-400">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                {{ $this->refundRequests->links() }}
            </div>
        </div>
    </section>
</div>
