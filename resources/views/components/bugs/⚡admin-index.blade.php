<?php

use App\Models\Bug;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $status = 'all';

    public string $severity = 'all';

    public string $scenario = 'all';

    /**
     * Bumped when realtime bug events fire so the paginator re-queries.
     */
    public int $inboxRealtimeVersion = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage_bugs'), 403);
    }

    #[On('bug-inbox-updated')]
    public function onBugInboxUpdated(): void
    {
        $this->inboxRealtimeVersion++;
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function getBugsProperty(): LengthAwarePaginator
    {
        return Bug::query()
            ->with('user:id,name,email')
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->severity !== 'all', fn ($query) => $query->where('severity', $this->severity))
            ->when($this->scenario !== 'all', fn ($query) => $query->where('scenario', $this->scenario))
            ->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        return $this->view()->title('Bugs');
    }
};
?>

<div class="flex h-full min-w-0 w-full flex-1 flex-col gap-6">
    <section class="relative min-w-0 overflow-hidden rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="pointer-events-none absolute -end-24 -top-24 h-56 w-56 rounded-full bg-red-500/10 blur-3xl dark:bg-red-400/10"></div>
        <div class="pointer-events-none absolute -bottom-24 -start-24 h-56 w-56 rounded-full bg-sky-500/10 blur-3xl dark:bg-sky-400/10"></div>

        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="xl" level="1">{{ __('Bug Inbox') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Triage and inspect product issues reported by users.') }}</flux:text>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Visible Results') }}</flux:text>
                <div class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->bugs->count() }}</div>
            </div>
        </div>

        <form class="relative mt-6 grid gap-4 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 md:grid-cols-4 dark:border-zinc-700 dark:bg-zinc-800/40" wire:submit.prevent="applyFilters">
            <flux:select wire:model.defer="status" label="{{ __('Status') }}" id="bug-filter-status">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                @foreach (\App\Models\Bug::statusOptions() as $statusOption)
                    <flux:select.option value="{{ $statusOption }}">{{ str_replace('_', ' ', $statusOption) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.defer="severity" label="{{ __('Severity') }}" id="bug-filter-severity">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                <flux:select.option value="low">{{ __('Low') }}</flux:select.option>
                <flux:select.option value="medium">{{ __('Medium') }}</flux:select.option>
                <flux:select.option value="high">{{ __('High') }}</flux:select.option>
                <flux:select.option value="critical">{{ __('Critical') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.defer="scenario" label="{{ __('Scenario') }}" id="bug-filter-scenario">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                <flux:select.option value="notification">{{ __('Notification') }}</flux:select.option>
                <flux:select.option value="topup_payment">{{ __('Topup / Payment') }}</flux:select.option>
                <flux:select.option value="fulfillment">{{ __('Fulfillment') }}</flux:select.option>
                <flux:select.option value="dashboard">{{ __('Dashboard') }}</flux:select.option>
                <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
            </flux:select>

            <div class="flex items-end gap-2">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="applyFilters">
                    <span wire:loading.remove wire:target="applyFilters">{{ __('Apply') }}</span>
                    <span wire:loading wire:target="applyFilters">{{ __('Applying...') }}</span>
                </flux:button>
                <flux:button type="button" variant="ghost" wire:click="$refresh">{{ __('Refresh') }}</flux:button>
            </div>
        </form>

        <div class="relative mt-4 min-w-0 overflow-x-auto overscroll-x-contain rounded-2xl border border-zinc-100 [-webkit-overflow-scrolling:touch] dark:border-zinc-800">
            <div wire:loading.flex wire:target="applyFilters" class="absolute inset-0 z-10 items-center justify-center bg-white/70 backdrop-blur-sm dark:bg-zinc-900/70">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Loading bugs...') }}</flux:text>
            </div>

            <table class="min-w-[44rem] w-full table-auto divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('ID') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('User') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Scenario') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Severity') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->bugs as $bug)
                        <tr wire:key="bug-row-{{ $bug->id }}" class="transition hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30">
                            <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-zinc-100">#{{ $bug->id }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $bug->user?->name ?? '—' }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $bug->user?->email ?? __('No email') }}</div>
                            </td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $bug->scenario) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide
                                    @if ($bug->severity === 'critical') bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300
                                    @elseif ($bug->severity === 'high') bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300
                                    @elseif ($bug->severity === 'medium') bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300
                                    @else bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300 @endif">
                                    {{ $bug->severity }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide
                                    @if ($bug->status === 'open') bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300
                                    @elseif ($bug->status === 'in_progress') bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300
                                    @elseif ($bug->status === 'resolved') bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300
                                    @else bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 @endif">
                                    {{ str_replace('_', ' ', $bug->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $bug->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-end">
                                <flux:button variant="ghost" size="sm" :href="route('admin.bugs.show', $bug)" wire:navigate>
                                    {{ __('View') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10">
                                <div class="grid place-items-center gap-2 text-center">
                                    <flux:heading size="sm">{{ __('No bugs found') }}</flux:heading>
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Try changing filters or refresh to load newer reports.') }}
                                    </flux:text>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->bugs->hasPages())
            <div class="mt-4">{{ $this->bugs->links() }}</div>
        @endif
    </section>
</div>