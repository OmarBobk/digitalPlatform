<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $perPage = 15;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function getNotificationsProperty(): LengthAwarePaginator
    {
        return Auth::user()
            ->notifications()
            ->orderByDesc('created_at')
            ->paginate($this->perPage);
    }

    public function markAsRead(string $id): void
    {
        $notification = Auth::user()->notifications()->whereKey($id)->first();
        if ($notification !== null) {
            $notification->markAsRead();
        }
    }

    public function render(): View
    {
        return $this->view()->layout('layouts::frontend')->title(__('messages.notifications'));
    }
};
?>

<div
    class="mx-auto w-full max-w-3xl px-4 py-8"
    x-data="{ newIds: [] }"
    x-on:notification-received.window="const id = $event.detail?.id; if (id) newIds.push(id); $wire.$refresh(); setTimeout(() => { const i = newIds.indexOf(id); if (i !== -1) newIds.splice(i, 1); }, 8000)"
>
    <div class="mb-6">
        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
            {{ __('messages.notifications') }}
        </flux:heading>
        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('messages.notifications_intro') }}
        </flux:text>
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->notifications as $notification)
            @php
                $data = $notification->data;
                $title = $data['title'] ?? '';
                $message = $data['message'] ?? '';
                $url = $data['url'] ?? null;
                $isUnread = $notification->read_at === null;
            @endphp
            <div
                wire:key="notif-{{ $notification->id }}"
                class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 {{ $isUnread ? 'border-sky-300 dark:border-sky-700' : '' }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">{{ $title }}</flux:heading>
                            <span
                                x-show="newIds.includes('{{ $notification->id }}')"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-90"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="shrink-0 rounded-full bg-emerald-500 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white dark:bg-emerald-600"
                            >
                                {{ __('messages.new') }}
                            </span>
                        </div>
                        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $message }}</flux:text>
                        <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">
                            {{ $notification->created_at?->diffForHumans() }}
                        </flux:text>
                    </div>
                    <div class="shrink-0">
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
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-6 py-12 text-center dark:border-zinc-700 dark:bg-zinc-800/60">
                <flux:text class="text-zinc-600 dark:text-zinc-400">{{ __('messages.no_notifications') }}</flux:text>
            </div>
        @endforelse
    </div>

    @if ($this->notifications->hasPages())
        <div class="mt-6">
            {{ $this->notifications->links() }}
        </div>
    @endif
</div>
