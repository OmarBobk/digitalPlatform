<?php

declare(strict_types=1);

namespace App\Livewire\Sidebar;

use App\Enums\FulfillmentStatus;
use App\Models\Fulfillment;
use Livewire\Attributes\On;
use Livewire\Component;

class FulfillmentIndicator extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    #[On('fulfillment-list-updated')]
    public function refreshCount(): void
    {
        if (! auth()->check() || ! auth()->user()->can('view_fulfillments')) {
            $this->count = 0;

            return;
        }

        $this->count = Fulfillment::query()
            ->where('status', FulfillmentStatus::Failed)
            ->count();
    }

    public function render()
    {
        return view('livewire.sidebar.fulfillment-indicator');
    }
}
