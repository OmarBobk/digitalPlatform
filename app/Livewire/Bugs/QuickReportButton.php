<?php

declare(strict_types=1);

namespace App\Livewire\Bugs;

use App\Models\Bug;
use App\Models\BugAttachment;
use App\Models\BugStep;
use App\Services\BugLinkDetectionService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toastable;

class QuickReportButton extends Component
{
    use Toastable;
    use WithFileUploads;

    public bool $open = false;

    public bool $canReport = false;

    public int $currentStep = 1;

    public string $scenario = '';

    public string $subtype = '';

    public string $severity = 'medium';

    public ?string $description = null;

    /** @var array<int, string> */
    public array $steps = ['', ''];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $screenshots = [];

    public string $currentUrl = '';

    public ?string $routeName = null;

    /**
     * @var array<string, array<int, string>>
     */
    protected array $subtypes = [
        'notification' => ['missing', 'duplicate', 'delayed', 'wrong_recipient', 'wrong_sound'],
        'topup_payment' => ['request_failed', 'proof_upload_failed', 'balance_mismatch', 'approval_issue'],
        'fulfillment' => ['stuck', 'wrong_status', 'retry_failed', 'refund_issue'],
        'dashboard' => ['wrong_data', 'stale_data', 'layout_issue'],
        'other' => ['other'],
    ];

    public function mount(): void
    {
        $this->canReport = auth()->check() && auth()->user()->can('manage_bugs');
        $this->currentUrl = request()->fullUrl();
        $this->routeName = request()->route()?->getName();
    }

    public function render()
    {
        return view('livewire.bugs.quick-report-button');
    }

    public function show(): void
    {
        if (! $this->canReport) {
            return;
        }

        $this->open = true;
    }

    public function hide(): void
    {
        $this->open = false;
    }

    public function updatedScenario(): void
    {
        $this->subtype = '';
    }

    public function addStep(): void
    {
        if (count($this->steps) >= 6) {
            return;
        }

        $this->steps[] = '';
    }

    public function removeStep(int $index): void
    {
        if (count($this->steps) <= 2) {
            return;
        }

        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function nextStep(): void
    {
        $this->currentStep = min(5, $this->currentStep + 1);
    }

    public function previousStep(): void
    {
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function submit(): void
    {
        abort_unless(auth()->user()?->can('manage_bugs'), 403);

        $validated = $this->validate([
            'scenario' => ['required', 'string'],
            'subtype' => ['required', 'string'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'description' => ['nullable', 'string', 'max:250'],
            'screenshots' => ['required', 'array', 'min:1', 'max:5'],
            'screenshots.*' => ['image', 'max:5120'],
        ]);

        $normalizedSteps = collect($this->steps)
            ->map(fn (string $step): string => trim($step))
            ->filter()
            ->values();

        if ($normalizedSteps->count() < 2) {
            $this->addError('steps', __('At least 2 steps are required.'));

            return;
        }

        $user = auth()->user();
        $serverNotificationSnapshot = $user->notifications()
            ->latest()
            ->limit(5)
            ->get(['id', 'type', 'created_at'])
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'type' => class_basename((string) $notification->type),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->all();

        $bug = DB::transaction(function () use ($validated, $user, $serverNotificationSnapshot, $normalizedSteps): Bug {
            $bug = Bug::query()->create([
                'user_id' => $user->id,
                'role' => (string) ($user->getRoleNames()->first() ?? 'unknown'),
                'scenario' => $validated['scenario'],
                'subtype' => $validated['subtype'],
                'severity' => $validated['severity'],
                'status' => Bug::STATUS_OPEN,
                'current_url' => $this->currentUrl,
                'route_name' => $this->routeName,
                'description' => $validated['description'] ?? null,
                'metadata' => [
                    'user_id' => $user->id,
                    'role' => (string) ($user->getRoleNames()->first() ?? 'unknown'),
                    'client_timestamp' => null,
                    'server_timestamp' => now()->toIso8601String(),
                    'browser' => request()->userAgent(),
                    'os' => php_uname('s'),
                    'online' => null,
                    'last_pages' => [],
                    'last_notifications' => [],
                    'server_notifications' => $serverNotificationSnapshot,
                ],
            ]);

            foreach ($normalizedSteps as $index => $stepText) {
                BugStep::query()->create([
                    'bug_id' => $bug->id,
                    'step_order' => $index + 1,
                    'step_text' => $stepText,
                ]);
            }

            foreach ($this->screenshots as $file) {
                $path = $file->store('bugs', 'public');

                BugAttachment::query()->create([
                    'bug_id' => $bug->id,
                    'path' => $path,
                    'type' => 'image',
                    'size' => (int) $file->getSize(),
                ]);
            }

            $links = app(BugLinkDetectionService::class)->detectForSubmit(
                request(),
                $this->currentUrl,
                [],
                [],
            );
            foreach ($links as $link) {
                $bug->links()->create($link);
            }

            return $bug;
        });

        $this->reset(['scenario', 'subtype', 'severity', 'description', 'steps', 'screenshots']);
        $this->steps = ['', ''];
        $this->severity = 'medium';
        $this->currentStep = 1;
        $this->open = false;

        $this->dispatch('bug-report-saved', id: $bug->id);
        $this->success(__('Bug report submitted successfully.'));
    }

    /**
     * @return array<int, string>
     */
    public function subtypeOptions(): array
    {
        return $this->subtypes[$this->scenario] ?? [];
    }
}
