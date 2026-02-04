<?php

use App\Actions\Fulfillments\CompleteFulfillment;
use App\Actions\Fulfillments\FailFulfillment;
use App\Actions\Fulfillments\GetFulfillments;
use App\Actions\Fulfillments\RetryFulfillment;
use App\Actions\Fulfillments\StartFulfillment;
use App\Actions\Orders\RefundOrderItem;
use App\Actions\Refunds\ApproveRefundRequest;
use App\Enums\FulfillmentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 10;

    public ?int $selectedFulfillmentId = null;
    public bool $showDetailsModal = false;
    public bool $showCompleteModal = false;
    public bool $showFailModal = false;

    public ?string $deliveredPayloadInput = null;
    public ?string $failureReason = null;
    public bool $refundAfterFail = false;
    public bool $autoDonePayload = false;

    public ?string $noticeMessage = null;
    public ?string $noticeVariant = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'perPage']);
        $this->resetPage();
    }

    public function openDetails(int $fulfillmentId): void
    {
        $this->selectedFulfillmentId = $fulfillmentId;
        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->reset(['showDetailsModal', 'selectedFulfillmentId']);
    }

    public function markProcessing(int $fulfillmentId): void
    {
        $this->reset('noticeMessage', 'noticeVariant');

        $fulfillment = Fulfillment::query()->findOrFail($fulfillmentId);
        app(StartFulfillment::class)->handle($fulfillment, 'admin', auth()->id(), ['source' => 'admin']);

        $this->noticeVariant = 'success';
        $this->noticeMessage = __('messages.fulfillment_marked_processing');
    }

    public function openCompleteModal(int $fulfillmentId): void
    {
        $this->reset('deliveredPayloadInput', 'autoDonePayload');
        $this->selectedFulfillmentId = $fulfillmentId;
        $this->showCompleteModal = true;
    }

    public function completeFulfillment(): void
    {
        $fulfillment = $this->selectedFulfillment;

        if ($fulfillment === null) {
            return;
        }

        if ($this->autoDonePayload && blank($this->deliveredPayloadInput)) {
            $this->deliveredPayloadInput = 'DONE';
        }

        app(CompleteFulfillment::class)->handle(
            $fulfillment,
            $this->parseDeliveredPayload(),
            'admin',
            auth()->id()
        );

        $this->reset('showCompleteModal', 'deliveredPayloadInput', 'autoDonePayload');
        $this->noticeVariant = 'success';
        $this->noticeMessage = __('messages.fulfillment_marked_completed');
    }

    public function updatedAutoDonePayload(bool $value): void
    {
        if ($value) {
            $this->deliveredPayloadInput = 'DONE';
            return;
        }

        if (trim((string) $this->deliveredPayloadInput) === 'DONE') {
            $this->deliveredPayloadInput = null;
        }
    }

    public function openFailModal(int $fulfillmentId): void
    {
        $this->reset('failureReason', 'refundAfterFail');
        $this->selectedFulfillmentId = $fulfillmentId;
        $this->showFailModal = true;
    }

    public function failFulfillment(): void
    {
        $this->validate([
            'failureReason' => ['required', 'string', 'max:500'],
        ]);

        $fulfillment = $this->selectedFulfillment;

        if ($fulfillment === null) {
            return;
        }

        app(FailFulfillment::class)->handle($fulfillment, $this->failureReason ?? '', 'admin', auth()->id());

        $refunded = false;

        if ($this->refundAfterFail) {
            $fulfillment->loadMissing('orderItem');

            if ($fulfillment->orderItem) {
                $transaction = app(RefundOrderItem::class)->handle($fulfillment, auth()->id());
                app(ApproveRefundRequest::class)->handle($transaction->id, auth()->id());
                $refunded = true;
            }
        }

        $this->reset('showFailModal', 'failureReason', 'refundAfterFail');
        $this->noticeVariant = $refunded ? 'success' : 'danger';
        $this->noticeMessage = $refunded
            ? __('messages.fulfillment_failed_refunded')
            : __('messages.fulfillment_marked_failed');
    }

    public function retryFulfillment(int $fulfillmentId): void
    {
        $this->reset('noticeMessage', 'noticeVariant');

        $fulfillment = Fulfillment::query()->findOrFail($fulfillmentId);
        app(RetryFulfillment::class)->handle($fulfillment, 'admin', auth()->id());

        $this->noticeVariant = 'success';
        $this->noticeMessage = __('messages.fulfillment_marked_queued');
    }

    public function getFulfillmentsProperty(): LengthAwarePaginator
    {
        return app(GetFulfillments::class)->handle($this->search, $this->statusFilter, $this->perPage);
    }

    public function getSelectedFulfillmentProperty(): ?Fulfillment
    {
        if ($this->selectedFulfillmentId === null) {
            return null;
        }

        return Fulfillment::query()
            ->with([
                'order.user:id,username',
                'orderItem.product:id,name,slug',
                'logs' => fn ($query) => $query->latest('created_at'),
            ])
            ->find($this->selectedFulfillmentId);
    }

    public function getPaymentTransactionProperty(): ?WalletTransaction
    {
        $order = $this->selectedFulfillment?->order;

        if ($order === null) {
            return null;
        }

        return WalletTransaction::query()
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::Purchase->value)
            ->latest('created_at')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    public function getStatusOptionsProperty(): array
    {
        return [
            'all' => __('messages.all'),
            FulfillmentStatus::Queued->value => __('messages.fulfillment_status_queued'),
            FulfillmentStatus::Processing->value => __('messages.fulfillment_status_processing'),
            FulfillmentStatus::Completed->value => __('messages.fulfillment_status_completed'),
            FulfillmentStatus::Failed->value => __('messages.fulfillment_status_failed'),
        ];
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.fulfillments'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseDeliveredPayload(): ?array
    {
        $input = trim((string) $this->deliveredPayloadInput);

        if ($input === '') {
            return null;
        }

        $decoded = json_decode($input, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['code' => $input];
    }

    protected function statusBadgeColor(FulfillmentStatus $status): string
    {
        return match ($status) {
            FulfillmentStatus::Queued => 'gray',
            FulfillmentStatus::Processing => 'amber',
            FulfillmentStatus::Completed => 'green',
            FulfillmentStatus::Failed => 'red',
            default => 'gray',
        };
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.fulfillments') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.fulfillments_intro') }}
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

        <form
            class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60"
            wire:submit.prevent="applyFilters"
        >
            <div class="grid gap-4 lg:grid-cols-4">
                <flux:input
                    name="search"
                    label="{{ __('messages.search') }}"
                    placeholder="{{ __('messages.fulfillment_search_placeholder') }}"
                    wire:model.defer="search"
                    class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                />

                <flux:select
                    name="statusFilter"
                    label="{{ __('messages.status') }}"
                    wire:model.defer="statusFilter"
                >
                    @foreach ($this->statusOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select
                    name="perPage"
                    label="{{ __('messages.per_page') }}"
                    wire:model.defer="perPage"
                >
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>

                <div class="flex items-end gap-2">
                    <flux:button type="submit" variant="primary" icon="magnifying-glass">
                        {{ __('messages.apply') }}
                    </flux:button>
                    <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="resetFilters">
                        {{ __('messages.reset') }}
                    </flux:button>
                </div>
            </div>
        </form>

        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                @if ($this->fulfillments->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_fulfillments_yet') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_fulfillments_hint') }}
                        </flux:text>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800" data-test="fulfillments-table">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.order_number') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.user') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.item') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.provider') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.status') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.updated') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->fulfillments as $fulfillment)
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:key="fulfillment-{{ $fulfillment->id }}">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $fulfillment->order?->order_number ?? '—' }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            #{{ $fulfillment->order_id }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $fulfillment->order?->user?->name ?? __('messages.unknown_user') }}
                                        </div>
                                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $fulfillment->order?->user?->email ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $fulfillment->orderItem?->name ?? __('messages.unknown_item') }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $fulfillment->orderItem?->product?->slug ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $fulfillment->provider }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @php
                                            $retryCount = (int) data_get($fulfillment->meta, 'retry_count', 0);
                                            $refundStatus = data_get($fulfillment->meta, 'refund.status');
                                            $isRefunded = $refundStatus === WalletTransaction::STATUS_POSTED;
                                            $isRefundPending = $refundStatus === WalletTransaction::STATUS_PENDING;
                                        @endphp
                                        <flux:badge color="{{ $this->statusBadgeColor($fulfillment->status) }}">
                                            {{ __('messages.fulfillment_status_'.$fulfillment->status->value) }}
                                        </flux:badge>
                                        @if ($retryCount > 0)
                                            <flux:badge color="amber">
                                                {{ __('messages.retried_times', ['count' => $retryCount]) }}
                                            </flux:badge>
                                        @endif
                                        @if ($refundStatus === 'pending')
                                            <flux:badge color="amber">
                                                {{ __('messages.refund_requested') }}
                                            </flux:badge>
                                        @elseif ($refundStatus === 'posted')
                                            <flux:badge color="green">
                                                {{ __('messages.refunded') }}
                                            </flux:badge>
                                        @elseif ($refundStatus === 'rejected')
                                            <flux:badge color="red">
                                                {{ __('messages.refund_rejected') }}
                                            </flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $fulfillment->updated_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" icon="ellipsis-vertical" />
                                            <flux:menu>
                                                @if ($isRefundPending)
                                                    <flux:menu.item icon="eye" wire:click="openDetails({{ $fulfillment->id }})">
                                                        {{ __('messages.details') }}
                                                    </flux:menu.item>
                                                @elseif ($fulfillment->status === FulfillmentStatus::Failed && $isRefunded)
                                                    <flux:menu.item icon="eye" wire:click="openDetails({{ $fulfillment->id }})">
                                                        {{ __('messages.details') }}
                                                    </flux:menu.item>
                                                @else
                                                    @if ($fulfillment->status === FulfillmentStatus::Queued)
                                                        <flux:menu.item icon="clock" wire:click="markProcessing({{ $fulfillment->id }})">
                                                            {{ __('messages.mark_processing') }}
                                                        </flux:menu.item>
                                                    @endif
                                                    @if (! in_array($fulfillment->status, [FulfillmentStatus::Completed, FulfillmentStatus::Failed], true))
                                                        <flux:menu.item icon="check-circle" wire:click="openCompleteModal({{ $fulfillment->id }})">
                                                            {{ __('messages.mark_completed') }}
                                                        </flux:menu.item>
                                                    @endif
                                                    @if ($fulfillment->status !== FulfillmentStatus::Completed)
                                                        <flux:menu.item icon="exclamation-triangle" wire:click="openFailModal({{ $fulfillment->id }})">
                                                            {{ __('messages.mark_failed') }}
                                                        </flux:menu.item>
                                                    @endif
                                                    @if ($fulfillment->status === FulfillmentStatus::Failed)
                                                        <flux:menu.item icon="arrow-path" wire:click="retryFulfillment({{ $fulfillment->id }})">
                                                            {{ __('messages.retry') }}
                                                        </flux:menu.item>
                                                    @endif
                                                    <flux:menu.separator />
                                                    <flux:menu.item icon="eye" wire:click="openDetails({{ $fulfillment->id }})">
                                                        {{ __('messages.details') }}
                                                    </flux:menu.item>
                                                @endif
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="mt-4 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
            {{ $this->fulfillments->links() }}
        </div>
    </section>

    <flux:modal
        wire:model.self="showDetailsModal"
        variant="floating"
        class="max-w-4xl pt-14"
        @close="closeDetails"
        @cancel="closeDetails"
    >
        @if ($this->selectedFulfillment)
            <div class="space-y-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.fulfillment_details') }}
                        </flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $this->selectedFulfillment->order?->order_number ?? '—' }}
                        </flux:text>
                    </div>
                    <flux:badge color="{{ $this->statusBadgeColor($this->selectedFulfillment->status) }}">
                        {{ __('messages.fulfillment_status_'.$this->selectedFulfillment->status->value) }}
                    </flux:badge>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
                        <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('messages.order_details') }}
                        </div>
                        <div class="mt-3">
                            @php
                                $order = $this->selectedFulfillment->order;
                                $orderItem = $this->selectedFulfillment->orderItem;
                                $currency = $order?->currency ?? 'USD';
                            @endphp
                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div class="rounded-lg border border-zinc-200 bg-white/70 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                                    <dt class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ __('messages.order_id') }}
                                    </dt>
                                    <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $order?->id ?? '—' }}
                                    </dd>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-white/70 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                                    <dt class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ __('messages.username') }}
                                    </dt>
                                    <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $order?->user?->username ?? '—' }}
                                    </dd>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-white/70 p-3 dark:border-zinc-700 dark:bg-zinc-900/40 sm:col-span-2">
                                    <dt class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ __('messages.item') }}
                                    </dt>
                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $orderItem?->name ?? '—' }}
                                    </dd>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-white/70 p-3 dark:border-zinc-700 dark:bg-zinc-900/40 sm:col-span-2">
                                    <dt class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ __('messages.unit_price') }}
                                    </dt>
                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" dir="ltr">
                                        {{ $orderItem?->unit_price ?? '—' }} {{ $currency }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
                        <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('messages.payloads') }}
                        </div>
                        <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                            @php
                                $requirementsPayload = $orderItem?->requirements_payload
                                    ?? data_get($this->selectedFulfillment->meta, 'requirements_payload');
                                $deliveredPayload = data_get($this->selectedFulfillment->meta, 'delivered_payload');
                                $formatPayload = static function (mixed $payload): string {
                                    if (is_string($payload)) {
                                        return $payload;
                                    }

                                    $json = json_encode(
                                        $payload,
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                                    );

                                    return $json === false ? '' : $json;
                                };
                                $hasRequirementsPayload = ! blank($requirementsPayload);
                                $hasDeliveredPayload = ! blank($deliveredPayload);
                            @endphp

                            @if ($this->selectedFulfillment->status === \App\Enums\FulfillmentStatus::Failed && $this->selectedFulfillment->last_error)
                                <flux:callout variant="subtle" icon="exclamation-triangle" class="mb-3">
                                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ __('messages.failure_reason') }}
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $this->selectedFulfillment->last_error }}
                                    </div>
                                </flux:callout>
                            @endif

                            <div class="space-y-3">
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ __('messages.requirements_payload') }}
                                    </div>
                                    @if ($hasRequirementsPayload)
                                        <pre class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-zinc-200 bg-white p-3 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ $formatPayload($requirementsPayload) }}</pre>
                                    @else
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.no_payload') }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ __('messages.delivery_payload') }}
                                    </div>
                                    @if ($hasDeliveredPayload)
                                        <pre class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-zinc-200 bg-white p-3 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ $formatPayload($deliveredPayload) }}</pre>
                                    @else
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.no_payload') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.fulfillment_logs') }}
                    </flux:heading>
                    <div class="space-y-3">
                        @forelse ($this->selectedFulfillment->logs as $log)
                            <div class="rounded-xl border border-zinc-100 p-3 text-sm text-zinc-600 dark:border-zinc-800 dark:text-zinc-300">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $log->message }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $log->created_at?->format('M d, Y H:i') }}
                                    </div>
                                </div>
                                @if ($log->context)
                                    <pre class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-zinc-200 p-4 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                {{ __('messages.no_logs_yet') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal
        wire:model.self="showCompleteModal"
        variant="floating"
        class="max-w-xl"
    >
        <div class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.mark_completed') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.fulfillment_payload_hint') }}
                </flux:text>
            </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800/60 dark:text-zinc-300">
            <div>
                <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.done_payload_toggle') }}
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('messages.done_payload_hint') }}
                </div>
            </div>
            <flux:switch
                class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                wire:model.live="autoDonePayload"
            />
        </div>

            <flux:textarea
                name="deliveredPayloadInput"
                label="{{ __('messages.delivery_payload') }}"
                rows="4"
                wire:model.defer="deliveredPayloadInput"
            />

            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('showCompleteModal', false)">
                    {{ __('messages.cancel') }}
                </flux:button>
                <flux:button variant="primary" wire:click="completeFulfillment">
                    {{ __('messages.confirm') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        wire:model.self="showFailModal"
        variant="floating"
        class="max-w-xl"
    >
        <div class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.mark_failed') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.fulfillment_failure_hint') }}
                </flux:text>
            </div>

            <flux:textarea
                name="failureReason"
                label="{{ __('messages.failure_reason') }}"
                rows="3"
                wire:model.defer="failureReason"
            />
            @error('failureReason')
                <flux:text color="red">{{ $message }}</flux:text>
            @enderror
            <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-800/60">
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-1">
                        <flux:text class="text-sm text-zinc-700 dark:text-zinc-200">
                            {{ __('messages.refund_to_wallet') }}
                        </flux:text>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('messages.refund_to_wallet_hint') }}
                        </flux:text>
                    </div>
                    <flux:switch
                        class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        wire:model.defer="refundAfterFail"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('showFailModal', false)">
                    {{ __('messages.cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="failFulfillment">
                    {{ __('messages.confirm') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
