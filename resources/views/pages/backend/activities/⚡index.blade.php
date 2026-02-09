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
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

<div class="flex h-full w-full flex-1 flex-col gap-6">
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
        </div>

        <form
            class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60"
            wire:submit.prevent="applyFilters"
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
            <div class="overflow-x-auto">
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
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.created') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.log_name') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.event') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.description') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.causer') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.subject_type') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.properties') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->activities as $activity)
                                @php
                                    $summary = $this->summaryProperties($activity->properties);
                                    $causer = $activity->causer instanceof User ? $activity->causer : null;
                                @endphp
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:key="activity-{{ $activity->id }}">
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $activity->created_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $activity->log_name ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $activity->event ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-700 dark:text-zinc-200">
                                        {{ $activity->description }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($causer)
                                            <div class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $causer->name ?? __('messages.unknown_user') }}
                                            </div>
                                            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $causer->email ?? '—' }}
                                            </div>
                                        @else
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.causer_system') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $this->subjectTypeLabel($activity->subject_type) }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            #{{ $activity->subject_id ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        @if ($summary !== [])
                                            @php
                                                $summaryCount = count($summary);
                                            @endphp
                                            <div class="space-y-1 text-xs" x-data="{ expanded: false }">

                                                @if ($summaryCount > 2)
                                                    <button
                                                        type="button"
                                                        class="text-left cursor-pointer text-xs text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                                                        x-on:click="expanded = !expanded"
                                                        :aria-expanded="expanded"
                                                    >
                                                        @foreach ($summary as $index => $line)
                                                            @if ($index < 2)
                                                                <div>{{ $line }}</div>
                                                            @endif
                                                        @endforeach
                                                        <div x-show="expanded" x-transition.opacity.duration.200ms class="space-y-1">
                                                            @foreach ($summary as $index => $line)
                                                                @if ($index >= 2)
                                                                    <div>{{ $line }}</div>
                                                                @endif
                                                            @endforeach
                                                        </div>  ...
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <flux:button size="sm" variant="ghost" wire:click="openDetails({{ $activity->id }})">
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
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $this->selectedActivity->event ?? $this->selectedActivity->description }}
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
                        <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $this->subjectTypeLabel($this->selectedActivity->subject_type) }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                #{{ $this->selectedActivity->subject_id ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('messages.properties') }}
                    </div>
                    <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                        @if ($this->selectedActivity->properties)
                            @php
                                $properties = $this->selectedActivity->properties instanceof \Illuminate\Support\Collection
                                    ? $this->selectedActivity->properties->toArray()
                                    : $this->selectedActivity->properties;
                            @endphp
                            <pre class="whitespace-pre-wrap break-words rounded-xl border border-zinc-200 bg-white p-4 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @else
                            <span class="text-zinc-500 dark:text-zinc-400">—</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
