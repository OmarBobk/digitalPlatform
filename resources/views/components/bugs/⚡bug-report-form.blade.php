<?php

use App\Models\Bug;
use App\Models\AdminDevice;
use App\Models\BugAttachment;
use App\Models\BugStep;
use App\Models\PushLog;
use App\Services\BugLinkDetectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toastable;

new class extends Component
{
    use Toastable;
    use WithFileUploads;

    public int $currentStep = 1;

    public string $scenario = '';

    public string $subtype = '';

    public string $severity = 'medium';

    public ?string $description = null;

    /** @var array<int, string> */
    public array $steps = ['', ''];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $screenshots = [];

    public ?string $clientMetadata = null;

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
        abort_unless(auth()->user()?->can('manage_bugs'), 403);
        $this->currentUrl = request()->fullUrl();
        $this->routeName = request()->route()?->getName();
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

    public function syncClientMetadata(string $payload): void
    {
        $this->clientMetadata = $payload;
    }

    public function submit(): void
    {
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
        $clientMetadata = $this->decodeClientMetadata();
        $serverNotificationSnapshot = $user->notifications()
            ->latest()
            ->limit(5)
            ->get(['id', 'type', 'data', 'created_at'])
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'type' => class_basename((string) $notification->type),
                'trace_id' => (string) data_get($notification->data, 'trace_id', ''),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->all();

        $clientTimestamp = isset($clientMetadata['timestamp'])
            ? Carbon::parse((string) $clientMetadata['timestamp'])
            : now();
        $recentTraceId = collect($serverNotificationSnapshot)
            ->pluck('trace_id')
            ->filter(fn (?string $trace): bool => is_string($trace) && $trace !== '')
            ->first();
        $traceId = is_string($clientMetadata['trace_id'] ?? null) && $clientMetadata['trace_id'] !== ''
            ? (string) $clientMetadata['trace_id']
            : ($recentTraceId ?? (string) Str::uuid());

        $hasToken = AdminDevice::query()
            ->where('user_id', $user->id)
            ->exists();

        $recentPushLogs = PushLog::query()
            ->whereBetween('created_at', [
                $clientTimestamp->copy()->subMinutes(2),
                $clientTimestamp->copy()->addMinutes(2),
            ])
            ->latest('id')
            ->limit(5)
            ->get(['id', 'notification_type', 'notification_id', 'trace_id', 'status', 'error', 'created_at'])
            ->map(fn (PushLog $log): array => [
                'id' => $log->id,
                'notification_type' => $log->notification_type,
                'notification_id' => $log->notification_id,
                'trace_id' => $log->trace_id,
                'status' => $log->status,
                'error' => $log->error,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();

        $potentialDuplicateId = Bug::query()
            ->where('scenario', $validated['scenario'])
            ->where('subtype', $validated['subtype'])
            ->where('route_name', $this->routeName)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('id')
            ->value('id');

        $pageUrlForLinks = is_string($clientMetadata['current_url'] ?? null) && $clientMetadata['current_url'] !== ''
            ? (string) $clientMetadata['current_url']
            : $this->currentUrl;

        $bug = DB::transaction(function () use (
            $validated,
            $user,
            $clientMetadata,
            $serverNotificationSnapshot,
            $normalizedSteps,
            $traceId,
            $hasToken,
            $recentPushLogs,
            $potentialDuplicateId,
            $pageUrlForLinks
        ): Bug {
            $bug = Bug::query()->create([
                'user_id' => $user->id,
                'role' => (string) ($user->getRoleNames()->first() ?? 'unknown'),
                'scenario' => $validated['scenario'],
                'subtype' => $validated['subtype'],
                'severity' => $validated['severity'],
                'status' => Bug::STATUS_OPEN,
                'trace_id' => $traceId,
                'current_url' => $pageUrlForLinks,
                'route_name' => $this->routeName,
                'description' => $validated['description'] ?? null,
                'metadata' => [
                    'user_id' => $user->id,
                    'role' => (string) ($user->getRoleNames()->first() ?? 'unknown'),
                    'client_timestamp' => $clientMetadata['timestamp'] ?? null,
                    'server_timestamp' => now()->toIso8601String(),
                    'browser' => $clientMetadata['browser'] ?? null,
                    'os' => $clientMetadata['os'] ?? null,
                    'online' => $clientMetadata['online'] ?? null,
                    'last_pages' => $clientMetadata['last_pages'] ?? [],
                    'last_notifications' => $clientMetadata['last_notifications'] ?? [],
                    'timeline' => is_array($clientMetadata['timeline'] ?? null) ? $clientMetadata['timeline'] : [],
                    'server_notifications' => $serverNotificationSnapshot,
                    'push_debug' => [
                        'has_token' => $hasToken,
                        'recent_push_logs' => $recentPushLogs,
                    ],
                ],
                'potential_duplicate_of' => $potentialDuplicateId,
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
                $pageUrlForLinks,
                is_array($clientMetadata['last_pages'] ?? null) ? $clientMetadata['last_pages'] : [],
                is_array($clientMetadata['last_notifications'] ?? null) ? $clientMetadata['last_notifications'] : [],
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

        $this->dispatch('bug-report-saved', id: $bug->id);
        if ($bug->potential_duplicate_of !== null) {
            $this->warning(__('Potential duplicate detected: #'.$bug->potential_duplicate_of));
        }
        $this->success(__('Bug report submitted successfully.'));
    }

    /**
     * @return array<int, string>
     */
    public function subtypeOptions(): array
    {
        return $this->subtypes[$this->scenario] ?? [];
    }

    private function decodeClientMetadata(): array
    {
        if ($this->clientMetadata === null || $this->clientMetadata === '') {
            return [];
        }

        $decoded = json_decode($this->clientMetadata, true);

        return is_array($decoded) ? $decoded : [];
    }
};
?>

<div
    x-data="{ initialized: false }"
    x-init="
        if (! initialized && typeof window.__collectBugReporterMetadata === 'function') {
            $wire.syncClientMetadata(JSON.stringify(window.__collectBugReporterMetadata()));
            initialized = true;
        }
    "
    class="space-y-4"
>
    <div class="flex items-center justify-between">
        <flux:heading size="md">{{ __('Report a bug') }}</flux:heading>
        <flux:text class="text-xs text-zinc-500">{{ __('Step') }} {{ $currentStep }}/5</flux:text>
    </div>

    @if ($currentStep === 1)
        <flux:select wire:model.live="scenario" label="{{ __('Scenario') }}">
            <flux:select.option value="">{{ __('Select scenario') }}</flux:select.option>
            <flux:select.option value="notification">{{ __('Notification') }}</flux:select.option>
            <flux:select.option value="topup_payment">{{ __('Topup / Payment') }}</flux:select.option>
            <flux:select.option value="fulfillment">{{ __('Fulfillment') }}</flux:select.option>
            <flux:select.option value="dashboard">{{ __('Dashboard') }}</flux:select.option>
            <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
        </flux:select>
        @error('scenario') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
    @endif

    @if ($currentStep === 2)
        <flux:select wire:model.live="subtype" label="{{ __('Subtype') }}">
            <flux:select.option value="">{{ __('Select subtype') }}</flux:select.option>
            @foreach ($this->subtypeOptions() as $option)
                <flux:select.option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</flux:select.option>
            @endforeach
        </flux:select>
        @error('subtype') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
    @endif

    @if ($currentStep === 3)
        <flux:select wire:model.live="severity" label="{{ __('Severity') }}">
            <flux:select.option value="low">{{ __('Low') }}</flux:select.option>
            <flux:select.option value="medium">{{ __('Medium') }}</flux:select.option>
            <flux:select.option value="high">{{ __('High') }}</flux:select.option>
            <flux:select.option value="critical">{{ __('Critical') }}</flux:select.option>
        </flux:select>
        <flux:textarea wire:model="description" label="{{ __('Short description') }}" rows="3" />
        @error('description') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
    @endif

    @if ($currentStep === 4)
        <div class="space-y-2">
            <flux:text class="text-sm">{{ __('Add at least two clear steps.') }}</flux:text>
            @foreach ($steps as $index => $step)
                <div class="flex items-center gap-2" wire:key="bug-step-{{ $index }}">
                    <flux:input
                        wire:model="steps.{{ $index }}"
                        placeholder="{{ __('Step') }} {{ $index + 1 }}"
                        class="flex-1"
                    />
                    <flux:button type="button" variant="ghost" wire:click="removeStep({{ $index }})">
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            @endforeach
            <flux:button type="button" variant="outline" wire:click="addStep">{{ __('Add step') }}</flux:button>
            @error('steps') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
    @endif

    @if ($currentStep === 5)
        <div class="space-y-2">
            <div class="relative" wire:loading.class="pointer-events-none opacity-60" wire:target="screenshots">
                <flux:input type="file" wire:model="screenshots" accept="image/*" multiple />
            </div>
            <div wire:loading.flex wire:target="screenshots" class="items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                <flux:icon.loading variant="micro" class="text-zinc-500" />
                {{ __('Uploading screenshots…') }}
            </div>
            <flux:text class="text-xs text-zinc-500">{{ __('Upload 1 to 5 screenshots (max 5MB each).') }}</flux:text>
            @error('screenshots') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            @error('screenshots.*') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
    @endif

    <div class="flex items-center justify-between pt-2">
        <flux:button
            type="button"
            variant="ghost"
            wire:click="previousStep"
            :disabled="$currentStep === 1"
            wire:loading.attr="disabled"
            wire:target="screenshots"
        >
            {{ __('Back') }}
        </flux:button>

        @if ($currentStep < 5)
            <flux:button type="button" variant="primary" wire:click="nextStep">
                {{ __('Next') }}
            </flux:button>
        @else
            <flux:button
                type="button"
                variant="primary"
                wire:click="submit"
                wire:loading.attr="disabled"
                wire:target="screenshots,submit"
            >
                <span wire:loading.remove wire:target="screenshots,submit" class="inline-flex items-center justify-center gap-2">
                    {{ __('Submit bug report') }}
                </span>
                <span wire:loading.flex wire:target="screenshots" class="inline-flex items-center justify-center gap-2">
                    <flux:icon.loading variant="micro" />
                    {{ __('Uploading screenshots…') }}
                </span>
                <span wire:loading.flex wire:target="submit" class="inline-flex items-center justify-center gap-2">
                    <flux:icon.loading variant="micro" />
                    {{ __('Submitting…') }}
                </span>
            </flux:button>
        @endif
    </div>
</div>