<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class NotificationBellDropdown extends Component
{
    public function getLatestNotificationsProperty(): Collection
    {
        $user = auth()->user();
        if ($user === null) {
            return collect();
        }

        return $user->notifications()->latest()->limit(5)->get();
    }

    public function getUnreadCountProperty(): int
    {
        $user = auth()->user();
        if ($user === null) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    public function markAsRead(string $id): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }
        $notification = $user->notifications()->whereKey($id)->first();
        if ($notification !== null) {
            $notification->markAsRead();
        }
    }

    /**
     * Mark all unread notifications as read when the user opens the dropdown (best practice: mark on view).
     */
    public function markAsReadOnOpen(): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        return view('livewire.notification-bell-dropdown');
    }
}
