<?php

declare(strict_types=1);

namespace App\Livewire\Sidebar;

use App\Enums\FulfillmentStatus;
use App\Enums\TopupRequestStatus;
use App\Enums\WalletTransactionType;
use App\Models\Bug;
use App\Models\Fulfillment;
use App\Models\TopupRequest;
use App\Models\WalletTransaction;
use Livewire\Attributes\On;
use Livewire\Component;

class SidebarToggleBadge extends Component
{
    public bool $hasBadge = false;

    public function mount(): void
    {
        $this->refreshBadge();
    }

    #[On('fulfillment-list-updated')]
    #[On('topup-list-updated')]
    #[On('bug-inbox-updated')]
    #[On('notification-received')]
    public function refreshBadge(): void
    {
        if (! auth()->check()) {
            $this->hasBadge = false;

            return;
        }

        $user = auth()->user();
        $operationsBadge = false;

        if ($user->can('view_fulfillments')) {
            $operationsBadge = Fulfillment::query()
                ->whereIn('status', [FulfillmentStatus::Queued, FulfillmentStatus::Processing])
                ->exists();
        }

        if (! $operationsBadge && $user->can('view_refunds')) {
            $operationsBadge = WalletTransaction::query()
                ->where('type', WalletTransactionType::Refund)
                ->where('status', WalletTransaction::STATUS_PENDING)
                ->exists();
        }

        $financialsBadge = $user->can('manage_topups')
            && TopupRequest::query()
                ->where('status', TopupRequestStatus::Pending)
                ->exists();

        $bugsBadge = $user->can('manage_bugs')
            && Bug::query()->openOrInProgress()->exists();

        $notificationsBadge = $user->unreadNotifications()->exists();

        $this->hasBadge = $operationsBadge || $financialsBadge || $bugsBadge || $notificationsBadge;
    }

    public function render()
    {
        return view('livewire.sidebar.sidebar-toggle-badge');
    }
}
