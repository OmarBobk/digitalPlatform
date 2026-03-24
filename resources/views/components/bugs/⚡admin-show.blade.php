<?php

use App\Models\Bug;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\TopupRequest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Masmerise\Toaster\Toastable;

new class extends Component
{
    use Toastable;

    public Bug $bug;

    public string $status = Bug::STATUS_OPEN;

    public function mount(Bug $bug): void
    {
        abort_unless(auth()->user()?->can('manage_bugs'), 403);
        $this->bug = $bug->load(['user', 'steps', 'attachments', 'links']);
        $this->status = $this->bug->status;
    }

    #[On('bug-inbox-updated')]
    public function onBugInboxUpdated(mixed $bug_id = null, mixed $reason = null): void
    {
        if ($bug_id !== null && (int) $bug_id !== (int) $this->bug->getKey()) {
            return;
        }

        $this->bug = $this->bug->fresh(['user', 'steps', 'attachments', 'links']);
        $this->status = $this->bug->status;
    }

    public function updateStatus(): void
    {
        $this->validate([
            'status' => ['required', 'in:open,in_progress,resolved,closed'],
        ]);

        $this->bug->update(['status' => $this->status]);
        $this->bug->refresh();
        $this->success(__('Bug status updated.'));
    }

    public function linkUrl(string $type, int $referenceId): ?string
    {
        return match ($type) {
            'order' => route('admin.orders.show', $referenceId),
            'topup' => route('topups'),
            'fulfillment' => route('fulfillments'),
            'notification' => route('admin.notifications.index'),
            default => null,
        };
    }

    public function linkPreview(string $type, int $referenceId): string
    {
        return match ($type) {
            'order' => (string) (Order::query()->find($referenceId)?->order_number ?? 'Order #'.$referenceId),
            'topup' => (string) (TopupRequest::query()->find($referenceId)?->amount ?? 'Topup #'.$referenceId),
            'fulfillment' => (string) (Fulfillment::query()->find($referenceId)?->status?->value ?? 'Fulfillment #'.$referenceId),
            'notification' => (string) (DatabaseNotification::query()->find($referenceId)?->type ?? 'Notification '.$referenceId),
            default => (string) $referenceId,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timeline(): array
    {
        $timeline = data_get($this->bug->metadata, 'timeline', []);

        return is_array($timeline) ? $timeline : [];
    }

    public function render(): View
    {
        return $this->view()->title('Bug #'.$this->bug->id);
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <section class="relative overflow-hidden rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="pointer-events-none absolute -end-24 -top-24 h-56 w-56 rounded-full bg-red-500/10 blur-3xl dark:bg-red-400/10"></div>
        <div class="pointer-events-none absolute -bottom-24 -start-24 h-56 w-56 rounded-full bg-sky-500/10 blur-3xl dark:bg-sky-400/10"></div>

        <div class="relative mb-4">
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.bugs.index')" wire:navigate>
                {{ __('Back') }}
            </flux:button>
        </div>

        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="inline-flex items-center rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium uppercase tracking-wide text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ __('Incident') }} #{{ $bug->id }}
                </div>
                <flux:heading size="xl" level="1" class="font-semibold">
                    {{ str_replace('_', ' ', $bug->scenario) }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $bug->subtype ?: __('General') }} · {{ $bug->created_at?->diffForHumans() }}
                </flux:text>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide
                        @if ($bug->severity === 'critical') bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300
                        @elseif ($bug->severity === 'high') bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300
                        @elseif ($bug->severity === 'medium') bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300
                        @else bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300 @endif">
                        {{ __('Severity') }}: {{ $bug->severity }}
                    </span>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide
                        @if ($bug->status === 'open') bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300
                        @elseif ($bug->status === 'in_progress') bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300
                        @elseif ($bug->status === 'resolved') bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300
                        @else bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 @endif">
                        {{ __('Status') }}: {{ str_replace('_', ' ', $bug->status) }}
                    </span>
                </div>
            </div>

            <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white/80 p-4 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/80">
                <flux:heading size="sm">{{ __('Update Status') }}</flux:heading>
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <flux:select wire:model="status">
                        @foreach (\App\Models\Bug::statusOptions() as $statusOption)
                            <flux:select.option value="{{ $statusOption }}">{{ str_replace('_', ' ', $statusOption) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button variant="primary" wire:click="updateStatus">{{ __('Save') }}</flux:button>
                </div>
                <flux:text class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Use this to keep triage progression accurate for the team.') }}
                </flux:text>
            </div>
        </div>

        <div class="relative mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Reporter') }}</flux:text>
                <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $bug->user?->name ?? '—' }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $bug->role ?: '—' }}</div>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Attachments') }}</flux:text>
                <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $bug->attachments->count() }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Evidence files') }}</div>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Timeline Events') }}</flux:text>
                <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ count($this->timeline()) }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Captured interactions') }}</div>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Trace') }}</flux:text>
                <div class="mt-2 truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $bug->trace_id ?? '—' }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Request correlation') }}</div>
            </div>
        </div>

        <div class="relative mt-6 grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-zinc-100 p-4 dark:border-zinc-800 xl:col-span-2">
                <flux:heading size="sm">{{ __('Reporter Context') }}</flux:heading>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Current URL') }}</flux:text>
                        <div class="mt-1 break-all text-sm text-zinc-700 dark:text-zinc-300">{{ $bug->current_url ?: '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Reported At') }}</flux:text>
                        <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $bug->created_at?->toDateTimeString() ?? '—' }}</div>
                    </div>
                </div>
                <div class="mt-3 rounded-xl border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Description') }}</flux:text>
                    <div class="mt-1 whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">{{ $bug->description ?: __('No description provided.') }}</div>
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-100 p-4 dark:border-zinc-800">
                <flux:heading size="sm">{{ __('Links') }}</flux:heading>
                <div class="mt-3 grid gap-2 text-sm">
                    @forelse ($bug->links as $link)
                        @php $url = $this->linkUrl($link->type, $link->reference_id); @endphp
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                            <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ ucfirst($link->type) }} #{{ $link->reference_id }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $this->linkPreview($link->type, $link->reference_id) }}</div>
                            @if ($url)
                                <div class="mt-2">
                                    <flux:button variant="ghost" size="sm" :href="$url" wire:navigate>{{ __('Open') }}</flux:button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No linked entities.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="rounded-2xl border border-zinc-100 p-4 dark:border-zinc-800">
                <flux:heading size="sm">{{ __('Reproduction Steps') }}</flux:heading>
                <ol class="mt-3 grid gap-2 text-sm">
                    @forelse ($bug->steps as $step)
                        <li class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                            {{ $step->step_text }}
                        </li>
                    @empty
                        <li class="text-zinc-500">{{ __('No steps provided.') }}</li>
                    @endforelse
                </ol>
            </div>

            <div class="rounded-2xl border border-zinc-100 p-4 dark:border-zinc-800">
                <flux:heading size="sm">{{ __('Event Timeline') }}</flux:heading>
                <div class="mt-3 max-h-72 space-y-2 overflow-auto text-xs">
                    @forelse ($this->timeline() as $event)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ data_get($event, 'ts', '—') }} · {{ data_get($event, 'type', 'event') }}</div>
                            <div class="mt-1 text-zinc-600 dark:text-zinc-400">{{ data_get($event, 'action', '—') }} / {{ data_get($event, 'route', '—') }}</div>
                            <div class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Outcome') }}: {{ data_get($event, 'outcome', '—') }}</div>
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No timeline captured.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="rounded-2xl border border-zinc-100 p-4 dark:border-zinc-800">
                <flux:heading size="sm">{{ __('Push Debug') }}</flux:heading>
                <div class="mt-3 rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                    {{ __('Has token') }}:
                    <span class="font-semibold">{{ data_get($bug->metadata, 'push_debug.has_token', false) ? 'yes' : 'no' }}</span>
                </div>
                <div class="mt-3 max-h-52 space-y-2 overflow-auto text-xs">
                    @forelse ((array) data_get($bug->metadata, 'push_debug.recent_push_logs', []) as $log)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200">#{{ data_get($log, 'id') }} · {{ data_get($log, 'status') }} · {{ data_get($log, 'created_at') }}</div>
                            <div class="mt-1 text-zinc-600 dark:text-zinc-400">{{ data_get($log, 'notification_type') }} / {{ data_get($log, 'trace_id') }}</div>
                            @if (data_get($log, 'error'))
                                <div class="mt-1 text-red-600 dark:text-red-400">{{ data_get($log, 'error') }}</div>
                            @endif
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No push logs in snapshot.') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-100 p-4 dark:border-zinc-800">
                <flux:heading size="sm">{{ __('Notification Snapshot') }}</flux:heading>
                <div class="mt-3 max-h-64 space-y-2 overflow-auto text-xs">
                    @forelse ((array) data_get($bug->metadata, 'server_notifications', []) as $notification)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ data_get($notification, 'type') }}</div>
                            <div class="mt-1 text-zinc-600 dark:text-zinc-400">{{ data_get($notification, 'created_at') }}</div>
                            <div class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Trace') }}: {{ data_get($notification, 'trace_id', '—') }}</div>
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No notifications captured.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-zinc-100 p-4 dark:border-zinc-800">
            <div class="flex items-center justify-between gap-2">
                <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $bug->attachments->count() }} {{ __('file(s)') }}</flux:text>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($bug->attachments as $attachment)
                    <a href="{{ route('bug-attachments.show', $attachment) }}" target="_blank" class="group relative block overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/60">
                        <img src="{{ route('bug-attachments.show', $attachment) }}" alt="Bug attachment" class="h-40 w-full object-cover transition duration-300 group-hover:scale-[1.03]" />
                    </a>
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('No attachments.') }}</flux:text>
                @endforelse
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-zinc-100 p-4 dark:border-zinc-800">
            <flux:heading size="sm">{{ __('Raw Metadata') }}</flux:heading>
            <pre class="mt-3 max-h-72 overflow-auto rounded-xl bg-zinc-50 p-3 text-xs dark:bg-zinc-950">{{ json_encode($bug->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </section>
</div>