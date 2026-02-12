<?php

use App\Enums\SystemEventSeverity;
use App\Models\SystemEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $eventType = '';

    public string $severity = 'all';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public bool $financialOnly = false;

    public int $perPage = 20;

    private const MAX_PER_PAGE = 50;

    public ?int $selectedEventId = null;

    public bool $showMetaModal = false;

    /** @var array<int, int> New event ids prepended on broadcast; trimmed to 50. */
    public array $prependedEventIds = [];

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
        $this->reset(['eventType', 'severity', 'dateFrom', 'dateTo', 'financialOnly', 'perPage', 'prependedEventIds']);
        $this->resetPage();
    }

    public function openMeta(int $eventId): void
    {
        $this->selectedEventId = $eventId;
        $this->showMetaModal = true;
    }

    public function closeMeta(): void
    {
        $this->reset(['showMetaModal', 'selectedEventId']);
    }

    #[On('system-event-created')]
    public function prependEventFromBroadcast(array $payload = []): void
    {
        $id = $payload['system_event_id'] ?? null;
        if ($id !== null && is_numeric($id)) {
            $this->prependedEventIds = array_slice(
                array_merge([(int) $id], $this->prependedEventIds),
                0,
                self::MAX_PER_PAGE,
            );
        }
    }

    public function getSelectedEventProperty(): ?SystemEvent
    {
        if ($this->selectedEventId === null) {
            return null;
        }

        return SystemEvent::query()->find($this->selectedEventId);
    }

    public function getEventsProperty(): LengthAwarePaginator
    {
        $query = SystemEvent::query()->latest('created_at');

        if ($this->eventType !== '') {
            $query->where('event_type', 'like', '%'.$this->eventType.'%');
        }

        if ($this->severity !== 'all') {
            $query->where('severity', $this->severity);
        }

        if ($this->dateFrom !== null) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== null) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->financialOnly) {
            $query->where('is_financial', true);
        }

        $perPage = min((int) $this->perPage, self::MAX_PER_PAGE);

        return $query->paginate($perPage);
    }

    /**
     * Merged list for display: on page 1, prepended events (from broadcast) + first page, trimmed to 50. No full refresh.
     *
     * @return \Illuminate\Support\Collection<int, SystemEvent>
     */
    public function getDisplayEventsProperty(): \Illuminate\Support\Collection
    {
        $paginator = $this->events;
        $pageItems = $paginator->getCollection();

        if ($paginator->currentPage() !== 1 || $this->prependedEventIds === []) {
            return $pageItems;
        }

        $prepended = SystemEvent::query()
            ->whereIn('id', $this->prependedEventIds)
            ->orderByDesc('created_at')
            ->get();

        $prependedIds = $prepended->pluck('id')->all();
        $merged = $prepended->merge(
            $pageItems->whereNotIn('id', $prependedIds)
        )->sortByDesc('created_at')->values()->take(self::MAX_PER_PAGE);

        return $merged;
    }

    /**
     * @return array<string, string>
     */
    public function getSeverityOptionsProperty(): array
    {
        return [
            'all' => __('messages.all'),
            'info' => __('messages.severity_info'),
            'warning' => __('messages.severity_warning'),
            'critical' => __('messages.severity_critical'),
        ];
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.system_events'));
    }
};
?>

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    x-data="{ showFilters: false }"
    data-test="system-events-page"
>
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.system_events') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.system_events_intro') }}
                </flux:text>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    type="button"
                    variant="outline"
                    icon="adjustments-horizontal"
                    x-on:click="showFilters = !showFilters"
                    x-bind:aria-expanded="showFilters"
                    aria-controls="system-events-filters"
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
            id="system-events-filters"
            class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60"
            wire:submit.prevent="applyFilters"
            x-show="showFilters"
            x-cloak
            data-test="system-events-filters"
        >
            <div class="grid gap-4 lg:grid-cols-5">
                <flux:input
                    name="eventType"
                    label="{{ __('messages.event') }}"
                    placeholder="e.g. wallet.purchase.debited"
                    wire:model.defer="eventType"
                />
                <flux:select
                    name="severity"
                    label="{{ __('messages.severity') }}"
                    wire:model.defer="severity"
                >
                    @foreach ($this->severityOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" name="dateFrom" label="{{ __('messages.date_from') }}" wire:model.defer="dateFrom" />
                <flux:input type="date" name="dateTo" label="{{ __('messages.date_to') }}" wire:model.defer="dateTo" />
                <div class="flex items-end gap-2">
                    <label class="flex cursor-pointer items-center gap-2">
                        <flux:checkbox wire:model.defer="financialOnly" name="financialOnly" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('messages.financial_only') }}</span>
                    </label>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <flux:button type="submit" variant="primary" icon="magnifying-glass">
                    {{ __('messages.apply') }}
                </flux:button>
                <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="resetFilters">
                    {{ __('messages.reset') }}
                </flux:button>
                <flux:select name="perPage" label="{{ __('messages.per_page') }}" wire:model.defer="perPage" class="max-w-[6rem]">
                    <flux:select.option value="20">20</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
            </div>
        </form>

        <div class="mt-6" wire:key="system-events-timeline">
            @if ($this->displayEvents->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-zinc-100 bg-zinc-50/50 px-6 py-16 text-center dark:border-zinc-800 dark:bg-zinc-800/30">
                    <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.no_system_events') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.no_system_events_hint') }}
                    </flux:text>
                </div>
            @else
                <div class="relative">
                    <div class="absolute left-4 top-0 h-full w-0.5 bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></div>
                    <ul class="space-y-0">
                        @foreach ($this->displayEvents as $event)
                            <li class="relative flex gap-4 pl-10 pb-6" wire:key="event-{{ $event->id }}">
                                @php
                                    $dotColor = $event->is_financial ? 'bg-emerald-500' : ($event->severity === SystemEventSeverity::Critical ? 'bg-red-500' : 'bg-zinc-400 dark:bg-zinc-500');
                                @endphp
                                <div class="absolute left-3 top-1.5 h-3 w-3 shrink-0 rounded-full border-2 border-white dark:border-zinc-900 {{ $dotColor }}" aria-hidden="true"></div>
                                <div class="min-w-0 flex-1 rounded-xl border border-zinc-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium tabular-nums text-zinc-500 dark:text-zinc-400">
                                                {{ $event->created_at?->format('M d, Y H:i:s') ?? '—' }}
                                            </span>
                                            <flux:badge variant="subtle" size="sm" color="zinc">
                                                {{ $event->event_type }}
                                            </flux:badge>
                                            @if ($event->is_financial)
                                                <flux:badge size="sm" color="emerald">{{ __('messages.financial') }}</flux:badge>
                                            @endif
                                            @if ($event->severity === SystemEventSeverity::Critical)
                                                <flux:badge size="sm" color="red">{{ __('messages.severity_critical') }}</flux:badge>
                                            @elseif ($event->severity === SystemEventSeverity::Warning)
                                                <flux:badge size="sm" color="amber">{{ __('messages.severity_warning') }}</flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-3 text-xs text-zinc-600 dark:text-zinc-400">
                                        @if ($event->entity_type)
                                            <span>{{ class_basename($event->entity_type) }} #{{ $event->entity_id }}</span>
                                        @endif
                                        @if ($event->actor_type && $event->actor_id)
                                            @php
                                                $actor = $event->actor_type === User::class ? User::query()->find($event->actor_id) : null;
                                            @endphp
                                            <span>{{ $actor ? $actor->name : (class_basename($event->actor_type).' #'.$event->actor_id) }}</span>
                                        @elseif (! $event->actor_type)
                                            <span>{{ __('messages.causer_system') }}</span>
                                        @endif
                                    </div>
                                    @if ($event->meta && count((array) $event->meta) > 0)
                                        <div class="mt-3" x-data="{ expanded: false }">
                                            <button
                                                type="button"
                                                class="text-sm font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300"
                                                x-on:click="expanded = !expanded"
                                                :aria-expanded="expanded"
                                            >
                                                <span x-text="expanded ? '{{ __('messages.show_less') }}' : '{{ __('messages.view_meta') }}'"></span>
                                            </button>
                                            <div x-show="expanded" x-collapse class="mt-2">
                                                <pre class="max-h-48 overflow-auto rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ json_encode($event->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="mt-2">
                                        <flux:button size="sm" variant="ghost" wire:click="openMeta({{ $event->id }})">
                                            {{ __('messages.view_details') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="mt-4 border-t border-zinc-100 px-0 py-4 dark:border-zinc-800">
            {{ $this->events->links() }}
        </div>
    </section>

    <flux:modal
        wire:model.self="showMetaModal"
        variant="floating"
        class="max-w-2xl"
        @close="closeMeta"
        @cancel="closeMeta"
    >
        @if ($this->selectedEvent)
            <div class="space-y-4">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ $this->selectedEvent->event_type }}
                </flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
                        <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.created') }}</div>
                        <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $this->selectedEvent->created_at?->format('M d, Y H:i:s') ?? '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
                        <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.severity') }}</div>
                        <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $this->selectedEvent->severity?->value ?? '—' }}</div>
                    </div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('messages.meta') }}</div>
                    <div class="mt-2">
                        @if ($this->selectedEvent->meta && count((array) $this->selectedEvent->meta) > 0)
                            <pre class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-xl border border-zinc-200 bg-white p-4 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ json_encode($this->selectedEvent->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @else
                            <span class="text-zinc-500 dark:text-zinc-400">—</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
