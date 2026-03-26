<?php

declare(strict_types=1);

namespace App\Livewire\Sidebar;

use Livewire\Attributes\On;
use Livewire\Component;

class NotificationIndicator extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    #[On('notification-received')]
    public function refreshCount(): void
    {
        $user = auth()->user();
        if ($user === null) {
            $this->count = 0;

            return;
        }

        $this->count = $user->unreadNotifications()->count();
    }

    public function render()
    {
        return view('livewire.sidebar.notification-indicator');
    }
}
