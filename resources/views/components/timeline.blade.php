@props(['events' => collect()])

@php
    use App\Enums\SystemEventSeverity;
@endphp

<div class="timeline-entity rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" {{ $attributes }}>
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
        {{ __('messages.system_events') }}
    </h3>
    @if ($events->isEmpty())
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.no_system_events_hint') }}</p>
    @else
        <div class="relative">
            <div class="absolute left-3 top-0 h-full w-0.5 bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></div>
            <ul class="space-y-0">
                @foreach ($events as $event)
                    @php
                        $dotColor = $event->is_financial ? 'bg-emerald-500' : ($event->severity === SystemEventSeverity::Critical ? 'bg-red-500' : 'bg-zinc-400 dark:bg-zinc-500');
                    @endphp
                    <li class="relative flex gap-3 pl-8 pb-4 last:pb-0" wire:key="timeline-event-{{ $event->id }}">
                        <div class="absolute left-2 top-1.5 h-2.5 w-2.5 shrink-0 rounded-full border-2 border-white dark:border-zinc-900 {{ $dotColor }}" aria-hidden="true"></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                    {{ $event->created_at?->format('M d, H:i') ?? '—' }}
                                </span>
                                <flux:badge variant="subtle" size="sm" color="zinc">{{ $event->event_type }}</flux:badge>
                                @if ($event->is_financial)
                                    <flux:badge size="sm" color="emerald">{{ __('messages.financial') }}</flux:badge>
                                @endif
                            </div>
                            @if ($event->meta && count((array) $event->meta) > 0)
                                <div class="mt-1.5" x-data="{ expanded: false }">
                                    <button
                                        type="button"
                                        class="text-xs font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300"
                                        x-on:click="expanded = !expanded"
                                        :aria-expanded="expanded"
                                    >
                                        <span x-text="expanded ? '{{ __('messages.show_less') }}' : '{{ __('messages.view_meta') }}'"></span>
                                    </button>
                                    <div x-show="expanded" x-collapse class="mt-1">
                                        <pre class="max-h-32 overflow-auto rounded border border-zinc-200 bg-zinc-50 p-2 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ json_encode($event->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
