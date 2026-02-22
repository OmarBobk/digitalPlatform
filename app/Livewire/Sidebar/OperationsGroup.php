<?php

declare(strict_types=1);

namespace App\Livewire\Sidebar;

use App\Enums\FulfillmentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Fulfillment;
use App\Models\WalletTransaction;
use Livewire\Attributes\On;
use Livewire\Component;

class OperationsGroup extends Component
{
    public bool $hasBadge = false;

    public bool $expanded = true;

    public string $heading = '';

    public function mount(): void
    {
        $this->refreshBadge();
    }

    #[On('fulfillment-list-updated')]
    public function refreshBadge(): void
    {
        if (! auth()->check()) {
            $this->hasBadge = false;

            return;
        }

        $fulfillmentCount = 0;
        $refundCount = 0;
        if (auth()->user()->can('view_fulfillments')) {
            $fulfillmentCount = Fulfillment::query()
                ->whereIn('status', [FulfillmentStatus::Queued, FulfillmentStatus::Processing])
                ->count();
        }
        if (auth()->user()->can('view_refunds')) {
            $refundCount = WalletTransaction::query()
                ->where('type', WalletTransactionType::Refund)
                ->where('status', WalletTransaction::STATUS_PENDING)
                ->count();
        }

        $this->hasBadge = $fulfillmentCount > 0 || $refundCount > 0;
    }

    public function render()
    {
        return view('livewire.sidebar.operations-group');
    }
}
