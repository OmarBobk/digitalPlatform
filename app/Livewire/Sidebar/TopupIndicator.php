<?php

declare(strict_types=1);

namespace App\Livewire\Sidebar;

use App\Enums\TopupRequestStatus;
use App\Models\TopupRequest;
use Livewire\Attributes\On;
use Livewire\Component;

class TopupIndicator extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    #[On('topup-list-updated')]
    public function refreshCount(): void
    {
        if (! auth()->check() || ! auth()->user()->can('manage_topups')) {
            $this->count = 0;

            return;
        }

        $this->count = TopupRequest::query()
            ->where('status', TopupRequestStatus::Pending)
            ->count();
    }

    public function render()
    {
        return view('livewire.sidebar.topup-indicator');
    }
}
