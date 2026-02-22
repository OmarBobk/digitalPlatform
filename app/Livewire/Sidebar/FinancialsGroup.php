<?php

declare(strict_types=1);

namespace App\Livewire\Sidebar;

use App\Enums\TopupRequestStatus;
use App\Models\TopupRequest;
use Livewire\Attributes\On;
use Livewire\Component;

class FinancialsGroup extends Component
{
    public bool $hasBadge = false;

    public bool $expanded = true;

    public string $heading = '';

    public function mount(): void
    {
        $this->refreshBadge();
    }

    #[On('topup-list-updated')]
    public function refreshBadge(): void
    {
        if (! auth()->check() || ! auth()->user()->can('manage_topups')) {
            $this->hasBadge = false;

            return;
        }

        $this->hasBadge = TopupRequest::query()
            ->where('status', TopupRequestStatus::Pending)
            ->count() > 0;
    }

    public function render()
    {
        return view('livewire.sidebar.financials-group');
    }
}
