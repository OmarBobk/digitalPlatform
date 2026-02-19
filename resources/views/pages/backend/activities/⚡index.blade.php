<?php

use App\Models\Category;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $logName = 'all';
    public string $event = '';
    public string $causerFilter = 'all';
    public string $subjectType = 'all';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public int $perPage = 20;

    public ?int $selectedActivityId = null;
    public bool $showDetailsModal = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view_activities'), 403);
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'logName', 'event', 'causerFilter', 'subjectType', 'dateFrom', 'dateTo', 'perPage']);
        $this->resetPage();
    }

    public function openDetails(int $activityId): void
    {
        $this->selectedActivityId = $activityId;
        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->reset(['showDetailsModal', 'selectedActivityId']);
    }

    #[On('activity-list-updated')]
    public function refreshActivities(): void
    {
        $this->resetPage();
        $this->dispatch('$refresh');
    }

    public function getSelectedActivityProperty(): ?Activity
    {
        if ($this->selectedActivityId === null) {
            return null;
        }

        return Activity::query()
            ->with(['causer', 'subject'])
            ->find($this->selectedActivityId);
    }

    public function getActivitiesProperty(): LengthAwarePaginator
    {
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->latest('created_at');

        if ($this->logName !== 'all') {
            $query->where('log_name', $this->logName);
        }

        if ($this->event !== '') {
            $query->where('event', 'like', '%'.$this->event.'%');
        }

        if ($this->subjectType !== 'all') {
            $query->where('subject_type', $this->subjectType);
        }

        if ($this->dateFrom !== null) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== null) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->causerFilter !== 'all') {
            $this->applyCauserFilter($query);
        }

        if ($this->search !== '') {
            $this->applySearch($query);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * @return array<string, string>
     */
    public function getLogNameOptionsProperty(): array
    {
        return [
            'all' => __('messages.all'),
            'payments' => __('messages.log_name_payments'),
            'orders' => __('messages.log_name_orders'),
            'fulfillment' => __('messages.log_name_fulfillment'),
            'loyalty' => __('messages.log_name_loyalty'),
            'admin' => __('messages.log_name_admin'),
            'system' => __('messages.log_name_system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getCauserOptionsProperty(): array
    {
        return [
            'all' => __('messages.all'),
            'admin' => __('messages.causer_admin'),
            'user' => __('messages.causer_user'),
            'system' => __('messages.causer_system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getSubjectOptionsProperty(): array
    {
        return [
            'all' => __('messages.all'),
            Order::class => __('messages.activity_subject_order'),
            OrderItem::class => __('messages.activity_subject_order_item'),
            Fulfillment::class => __('messages.activity_subject_fulfillment'),
            Wallet::class => __('messages.activity_subject_wallet'),
            WalletTransaction::class => __('messages.activity_subject_wallet_transaction'),
            TopupRequest::class => __('messages.activity_subject_topup_request'),
            Product::class => __('messages.activity_subject_product'),
            Package::class => __('messages.activity_subject_package'),
            Category::class => __('messages.activity_subject_category'),
            User::class => __('messages.activity_subject_user'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function summaryProperties(mixed $properties): array
    {
        if ($properties instanceof \Illuminate\Support\Collection) {
            $properties = $properties->toArray();
        }

        if (! is_array($properties) || $properties === []) {
            return [];
        }

        $keys = [
            'order_id',
            'order_item_id',
            'wallet_id',
            'transaction_id',
            'amount',
            'currency',
            'status_from',
            'status_to',
            'provider',
            'reason',
            'username',
            'role',
            'roles',
            'previous_roles',
            'permissions',
            'previous_permissions',
            'phone',
            'is_active',
            'last_login_at',
            'email_verified_at',
        ];

        $summary = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $properties)) {
                continue;
            }

            $value = $properties[$key];

            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_map(fn ($v) => is_string($v) ? $v : json_encode($v), $value));
            }

            $summary[] = $key.': '.$value;
        }

        return $summary;
    }

    protected function subjectTypeLabel(?string $type): string
    {
        if ($type === null) {
            return __('messages.activity_subject_unknown');
        }

        return $this->subjectOptions[$type] ?? class_basename($type);
    }

    protected function subjectDisplayText(Activity $activity): string
    {
        if ($activity->subject_type === User::class && $activity->subject instanceof User) {
            $user = $activity->subject;

            return 'ID: ' . ($user->id ?? $activity->subject_id ?? '—') . ', ' . __('messages.user') . ': ' . ($user->username ?? $user->name ?? '—');
        }

        $typeLabel = $this->subjectTypeLabel($activity->subject_type);

        return $typeLabel . ' #' . ($activity->subject_id ?? '—');
    }

    /**
     * Format activity properties for the details modal with human-readable labels and values.
     *
     * @return array<string, string>
     */
    protected function formatPropertiesForModal(mixed $properties): array
    {
        if ($properties instanceof \Illuminate\Support\Collection) {
            $properties = $properties->toArray();
        }

        if (! is_array($properties) || $properties === []) {
            return [];
        }

        $out = [];

        foreach ($properties as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $label = __('messages.activity_prop_'.$key);
            if ($label === 'messages.activity_prop_'.$key) {
                $label = str_replace('_', ' ', ucfirst($key));
            }

            if (is_array($value)) {
                $value = implode(', ', array_map(fn ($v) => is_string($v) ? $v : json_encode($v), $value));
            }

            if (is_bool($value)) {
                $value = $value ? __('messages.yes') : __('messages.no');
            }

            if ($value instanceof \Carbon\CarbonInterface || $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $out[$label] = (string) $value;
        }

        return $out;
    }

    protected function logNameColor(?string $logName): string
    {
        return match ($logName) {
            'payments' => 'emerald',
            'orders' => 'blue',
            'fulfillment' => 'amber',
            'admin' => 'violet',
            'system' => 'zinc',
            default => 'gray',
        };
    }

    protected function eventColor(?string $event): string
    {
        if ($event === null) {
            return 'gray';
        }
        if (str_starts_with($event, 'order.')) {
            return 'blue';
        }
        if (str_starts_with($event, 'wallet.') || str_contains($event, 'payment')) {
            return 'emerald';
        }
        if (str_contains($event, 'fulfillment') || str_contains($event, 'refund')) {
            return 'amber';
        }
        if (str_contains($event, 'login') || str_contains($event, 'logout')) {
            return 'violet';
        }
        if (str_contains($event, 'topup')) {
            return 'sky';
        }
        return 'gray';
    }

    protected function causerBadgeColor($causer): string
    {
        if ($causer === null) {
            return 'zinc';
        }
        if ($causer instanceof User && $causer->hasAnyRole(['admin', 'supervisor'])) {
            return 'violet';
        }
        return 'blue';
    }

    private function applyCauserFilter(Builder $query): void
    {
        if ($this->causerFilter === 'system') {
            $query->whereNull('causer_id');

            return;
        }

        $query->whereHasMorph('causer', [User::class], function (Builder $builder): void {
            if ($this->causerFilter === 'admin') {
                $builder->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ['admin', 'supervisor']));

                return;
            }

            $builder->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', ['admin', 'supervisor']));
        });
    }

    private function applySearch(Builder $query): void
    {
        $search = trim($this->search);

        $query->where(function (Builder $builder) use ($search): void {
            $builder->where('description', 'like', '%'.$search.'%')
                ->orWhere('event', 'like', '%'.$search.'%');

            if (ctype_digit($search)) {
                $builder->orWhere('subject_id', (int) $search)
                    ->orWhere('causer_id', (int) $search);
            }

            $builder->orWhereHasMorph('causer', [User::class], function (Builder $causerQuery) use ($search): void {
                $causerQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        });
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.activities'));
    }
};
?>

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    x-data="{ showFilters: false }"
    data-test="activities-page"
>
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.activities') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.activities_intro') }}
                </flux:text>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    type="button"
                    variant="outline"
                    icon="adjustments-horizontal"
                    x-on:click="showFilters = !showFilters"
                    x-bind:aria-expanded="showFilters"
                    aria-controls="activities-filters"
                >
                    {{ __('messages.filters') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="ghost"
                    icon="arrow-path"
                    wire:click="$refresh"
                    wire:loading.attr="disabled"
                >
                    {{ __('messages.refresh') }}
                </flux:button>
            </div>
        </div>

        <form
            id="activities-filters"
            class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60"
            wire:submit.prevent="applyFilters"
            x-show="showFilters"
            x-cloak
            data-test="activities-filters"
            role="search"
            aria-label="{{ __('messages.filters') }}"
        >
            <div class="grid gap-4 lg:grid-cols-6">
                <flux:input
                    name="search"
                    label="{{ __('messages.search') }}"
                    placeholder="{{ __('messages.activity_search_placeholder') }}"
                    wire:model.defer="search"
                    class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                />

                <flux:select
                    name="logName"
                    label="{{ __('messages.log_name') }}"
                    wire:model.defer="logName"
                >
                    @foreach ($this->logNameOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    name="event"
                    label="{{ __('messages.event') }}"
                    placeholder="{{ __('messages.activity_event_placeholder') }}"
                    wire:model.defer="event"
                />

                <flux:select
                    name="causerFilter"
                    label="{{ __('messages.causer') }}"
                    wire:model.defer="causerFilter"
                >
                    @foreach ($this->causerOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select
                    name="subjectType"
                    label="{{ __('messages.subject_type') }}"
                    wire:model.defer="subjectType"
                >
                    @foreach ($this->subjectOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select
                    name="perPage"
                    label="{{ __('messages.per_page') }}"
                    wire:model.defer="perPage"
                >
                    <flux:select.option value="20">20</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-4">
                <flux:input type="date" name="dateFrom" label="{{ __('messages.date_from') }}" wire:model.defer="dateFrom" />
                <flux:input type="date" name="dateTo" label="{{ __('messages.date_to') }}" wire:model.defer="dateTo" />
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
            <div class="max-h-[calc(100vh-14rem)] overflow-auto">
                @if ($this->activities->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_activities') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_activities_hint') }}
                        </flux:text>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800" data-test="activities-table">
                        <thead class="sticky top-0 z-10 bg-zinc-100 text-xs uppercase tracking-wide text-zinc-700 shadow-sm dark:bg-zinc-800 dark:text-zinc-300 dark:shadow-zinc-900/50">
                            <tr>
                                <th class="bg-zinc-100 px-5 py-3 text-start font-semibold dark:bg-zinc-800">{{ __('messages.created') }}</th>
                                <th class="bg-zinc-100 px-5 py-3 text-start font-semibold dark:bg-zinc-800">{{ __('messages.log_name') }}</th>
                                <th class="bg-zinc-100 px-5 py-3 text-start font-semibold dark:bg-zinc-800">{{ __('messages.event') }}</th>
                                <th class="bg-zinc-100 px-5 py-3 text-start font-semibold dark:bg-zinc-800">{{ __('messages.description') }}</th>
                                <th class="bg-zinc-100 px-5 py-3 text-start font-semibold dark:bg-zinc-800">{{ __('messages.causer') }}</th>
                                <th class="bg-zinc-100 px-5 py-3 text-start font-semibold dark:bg-zinc-800">{{ __('messages.subject_type') }}</th>
                                <th class="bg-zinc-100 px-5 py-3 text-start font-semibold dark:bg-zinc-800">{{ __('messages.properties') }}</th>
                                <th class="bg-zinc-100 px-5 py-3 text-end font-semibold dark:bg-zinc-800">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->activities as $index => $activity)
                                @php
                                    $summary = $this->summaryProperties($activity->properties);
                                    $causer = $activity->causer instanceof User ? $activity->causer : null;
                                    $rowBg = $index % 2 === 0 ? 'bg-white dark:bg-zinc-900' : 'bg-zinc-50/50 dark:bg-zinc-800/30';
                                @endphp
                                <tr class="transition {{ $rowBg }} hover:bg-sky-50/50 dark:hover:bg-sky-950/20" wire:key="activity-{{ $activity->id }}">
                                    <td class="px-5 py-4 text-sm font-medium text-zinc-700 dark:text-zinc-300 tabular-nums">
                                        {{ $activity->created_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <flux:badge color="{{ $this->logNameColor($activity->log_name) }}" size="sm">
                                            {{ $activity->log_name ?? '—' }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-5 py-4">
                                        <flux:badge color="{{ $this->eventColor($activity->event) }}" size="sm" variant="subtle">
                                            {{ $activity->event ?? '—' }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-5 py-4 max-w-[180px]"
                                        x-data="{
                                            expanded: false,
                                            desc: @js($activity->description ?? ''),
                                            limit: 38,
                                            get displayText() { return this.expanded || this.desc.length <= this.limit ? this.desc : this.desc.slice(0, this.limit) + '...'; },
                                            get isLong() { return this.desc.length > this.limit; },
                                            showLess: @js(__('messages.show_less')),
                                            more: @js(__('messages.more'))
                                        }"
                                    >
                                        <span class="block max-w-full truncate font-medium text-zinc-900 dark:text-zinc-100" x-show="!expanded" x-cloak x-text="displayText" :title="isLong ? desc : ''"></span>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100 break-words" x-show="expanded" x-cloak x-text="desc" style="display: none;"></span>
                                        <button type="button" class="mt-0.5 block text-left text-xs font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300" x-show="isLong" x-cloak x-on:click="expanded = !expanded" x-text="expanded ? showLess : more" :aria-expanded="expanded"></button>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($causer)
                                            <flux:badge color="{{ $this->causerBadgeColor($causer) }}" size="sm">
                                                {{ $causer->name ?? __('messages.unknown_user') }}
                                            </flux:badge>
                                            <div class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $causer->email ?? '—' }}
                                            </div>
                                        @else
                                            <flux:badge color="zinc" size="sm" variant="subtle">{{ __('messages.causer_system') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $this->subjectDisplayText($activity) }}</span>
                                    </td>
                                    <td class="px-5 py-4 max-w-[200px]"
                                        x-data="{
                                            expanded: false,
                                            lines: @js(collect($summary)->map(fn ($line) => array_combine(['key', 'value'], array_map('trim', array_replace([0 => '', 1 => $line], explode(':', $line, 2)))))->values()->all()),
                                            limit: 30,
                                            get hasExtraLines() { return this.lines.length > 2; },
                                            get extraCount() { return this.lines.length - 2; },
                                            get anyFirstTwoLong() { return this.lines.slice(0, 2).some(l => l.value.length > this.limit); },
                                            get showToggle() { return this.hasExtraLines || this.anyFirstTwoLong; },
                                            showLess: @js(__('messages.show_less')),
                                            more: @js(__('messages.more'))
                                        }"
                                    >
                                        @if ($summary !== [])
                                            <div class="space-y-0.5 text-xs min-w-0">
                                                <template x-for="(line, idx) in lines.slice(0, expanded ? lines.length : 2)" :key="idx">
                                                    <div class="font-medium text-zinc-700 dark:text-zinc-300 min-w-0">
                                                        <span class="shrink-0 text-zinc-500 dark:text-zinc-400" x-text="line.key + ':'"></span>
                                                        <span x-show="!expanded" x-cloak x-text="line.value.length > limit ? line.value.slice(0, limit) + '...' : line.value" :title="line.value.length > limit ? line.value : ''"></span>
                                                        <span class="break-words" x-show="expanded" x-cloak x-text="line.value" style="display: none;"></span>
                                                    </div>
                                                </template>
                                                <button
                                                    type="button"
                                                    class="text-left font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300"
                                                    x-show="showToggle"
                                                    x-cloak
                                                    x-on:click="expanded = !expanded"
                                                    :aria-expanded="expanded"
                                                >
                                                    <span x-text="expanded ? showLess : (hasExtraLines ? `+${extraCount} ${more}` : more)"></span>
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <flux:button size="sm" variant="outline" wire:click="openDetails({{ $activity->id }})">
                                            {{ __('messages.view_details') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="mt-4 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
            {{ $this->activities->links() }}
        </div>
    </section>

    <flux:modal
        wire:model.self="showDetailsModal"
        variant="floating"
        class="max-w-3xl"
        @close="closeDetails"
        @cancel="closeDetails"
    >
        @if ($this->selectedActivity)
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 pe-10">
                    <div class="space-y-1">
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.activity_details') }}
                        </flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400"
                            x-data="{
                                expanded: false,
                                desc: @js($this->selectedActivity->event ?? $this->selectedActivity->description ?? ''),
                                limit: 80,
                                get displayText() { return this.expanded || this.desc.length <= this.limit ? this.desc : this.desc.slice(0, this.limit) + '...'; },
                                get isLong() { return this.desc.length > this.limit; },
                                showLess: @js(__('messages.show_less')),
                                more: @js(__('messages.more'))
                            }"
                        >
                            <span x-show="!expanded" x-cloak x-text="displayText"></span>
                            <span x-show="expanded" x-cloak class="block break-words" style="display: none;" x-text="desc"></span>
                            <button type="button" class="mt-0.5 text-left text-xs font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300" x-show="isLong" x-cloak x-on:click="expanded = !expanded" x-text="expanded ? showLess : more" :aria-expanded="expanded"></button>
                        </flux:text>
                    </div>
                    <flux:badge color="gray">{{ $this->selectedActivity->log_name ?? '—' }}</flux:badge>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
                        <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('messages.causer') }}
                        </div>
                        <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                            @if ($this->selectedActivity->causer instanceof User)
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->selectedActivity->causer->name ?? __('messages.unknown_user') }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $this->selectedActivity->causer->email ?? '—' }}
                                </div>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('messages.causer_system') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
                        <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('messages.subject_type') }}
                        </div>
                        <div class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $this->subjectDisplayText($this->selectedActivity) }}
                        </div>
                    </div>
                </div>

                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('messages.properties') }}
                    </div>
                    <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                        @php
                            $modalProperties = $this->selectedActivity->properties
                                ? $this->formatPropertiesForModal($this->selectedActivity->properties)
                                : [];
                        @endphp
                        @if ($modalProperties !== [])
                            <dl class="space-y-2 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                @foreach ($modalProperties as $label => $value)
                                    <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-2"
                                        x-data="{
                                            expanded: false,
                                            value: @js($value),
                                            limit: 80,
                                            get displayText() { return this.expanded || this.value.length <= this.limit ? this.value : this.value.slice(0, this.limit) + '...'; },
                                            get isLong() { return this.value.length > this.limit; },
                                            showLess: @js(__('messages.show_less')),
                                            more: @js(__('messages.more'))
                                        }"
                                    >
                                        <dt class="shrink-0 font-medium text-zinc-500 dark:text-zinc-400 sm:w-40">{{ $label }}</dt>
                                        <dd class="min-w-0 break-words text-zinc-900 dark:text-zinc-100">
                                            <span x-show="!expanded" x-cloak x-text="displayText"></span>
                                            <span x-show="expanded" x-cloak class="block break-words" style="display: none;" x-text="value"></span>
                                            <button type="button" class="mt-0.5 text-left text-xs font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300" x-show="isLong" x-cloak x-on:click="expanded = !expanded" x-text="expanded ? showLess : more" :aria-expanded="expanded"></button>
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <span class="text-zinc-500 dark:text-zinc-400">—</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
