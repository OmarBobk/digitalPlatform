<?php

use App\Actions\Topups\ApproveTopupRequest;
use App\Actions\Topups\GetTopupRequests;
use App\Actions\Topups\RejectTopupRequest;
use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Models\TopupRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public int $perPage = 10;

    public ?string $noticeMessage = null;
    public ?string $noticeVariant = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage_topups'), 403);
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['statusFilter', 'perPage']);
        $this->resetPage();
    }

    public function approveTopup(int $topupRequestId): void
    {
        $this->reset('noticeMessage', 'noticeVariant');

        $topupRequest = TopupRequest::query()->findOrFail($topupRequestId);

        if ($topupRequest->status !== TopupRequestStatus::Pending) {
            return;
        }

        app(ApproveTopupRequest::class)->handle($topupRequest, auth()->id());

        $this->noticeVariant = 'success';
        $this->noticeMessage = __('messages.topup_approved');
    }

    public function rejectTopup(int $topupRequestId): void
    {
        $this->reset('noticeMessage', 'noticeVariant');

        $topupRequest = TopupRequest::query()->findOrFail($topupRequestId);

        if ($topupRequest->status !== TopupRequestStatus::Pending) {
            return;
        }

        app(RejectTopupRequest::class)->handle($topupRequest);

        $this->noticeVariant = 'danger';
        $this->noticeMessage = __('messages.topup_rejected');
    }

    public function getTopupRequestsProperty(): LengthAwarePaginator
    {
        return app(GetTopupRequests::class)->handle($this->statusFilter, $this->perPage);
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.topup_requests'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.topup_requests') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.topup_requests_intro') }}
                </flux:text>
            </div>
        </div>

        @if ($noticeMessage)
            <div class="mt-4">
                <flux:callout
                    variant="subtle"
                    icon="{{ $noticeVariant === 'success' ? 'check-circle' : 'exclamation-triangle' }}"
                >
                    {{ $noticeMessage }}
                </flux:callout>
            </div>
        @endif

        <div class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60">
            <div class="flex flex-wrap items-end gap-4">
                <flux:select
                    name="statusFilter"
                    label="{{ __('messages.status') }}"
                    wire:model.defer="statusFilter"
                >
                    <flux:select.option value="pending">{{ __('messages.pending') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('messages.approved') }}</flux:select.option>
                    <flux:select.option value="rejected">{{ __('messages.rejected') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('messages.all') }}</flux:select.option>
                </flux:select>

                <flux:select
                    name="perPage"
                    label="{{ __('messages.per_page') }}"
                    wire:model.defer="perPage"
                >
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>

                <div class="flex items-center gap-2">
                    <flux:button variant="primary" wire:click="applyFilters">
                        {{ __('messages.apply') }}
                    </flux:button>
                    <flux:button variant="outline" wire:click="resetFilters">
                        {{ __('messages.reset') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                @if ($this->topupRequests->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_topups_yet') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_topups_hint') }}
                        </flux:text>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800" data-test="topups-table">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.user') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.amount') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.method') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.created') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.status') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->topupRequests as $topupRequest)
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:key="topup-{{ $topupRequest->id }}">
                                    <td class="px-5 py-4">
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $topupRequest->user?->name ?? __('messages.unknown_user') }}
                                            </div>
                                            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $topupRequest->user?->email ?? '—' }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-700 dark:text-zinc-200">
                                        {{ $topupRequest->amount }} {{ $topupRequest->currency }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        @if ($topupRequest->method === TopupMethod::ShamCash)
                                            {{ __('messages.topup_method_sham_cash') }}
                                        @else
                                            {{ __('messages.topup_method_eft_transfer') }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $topupRequest->created_at?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @php
                                            $statusColor = match ($topupRequest->status) {
                                                TopupRequestStatus::Approved => 'green',
                                                TopupRequestStatus::Rejected => 'red',
                                                default => 'amber',
                                            };
                                        @endphp
                                        <flux:badge color="{{ $statusColor }}">
                                            {{ __('messages.'.$topupRequest->status->value) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        @if ($topupRequest->status === TopupRequestStatus::Pending)
                                            <div class="flex items-center justify-end gap-2">
                                                <flux:button
                                                    size="sm"
                                                    variant="primary"
                                                    wire:click="approveTopup({{ $topupRequest->id }})"
                                                >
                                                    {{ __('messages.approve') }}
                                                </flux:button>
                                                <flux:button
                                                    size="sm"
                                                    variant="danger"
                                                    wire:click="rejectTopup({{ $topupRequest->id }})"
                                                >
                                                    {{ __('messages.reject') }}
                                                </flux:button>
                                            </div>
                                        @else
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                {{ $this->topupRequests->links() }}
            </div>
        </div>
    </section>
</div>
