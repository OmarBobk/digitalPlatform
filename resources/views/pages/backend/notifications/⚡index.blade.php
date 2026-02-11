<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $typeFilter = '';

    public string $unreadFilter = 'all';

    public int $perPage = 20;

    /** @var array<string> */
    public array $selectedIds = [];

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function getNotificationsProperty(): LengthAwarePaginator
    {
        $query = Auth::user()->notifications()->getQuery();

        if ($this->typeFilter !== '') {
            $query->where('type', $this->typeFilter);
        }

        if ($this->unreadFilter === 'unread') {
            $query->whereNull('read_at');
        }

        return $query->orderByDesc('created_at')->paginate($this->perPage);
    }

    public function getNotificationTypesProperty(): array
    {
        return Auth::user()
            ->notifications()
            ->select('type')
            ->distinct()
            ->pluck('type')
            ->sort()
            ->values()
            ->all();
    }

    public function markAsRead(string $id): void
    {
        $notification = Auth::user()->notifications()->whereKey($id)->first();
        if ($notification !== null) {
            $notification->markAsRead();
        }
    }

    public function markSelectedAsRead(): void
    {
        if ($this->selectedIds === []) {
            return;
        }
        Auth::user()->notifications()->whereIn('id', $this->selectedIds)->update(['read_at' => now()]);
        $this->selectedIds = [];
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.notifications'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6" x-on:notification-received.window="$wire.$refresh()">
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.notifications') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.notifications_intro') }}
                </flux:text>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="typeFilter" class="min-w-[180px]">
                <option value="">{{ __('messages.all_types') }}</option>
                @foreach ($this->notificationTypes as $type)
                    <option value="{{ $type }}">{{ class_basename($type) }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="unreadFilter" class="min-w-[120px]">
                <option value="all">{{ __('messages.all') }}</option>
                <option value="unread">{{ __('messages.unread') }}</option>
            </flux:select>
            @if ($selectedIds !== [])
                <flux:button variant="primary" size="sm" wire:click="markSelectedAsRead">
                    {{ __('messages.mark_selected_read') }}
                </flux:button>
            @endif
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-100 dark:border-zinc-800">
            <div class="overflow-x-auto">
                @if ($this->notifications->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_notifications') }}
                        </flux:heading>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="w-10 px-4 py-3 text-start font-semibold"></th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __('messages.date') }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __('messages.title') }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __('messages.message') }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->notifications as $notification)
                                @php
                                    $data = $notification->data;
                                    $title = $data['title'] ?? '';
                                    $message = $data['message'] ?? '';
                                    $url = $data['url'] ?? null;
                                    $isUnread = $notification->read_at === null;
                                @endphp
                                <tr
                                    wire:key="notif-{{ $notification->id }}"
                                    class="{{ $isUnread ? 'bg-sky-50/50 dark:bg-sky-950/20' : '' }} hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                                >
                                    <td class="px-4 py-3">
                                        @if ($isUnread)
                                            <flux:checkbox wire:model.live="selectedIds" value="{{ $notification->id }}" />
                                        @else
                                            <span class="inline-block size-4"></span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                        {{ $notification->created_at?->diffForHumans() }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $title }}</td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ Str::limit($message, 60) }}</td>
                                    <td class="px-4 py-3 text-end">
                                        @if ($url)
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrow-top-right-on-square"
                                                :href="$url"
                                                wire:navigate
                                                wire:click="markAsRead('{{ $notification->id }}')"
                                            >
                                                {{ __('messages.view') }}
                                            </flux:button>
                                        @else
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                wire:click="markAsRead('{{ $notification->id }}')"
                                            >
                                                {{ __('messages.mark_read') }}
                                            </flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @if ($this->notifications->hasPages())
                <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    {{ $this->notifications->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
