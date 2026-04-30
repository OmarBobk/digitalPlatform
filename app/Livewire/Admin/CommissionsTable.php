<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\CommissionStatus;
use App\Enums\FulfillmentStatus;
use App\Models\Commission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toastable;

#[Layout('layouts.app')]
final class CommissionsTable extends Component
{
    use Toastable;
    use WithPagination;

    public int $perPage = 20;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage_settlements'), 403);
    }

    public function markPaid(int $commissionId): void
    {
        abort_unless(auth()->user()?->can('manage_settlements'), 403);

        $commission = Commission::query()
            ->with(['order.items.fulfillments', 'fulfillment:id,status'])
            ->findOrFail($commissionId);

        if ($commission->status !== CommissionStatus::Pending) {
            return;
        }

        if (! $this->canMarkPaid($commission)) {
            $this->error('Cannot mark as paid until fulfillments are completed.');

            return;
        }

        $commission->update([
            'status' => CommissionStatus::Paid,
            'paid_at' => now(),
            'paid_method' => 'manual',
        ]);

        $this->success(__('messages.commission_marked_paid'));
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator<int, Commission> $commissions */
        $commissions = Commission::query()
            ->with([
                'salesperson:id,name,email',
                'order:id,order_number',
                'fulfillment:id,status',
                'order.items:id,order_id',
                'order.items.fulfillments:id,order_item_id,status',
            ])
            ->latest('id')
            ->paginate($this->perPage);

        return view('livewire.admin.commissions-table', [
            'commissions' => $commissions,
        ])->title(__('messages.commissions'));
    }

    public function canMarkPaid(Commission $commission): bool
    {
        if ($commission->status !== CommissionStatus::Pending) {
            return false;
        }

        if ($commission->fulfillment !== null) {
            return $commission->fulfillment->status === FulfillmentStatus::Completed;
        }

        $order = $commission->order;
        if ($order === null || $order->items->isEmpty()) {
            return false;
        }

        foreach ($order->items as $item) {
            if ($item->fulfillments->isEmpty()) {
                return false;
            }

            $allCompleted = $item->fulfillments->every(
                fn ($fulfillment): bool => $fulfillment->status === FulfillmentStatus::Completed
            );

            if (! $allCompleted) {
                return false;
            }
        }

        return true;
    }
}
