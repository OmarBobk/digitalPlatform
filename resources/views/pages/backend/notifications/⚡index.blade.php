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
            ->reorder()
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

    public function markAllAsRead(): void
    {
        Auth::user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);
        $this->selectedIds = [];
    }

    public function getHasUnreadNotificationsProperty(): bool
    {
        return Auth::user()->notifications()->whereNull('read_at')->exists();
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.notifications'));
    }
};
?>

<div
    class="admin-notifications flex h-full w-full flex-1 flex-col gap-8"
    data-test="admin-notifications-page"
    x-on:notification-received.window="$wire.$refresh()"
>
    <section class="cf-reveal rounded-2xl border border-[var(--cf-border)] bg-[var(--cf-card)] p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-2">
                <p class="cf-display text-xs font-semibold tracking-[0.2em] text-[var(--cf-primary)] uppercase">
                    {{ __('messages.nav_operations') }}
                </p>
                <flux:heading size="lg" class="cf-display tracking-tight text-[var(--cf-foreground)]">
                    {{ __('messages.notifications') }}
                </flux:heading>
                <flux:text class="text-sm text-[var(--cf-muted-foreground)]">
                    {{ __('messages.notifications_intro') }}
                </flux:text>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-end gap-3">
            <flux:select
                wire:model.live="typeFilter"
                class="min-w-[180px] focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                label="{{ __('messages.type') }}"
            >
                <flux:select.option value="">{{ __('messages.all_types') }}</flux:select.option>
                @foreach ($this->notificationTypes as $type)
                    <flux:select.option value="{{ $type }}">{{ class_basename($type) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select
                wire:model.live="unreadFilter"
                class="min-w-[140px] focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                label="{{ __('messages.filter') }}"
            >
                <flux:select.option value="all">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="unread">{{ __('messages.unread') }}</flux:select.option>
            </flux:select>
            @if ($this->hasUnreadNotifications)
                <flux:button
                    variant="outline"
                    size="sm"
                    class="border-[var(--cf-border)] text-[var(--cf-muted-foreground)] hover:border-[color-mix(in_srgb,var(--cf-primary)_45%,var(--cf-border))] hover:bg-[var(--cf-card-elevated)] hover:text-[var(--cf-foreground)]"
                    wire:click="markAllAsRead"
                    wire:loading.attr="disabled"
                    wire:target="markAllAsRead"
                >
                    {{ __('messages.mark_all_read') }}
                </flux:button>
            @endif
            @if ($selectedIds !== [])
                <flux:button
                    variant="primary"
                    size="sm"
                    class="!bg-[var(--cf-primary)] !text-[var(--cf-primary-foreground)] transition-colors duration-200 hover:brightness-110"
                    wire:click="markSelectedAsRead"
                >
                    {{ __('messages.mark_selected_read') }}
                </flux:button>
            @endif
        </div>

        <div class="cf-table-shell mt-5">
            <div class="overflow-x-auto">
                @if ($this->notifications->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="cf-display text-[var(--cf-foreground)]">
                            {{ __('messages.no_notifications') }}
                        </flux:heading>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-[var(--cf-border)] text-sm" data-test="admin-notifications-table">
                        <thead class="cf-table-head text-xs uppercase tracking-wide text-[var(--cf-muted-foreground)]">
                            <tr>
                                <th class="w-10 px-5 py-3 text-start font-semibold"></th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.date') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.title') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.message') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--cf-border)]">
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
                                    class="{{ $isUnread ? 'bg-[color-mix(in_srgb,var(--cf-primary-soft)_70%,var(--cf-card))]' : '' }} transition-colors hover:bg-[color-mix(in_srgb,var(--cf-card-elevated)_65%,var(--cf-card))]"
                                >
                                    <td class="px-5 py-4">
                                        @if ($isUnread)
                                            <flux:checkbox wire:model.live="selectedIds" value="{{ $notification->id }}" />
                                        @else
                                            <span class="inline-block size-4"></span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-[var(--cf-muted-foreground)]">
                                        {{ $notification->created_at?->diffForHumans() }}
                                    </td>
                                    <td class="px-5 py-4 font-medium text-[var(--cf-foreground)]">{{ $title }}</td>
                                    <td class="px-5 py-4 text-[var(--cf-muted-foreground)]">{{ Str::limit($message, 60) }}</td>
                                    <td class="px-5 py-4 text-end">
                                        @if ($url)
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrow-top-right-on-square"
                                                class="text-[var(--cf-muted-foreground)] hover:bg-[var(--cf-card-elevated)] hover:text-[var(--cf-foreground)]"
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
                                                class="text-[var(--cf-muted-foreground)] hover:bg-[var(--cf-card-elevated)] hover:text-[var(--cf-foreground)]"
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
                <div class="cf-pagination border-t border-[var(--cf-border)] px-5 py-4">
                    {{ $this->notifications->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
