<?php

use App\Enums\CommissionStatus;
use App\Enums\FulfillmentStatus;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public string $sortDir = 'desc';
    public int $perPage = 20;
    public int $usersPerPage = 10;
    public ?string $payoutDateFrom = null;
    public ?string $payoutDateTo = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view_sales'), 403);
    }

    public function applyFilters(): void
    {
        $this->resetPage(pageName: 'ordersPage');
    }

    public function resetFilters(): void
    {
        $this->reset(['statusFilter', 'dateFrom', 'dateTo', 'sortDir', 'perPage']);
        $this->statusFilter = 'all';
        $this->sortDir = 'desc';
        $this->resetPage(pageName: 'ordersPage');
    }

    public function applyPayoutFilters(): void
    {
        $this->resetPage(pageName: 'payoutsPage');
    }

    public function resetPayoutFilters(): void
    {
        $this->reset(['payoutDateFrom', 'payoutDateTo']);
        $this->resetPage(pageName: 'payoutsPage');
    }

    public function getPerformanceCardsProperty(): array
    {
        $salespersonId = (int) auth()->id();

        $allTime = (float) Commission::query()
            ->where('salesperson_id', $salespersonId)
            ->whereIn('status', [CommissionStatus::Pending, CommissionStatus::Paid])
            ->sum('commission_amount');

        $paidThisMonth = (float) Commission::query()
            ->where('salesperson_id', $salespersonId)
            ->where('status', CommissionStatus::Paid)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('commission_amount');

        $pending = (float) Commission::query()
            ->where('salesperson_id', $salespersonId)
            ->where('status', CommissionStatus::Pending)
            ->sum('commission_amount');

        $orders = (int) Commission::query()
            ->where('salesperson_id', $salespersonId)
            ->distinct('order_id')
            ->count('order_id');

        return [
            'all_time_earnings' => $allTime,
            'paid_this_month' => $paidThisMonth,
            'pending_balance' => $pending,
            'total_orders' => $orders,
        ];
    }

    public function getOrdersProperty(): LengthAwarePaginator
    {
        $salespersonId = (int) auth()->id();

        return Commission::query()
            ->where('salesperson_id', $salespersonId)
            ->with([
                'order:id,user_id,order_number,created_at,meta',
                'order.user:id,name,username,phone,country_code,email',
                'order.items:id,order_id,name,amount_mode,requested_amount,amount_unit_label,line_total,requirements_payload',
                'order.items.fulfillments:id,order_item_id,status',
                'fulfillment:id,status',
            ])
            ->when($this->dateFrom, function (Builder $query): void {
                $query->whereHas('order', function (Builder $orderQuery): void {
                    $orderQuery->whereDate('created_at', '>=', $this->dateFrom);
                });
            })
            ->when($this->dateTo, function (Builder $query): void {
                $query->whereHas('order', function (Builder $orderQuery): void {
                    $orderQuery->whereDate('created_at', '<=', $this->dateTo);
                });
            })
            ->when($this->statusFilter !== 'all', function (Builder $query): void {
                $status = $this->statusFilter;
                $query->whereHas('order.items.fulfillments', function (Builder $fulfillmentQuery) use ($status): void {
                    $fulfillmentQuery->where('status', $status);
                });
            })
            ->join('orders', 'orders.id', '=', 'commissions.order_id')
            ->orderBy('orders.created_at', $this->sortDir)
            ->select('commissions.*')
            ->paginate($this->perPage, pageName: 'ordersPage');
    }

    public function getUsersProperty(): LengthAwarePaginator
    {
        $salespersonId = (int) auth()->id();

        return User::query()
            ->where('referred_by_user_id', $salespersonId)
            ->withCount([
                'customerCommissions as referred_orders_count' => function (Builder $query) use ($salespersonId): void {
                    $query->where('salesperson_id', $salespersonId);
                },
            ])
            ->withSum([
                'customerCommissions as referred_commission_sum' => function (Builder $query) use ($salespersonId): void {
                    $query->where('salesperson_id', $salespersonId);
                },
            ], 'commission_amount')
            ->latest('id')
            ->paginate($this->usersPerPage, pageName: 'usersPage');
    }

    public function getPayoutHistoryProperty(): LengthAwarePaginator
    {
        return Commission::query()
            ->where('salesperson_id', (int) auth()->id())
            ->where('status', CommissionStatus::Paid)
            ->whereNotNull('paid_at')
            ->when($this->payoutDateFrom, fn (Builder $query): Builder => $query->whereDate('paid_at', '>=', $this->payoutDateFrom))
            ->when($this->payoutDateTo, fn (Builder $query): Builder => $query->whereDate('paid_at', '<=', $this->payoutDateTo))
            ->with('order:id,order_number')
            ->latest('paid_at')
            ->paginate(10, pageName: 'payoutsPage');
    }

    public function orderFulfillmentLabel(Commission $commission): string
    {
        if ($commission->fulfillment !== null) {
            return match ($commission->fulfillment->status) {
                FulfillmentStatus::Failed => __('messages.fulfillment_status_failed'),
                FulfillmentStatus::Processing => __('messages.fulfillment_status_processing'),
                FulfillmentStatus::Completed => __('messages.fulfillment_status_completed'),
                default => __('messages.fulfillment_status_queued'),
            };
        }

        $order = $commission->order;

        if ($order === null || $order->items->isEmpty()) {
            return __('messages.fulfillment_status_queued');
        }

        $statuses = $order->items
            ->flatMap(fn ($item) => $item->fulfillments)
            ->pluck('status')
            ->filter()
            ->map(fn ($status) => $status instanceof FulfillmentStatus ? $status->value : (string) $status)
            ->all();

        if ($statuses === []) {
            return __('messages.fulfillment_status_queued');
        }

        if (in_array(FulfillmentStatus::Failed->value, $statuses, true)) {
            return __('messages.fulfillment_status_failed');
        }
        if (in_array(FulfillmentStatus::Processing->value, $statuses, true)) {
            return __('messages.fulfillment_status_processing');
        }
        if (in_array(FulfillmentStatus::Queued->value, $statuses, true)) {
            return __('messages.fulfillment_status_queued');
        }

        return __('messages.fulfillment_status_completed');
    }

    public function orderProductsSummary(Commission $commission): string
    {
        $order = $commission->order;
        if ($order === null || $order->items->isEmpty()) {
            return '—';
        }

        return $order->items
            ->map(function ($item): string {
                if ($item->requested_amount !== null) {
                    $label = trim((string) ($item->amount_unit_label ?? ''));
                    $amount = number_format((int) $item->requested_amount, 0, '.', ',');

                    return $item->name.' ('.$amount.($label !== '' ? ' '.$label : '').')';
                }

                return $item->name.' x'.$item->quantity;
            })
            ->implode(', ');
    }

    public function customerInfo(Commission $commission): string
    {
        $order = $commission->order;
        if ($order === null) {
            return '—';
        }

        $customer = $order->user;
        if ($customer === null) {
            return '—';
        }

        $phone = trim(((string) $customer->country_code).' '.((string) $customer->phone));

        return implode(' | ', array_filter([
            $customer->name !== '' ? $customer->name : null,
            $phone !== '' ? $phone : null,
            $customer->username !== null && $customer->username !== '' ? '@'.$customer->username : null,
        ]));

    }

    public function render(): View
    {
        return $this->view()->title(__('messages.salesperson_dashboard'));
    }
};
?>

@php
    $allTimeEarnings = (float) $this->performanceCards['all_time_earnings'];
    $paidThisMonth = (float) $this->performanceCards['paid_this_month'];
    $pendingBalance = (float) $this->performanceCards['pending_balance'];
    $totalOrders = (int) $this->performanceCards['total_orders'];
    $paidShare = $allTimeEarnings > 0 ? min(100, max(0, ($paidThisMonth / $allTimeEarnings) * 100)) : 0;
    $pendingShare = $allTimeEarnings > 0 ? min(100, max(0, ($pendingBalance / $allTimeEarnings) * 100)) : 0;
@endphp

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    x-data="{ activePanel: 'orders', showOrderFilters: true, showPayoutFilters: true, mounted: false }"
    x-init="requestAnimationFrame(() => { mounted = true })"
>
    <section class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="pointer-events-none absolute -top-20 -end-14 size-56 rounded-full bg-accent/15 blur-3xl animate-pulse"></div>
        <div class="pointer-events-none absolute -bottom-24 -start-8 size-52 rounded-full bg-blue-300/10 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-45 [background-image:radial-gradient(circle_at_10%_20%,color-mix(in_oklab,var(--color-accent)_25%,transparent),transparent_35%),radial-gradient(circle_at_100%_0%,color-mix(in_oklab,var(--color-accent)_15%,transparent),transparent_40%)]"></div>
        <div class="relative flex flex-wrap items-center justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">{{ __('messages.salesperson_dashboard') }}</flux:heading>
                <flux:text class="max-w-3xl text-sm text-zinc-600 dark:text-zinc-400">{{ __('messages.salesperson_dashboard_intro') }}</flux:text>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200/80 bg-white/75 p-1 text-xs shadow-sm dark:border-zinc-700 dark:bg-zinc-900/70">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium transition"
                    :class="activePanel === 'orders' ? 'bg-accent text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100'"
                    x-on:click="activePanel = 'orders'"
                >
                    <flux:icon icon="shopping-bag" class="size-3.5" />
                    {{ __('main.my_orders') }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium transition"
                    :class="activePanel === 'earnings' ? 'bg-accent text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100'"
                    x-on:click="activePanel = 'earnings'"
                >
                    <flux:icon icon="banknotes" class="size-3.5" />
                    {{ __('messages.my_earnings_and_commissions') }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium transition"
                    :class="activePanel === 'users' ? 'bg-accent text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100'"
                    x-on:click="activePanel = 'users'"
                >
                    <flux:icon icon="users" class="size-3.5" />
                    {{ __('messages.users_under_salesperson') }}
                </button>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900" :class="mounted ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'">
            <div class="mb-3 h-1 w-14 rounded-full bg-gradient-to-r from-accent to-yellow-400 transition-all duration-300 group-hover:w-20"></div>
            <div class="flex items-center justify-between">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('messages.total_earnings_all_time') }}</flux:text>
                <flux:icon icon="sparkles" class="size-4 text-zinc-400" />
            </div>
            <flux:heading size="lg" class="mt-2 text-zinc-900 dark:text-zinc-100" dir="ltr">${{ number_format((float) $this->performanceCards['all_time_earnings'], 2) }}</flux:heading>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full rounded-full bg-gradient-to-r from-accent to-yellow-400 transition-all duration-700" style="width: {{ number_format($paidShare, 2, '.', '') }}%"></div>
            </div>
        </article>
        <article class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900" :class="mounted ? 'translate-y-0 opacity-100 delay-75' : 'translate-y-2 opacity-0'">
            <div class="mb-3 h-1 w-14 rounded-full bg-gradient-to-r from-emerald-400 to-teal-400 transition-all duration-300 group-hover:w-20"></div>
            <div class="flex items-center justify-between">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('messages.total_paid_this_month') }}</flux:text>
                <flux:icon icon="banknotes" class="size-4 text-zinc-400" />
            </div>
            <flux:heading size="lg" class="mt-2 text-zinc-900 dark:text-zinc-100" dir="ltr">${{ number_format((float) $this->performanceCards['paid_this_month'], 2) }}</flux:heading>
        </article>
        <article class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900" :class="mounted ? 'translate-y-0 opacity-100 delay-100' : 'translate-y-2 opacity-0'">
            <div class="mb-3 h-1 w-14 rounded-full bg-gradient-to-r from-violet-400 to-indigo-400 transition-all duration-300 group-hover:w-20"></div>
            <div class="flex items-center justify-between">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('messages.total_orders_brought_in') }}</flux:text>
                <flux:icon icon="shopping-bag" class="size-4 text-zinc-400" />
            </div>
            <flux:heading size="lg" class="mt-2 text-zinc-900 dark:text-zinc-100" dir="ltr">{{ $this->performanceCards['total_orders'] }}</flux:heading>
            <flux:text class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.orders') }}: {{ $totalOrders }}</flux:text>
        </article>
        <article class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900" :class="mounted ? 'translate-y-0 opacity-100 delay-150' : 'translate-y-2 opacity-0'">
            <div class="mb-3 h-1 w-14 rounded-full bg-gradient-to-r from-amber-400 to-orange-400 transition-all duration-300 group-hover:w-20"></div>
            <div class="flex items-center justify-between">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('messages.pending_commission_balance') }}</flux:text>
                <flux:icon icon="clock" class="size-4 text-zinc-400" />
            </div>
            <flux:heading size="lg" class="mt-2 text-zinc-900 dark:text-zinc-100" dir="ltr">${{ number_format((float) $this->performanceCards['pending_balance'], 2) }}</flux:heading>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-400 transition-all duration-700" style="width: {{ number_format($pendingShare, 2, '.', '') }}%"></div>
            </div>
        </article>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" x-show="activePanel === 'orders'" x-cloak>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="md">{{ __('main.my_orders') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('messages.salesperson_my_orders_intro') }}</flux:text>
            </div>
            <flux:button variant="ghost" type="button" x-on:click="showOrderFilters = ! showOrderFilters">
                {{ __('messages.filter') }}
                <flux:icon icon="chevron-down" class="size-4 transition" x-bind:class="showOrderFilters ? 'rotate-180' : ''" />
            </flux:button>
        </div>

        <form class="grid gap-4 md:grid-cols-5" wire:submit.prevent="applyFilters" x-show="showOrderFilters" x-cloak>
            <flux:input type="date" wire:model.defer="dateFrom" label="{{ __('messages.date_from') }}" />
            <flux:input type="date" wire:model.defer="dateTo" label="{{ __('messages.date_to') }}" />
            <flux:select wire:model.defer="statusFilter" label="{{ __('messages.fulfillment_summary') }}">
                <flux:select.option value="all">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="{{ \App\Enums\FulfillmentStatus::Queued->value }}">{{ __('messages.fulfillment_status_queued') }}</flux:select.option>
                <flux:select.option value="{{ \App\Enums\FulfillmentStatus::Processing->value }}">{{ __('messages.fulfillment_status_processing') }}</flux:select.option>
                <flux:select.option value="{{ \App\Enums\FulfillmentStatus::Completed->value }}">{{ __('messages.fulfillment_status_completed') }}</flux:select.option>
                <flux:select.option value="{{ \App\Enums\FulfillmentStatus::Failed->value }}">{{ __('messages.fulfillment_status_failed') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model.defer="sortDir" label="{{ __('messages.direction') }}">
                <flux:select.option value="desc">{{ __('messages.descending') }}</flux:select.option>
                <flux:select.option value="asc">{{ __('messages.ascending') }}</flux:select.option>
            </flux:select>
            <div class="flex items-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('messages.apply') }}</flux:button>
                <flux:button type="button" wire:click="resetFilters" variant="ghost">{{ __('messages.reset') }}</flux:button>
            </div>
        </form>

        <div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                <thead class="sticky top-0 z-10 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/90 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('messages.order_number') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('messages.product') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('messages.sale_price') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('messages.commission_amount') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('messages.commission_rate_percent') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('messages.commission_status') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('messages.fulfillment_summary') }}</th>
                        <th class="px-4 py-3 text-center font-semibold">{{ __('messages.customer') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->orders as $commission)
                        <tr wire:key="sales-order-{{ $commission->id }}" class="align-middle transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $commission->order?->order_number ?? '#'.$commission->order_id }}</div>
                                <div class="text-xs text-zinc-500">{{ $commission->order?->created_at?->format('Y-m-d H:i') ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                @php
                                    $productSummary = $this->orderProductsSummary($commission);
                                @endphp
                                <div
                                    class="max-w-80 text-sm"
                                    x-data="{ expanded: false, overflow: false, measure() { this.$nextTick(() => { this.overflow = this.$refs.content.scrollHeight > this.$refs.content.clientHeight; }); } }"
                                    x-init="measure()"
                                    x-on:resize.window.debounce.150ms="measure()"
                                >
                                    <p x-ref="content" class="whitespace-pre-line break-words text-zinc-700 dark:text-zinc-300" :class="expanded ? '' : 'line-clamp-2'">
                                        {{ $productSummary }}
                                    </p>
                                    <button
                                        type="button"
                                        class="mt-1 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-[var(--color-accent-content)] hover:bg-zinc-100 hover:text-zinc-900 dark:text-[var(--color-accent)] dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                        x-show="overflow || expanded"
                                        x-cloak
                                        x-on:click="expanded = !expanded; measure()"
                                    >
                                        <span x-show="!expanded">{{ __('messages.more') }}...</span>
                                        <span x-show="expanded">{{ __('messages.show_less') }}</span>
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-zinc-800 dark:text-zinc-200" dir="ltr">${{ number_format((float) $commission->order_total, 2) }}</td>
                            <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">${{ number_format((float) $commission->commission_amount, 2) }}</td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="sky" variant="subtle" dir="ltr">
                                    {{ number_format((float) $commission->commission_rate_percent, 2) }}%
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                @if ($commission->status === \App\Enums\CommissionStatus::Pending)
                                    <flux:badge size="sm" color="amber" variant="subtle">{{ __('messages.commission_status_pending') }}</flux:badge>
                                @elseif ($commission->status === \App\Enums\CommissionStatus::Failed)
                                    <flux:badge size="sm" color="red" variant="subtle">{{ __('messages.commission_status_failed') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green" variant="subtle">{{ __('messages.commission_status_paid') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $fulfillmentLabel = $this->orderFulfillmentLabel($commission);
                                    $badgeColor = 'zinc';
                                    if ($fulfillmentLabel === __('messages.fulfillment_status_failed')) {
                                        $badgeColor = 'red';
                                    } elseif ($fulfillmentLabel === __('messages.fulfillment_status_processing')) {
                                        $badgeColor = 'amber';
                                    } elseif ($fulfillmentLabel === __('messages.fulfillment_status_completed')) {
                                        $badgeColor = 'green';
                                    } elseif ($fulfillmentLabel === __('messages.fulfillment_status_queued')) {
                                        $badgeColor = 'sky';
                                    }
                                @endphp
                                <flux:badge size="sm" :color="$badgeColor" variant="subtle" class="font-semibold">{{ $fulfillmentLabel }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $customerSummary = $this->customerInfo($commission);
                                @endphp
                                <div
                                    class="max-w-72 text-sm"
                                    x-data="{ expanded: false, overflow: false, measure() { this.$nextTick(() => { this.overflow = this.$refs.content.scrollHeight > this.$refs.content.clientHeight; }); } }"
                                    x-init="measure()"
                                    x-on:resize.window.debounce.150ms="measure()"
                                >
                                    <p x-ref="content" class="whitespace-pre-line break-words text-zinc-700 dark:text-zinc-300" :class="expanded ? '' : 'line-clamp-2'">
                                        {{ $customerSummary }}
                                    </p>
                                    <button
                                        type="button"
                                        class="mt-1 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-[var(--color-accent-content)] hover:bg-zinc-100 hover:text-zinc-900 dark:text-[var(--color-accent)] dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                        x-show="overflow || expanded"
                                        x-cloak
                                        x-on:click="expanded = !expanded; measure()"
                                    >
                                        <span x-show="!expanded">{{ __('messages.more') }}...</span>
                                        <span x-show="expanded">{{ __('messages.show_less') }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-zinc-500">{{ __('messages.no_orders_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->orders->links() }}</div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" x-show="activePanel === 'earnings' || activePanel === 'orders'" x-cloak>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <flux:heading size="md">{{ __('messages.my_earnings_and_commissions') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('messages.my_earnings_intro') }}</flux:text>
                </div>
            <flux:button variant="ghost" type="button" x-on:click="showPayoutFilters = ! showPayoutFilters">
                {{ __('messages.filter') }}
                <flux:icon icon="chevron-down" class="size-4 transition" x-bind:class="showPayoutFilters ? 'rotate-180' : ''" />
                </flux:button>
            </div>
            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800/60"><dt>{{ __('messages.total_earned_this_month') }}</dt><dd class="font-semibold" dir="ltr">${{ number_format((float) $this->performanceCards['paid_this_month'], 2) }}</dd></div>
                <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800/60"><dt>{{ __('messages.total_paid_to_date') }}</dt><dd class="font-semibold" dir="ltr">${{ number_format((float) \App\Models\Commission::query()->where('salesperson_id', auth()->id())->where('status', \App\Enums\CommissionStatus::Paid)->sum('commission_amount'), 2) }}</dd></div>
                <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800/60"><dt>{{ __('messages.pending_balance') }}</dt><dd class="font-semibold" dir="ltr">${{ number_format((float) $this->performanceCards['pending_balance'], 2) }}</dd></div>
            </dl>
            <div class="mt-4 space-y-2">
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('messages.total_paid_this_month') }}</span>
                        <span>{{ number_format($paidShare, 0) }}%</span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-400 transition-all duration-700" style="width: {{ number_format($paidShare, 2, '.', '') }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('messages.pending_balance') }}</span>
                        <span>{{ number_format($pendingShare, 0) }}%</span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-400 transition-all duration-700" style="width: {{ number_format($pendingShare, 2, '.', '') }}%"></div>
                    </div>
                </div>
            </div>

            <form class="mt-4 grid gap-3 sm:grid-cols-3" wire:submit.prevent="applyPayoutFilters" x-show="showPayoutFilters" x-cloak>
                <flux:input type="date" wire:model.defer="payoutDateFrom" label="{{ __('messages.date_from') }}" />
                <flux:input type="date" wire:model.defer="payoutDateTo" label="{{ __('messages.date_to') }}" />
                <div class="flex items-end gap-2">
                    <flux:button type="submit" variant="primary">{{ __('messages.apply') }}</flux:button>
                    <flux:button type="button" wire:click="resetPayoutFilters" variant="ghost">{{ __('messages.reset') }}</flux:button>
                </div>
            </form>

            <div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('messages.date') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('messages.amount') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('messages.method') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->payoutHistory as $row)
                            <tr wire:key="payout-{{ $row->id }}" class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2">{{ $row->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-3 py-2 font-semibold" dir="ltr">${{ number_format((float) $row->commission_amount, 2) }}</td>
                                <td class="px-3 py-2">{{ $row->paid_method ?? 'manual' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-4 text-center text-zinc-500">{{ __('messages.no_commissions_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $this->payoutHistory->links() }}</div>
        </article>

        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" x-show="activePanel === 'users' || activePanel === 'orders'" x-cloak>
            <div class="mb-4 space-y-1">
                <flux:heading size="md">{{ __('messages.users_under_salesperson') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('messages.users_under_salesperson_intro') }}</flux:text>
            </div>
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('messages.name') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('messages.email') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('messages.orders') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('messages.commissions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->users as $user)
                            <tr wire:key="ref-user-{{ $user->id }}" class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2">{{ $user->name }}</td>
                                <td class="px-3 py-2">{{ $user->email }}</td>
                                <td class="px-3 py-2">{{ $user->referred_orders_count }}</td>
                                <td class="px-3 py-2 font-semibold" dir="ltr">${{ number_format((float) ($user->referred_commission_sum ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-4 text-center text-zinc-500">{{ __('messages.no_users_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $this->users->links() }}</div>
        </article>
    </section>
</div>
