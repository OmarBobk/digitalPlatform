<?php

declare(strict_types=1);

namespace App\Livewire\Sidebar;

use App\Models\Bug;
use Livewire\Attributes\On;
use Livewire\Component;

class BugReportsIndicator extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    #[On('bug-inbox-updated')]
    public function refreshCount(): void
    {
        if (! auth()->check() || ! auth()->user()->can('manage_bugs')) {
            $this->count = 0;

            return;
        }

        $this->count = Bug::query()->openOrInProgress()->count();
    }

    public function render()
    {
        return view('livewire.sidebar.bug-reports-indicator');
    }
}
