<?php

use App\DTOs\TimelineEntryDTO;
use App\Enums\SystemEventSeverity;
use App\Models\User;
use App\Services\UserAuditTimelineService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public User $user;

    public bool $financialOnly = false;

    public string $severity = 'all';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $type = '';

    public int $perPage = 50;

    private const MAX_PER_PAGE = 50;

    private const TIMELINE_CAP = 200;

    public function mount(User $user): void
    {
        if (! auth()->user()?->can('manage_users')) {
            abort(404);
        }
        $this->authorize('view', $user);
        $this->user = $user;
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['financialOnly', 'severity', 'dateFrom', 'dateTo', 'type', 'perPage']);
        $this->resetPage();
    }

    /**
     * @return array{financial_only?: bool, severity?: string, date_from?: string|null, date_to?: string|null, type?: string}
     */
    private function filters(): array
    {
        return [
            'financial_only' => $this->financialOnly,
            'severity' => $this->severity,
            'date_from' => $this->dateFrom ?: null,
            'date_to' => $this->dateTo ?: null,
            'type' => $this->type,
        ];
    }

    /**
     * Bounded timeline collection then paginated. Single service call, no N+1.
     *
     * @return LengthAwarePaginator<int, TimelineEntryDTO>
     */
    public function getEntriesPaginatorProperty(): LengthAwarePaginator
    {
        $service = app(UserAuditTimelineService::class);
        $all = $service->buildForUser($this->user, self::TIMELINE_CAP, $this->filters());

        $perPage = min((int) $this->perPage, self::MAX_PER_PAGE);
        $page = $this->getPage();
        $items = $all->forPage($page, $perPage);

        return new Paginator(
            $items,
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
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

    /**
     * @return array<string, string>
     */
    public function getTypeOptionsProperty(): array
    {
        return [
            '' => __('messages.all'),
            'system_event' => __('messages.audit_type_system_event'),
            'wallet_transaction' => __('messages.audit_type_wallet_transaction'),
            'order' => __('messages.audit_type_order'),
            'refund_request' => __('messages.audit_type_refund_request'),
            'fulfillment' => __('messages.audit_type_fulfillment'),
            'loyalty_tier_change' => __('messages.audit_type_loyalty_tier_change'),
        ];
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.audit_timeline'));
    }
};
?>

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    x-data="{ showFilters: false }"
    data-test="user-audit-timeline-page"
>
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.audit_timeline') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $user->name }} · {{ $user->email }}
                </flux:text>
                <div class="mt-1 flex flex-wrap gap-2">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        :href="route('admin.users.show', $user)"
                        wire:navigate
                    >
                        {{ __('messages.details') }}
                    </flux:button>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    type="button"
                    variant="outline"
                    icon="adjustments-horizontal"
                    x-on:click="showFilters = !showFilters"
                    x-bind:aria-expanded="showFilters"
                    aria-controls="audit-timeline-filters"
                >
                    {{ __('messages.filters') }}
                </flux:button>
                <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="resetFilters">
                    {{ __('messages.reset') }}
                </flux:button>
            </div>
        </div>

        <form
            id="audit-timeline-filters"
            class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60"
            wire:submit.prevent="applyFilters"
            x-show="showFilters"
            x-cloak
            data-test="audit-timeline-filters"
        >
            <div class="grid gap-4 lg:grid-cols-5">
                <flux:select
                    name="type"
                    label="{{ __('messages.audit_type') }}"
                    wire:model.defer="type"
                >
                    @foreach ($this->typeOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
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
                <flux:select name="perPage" label="{{ __('messages.per_page') }}" wire:model.defer="perPage" class="max-w-[6rem]">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
            </div>
        </form>

        <div class="mt-6" wire:key="audit-timeline-list">
            @if ($this->entriesPaginator->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-zinc-100 bg-zinc-50/50 px-6 py-16 text-center dark:border-zinc-800 dark:bg-zinc-800/30">
                    <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.no_audit_entries') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.no_audit_entries_hint') }}
                    </flux:text>
                </div>
            @else
                <div class="relative">
                    <div class="absolute left-4 top-0 h-full w-0.5 bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></div>
                    <ul class="space-y-0">
                        @foreach ($this->entriesPaginator as $entry)
                            @php
                                $dto = $entry;
                                $dotColor = $dto->isFinancial
                                    ? 'bg-emerald-500'
                                    : ($dto->severity === SystemEventSeverity::Critical
                                        ? 'bg-red-500'
                                        : 'bg-zinc-400 dark:bg-zinc-500');
                            @endphp
                            <li class="relative flex gap-4 pl-10 pb-6 last:pb-0" wire:key="audit-{{ $dto->sourceKey }}">
                                <div class="absolute left-3 top-1.5 h-3 w-3 shrink-0 rounded-full border-2 border-white dark:border-zinc-900 {{ $dotColor }}" aria-hidden="true"></div>
                                <div class="min-w-0 flex-1 rounded-xl border border-zinc-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium tabular-nums text-zinc-500 dark:text-zinc-400">
                                                {{ $dto->occurredAt->format('M d, Y H:i:s') }}
                                            </span>
                                            <flux:badge variant="subtle" size="sm" color="zinc">{{ $dto->type }}</flux:badge>
                                            @if ($dto->isFinancial)
                                                <flux:badge size="sm" color="emerald">{{ __('messages.financial') }}</flux:badge>
                                            @endif
                                            @if ($dto->severity === SystemEventSeverity::Critical)
                                                <flux:badge size="sm" color="red">{{ __('messages.severity_critical') }}</flux:badge>
                                            @elseif ($dto->severity === SystemEventSeverity::Warning)
                                                <flux:badge size="sm" color="amber">{{ __('messages.severity_warning') }}</flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $dto->title }}
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $dto->description }}
                                    </div>
                                    @if ($dto->meta !== [])
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
                                                <pre class="max-h-48 overflow-auto rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ json_encode($dto->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if ($this->entriesPaginator->hasPages())
            <div class="mt-4 border-t border-zinc-100 px-0 py-4 dark:border-zinc-800">
                {{ $this->entriesPaginator->links() }}
            </div>
        @endif
    </section>
</div>
