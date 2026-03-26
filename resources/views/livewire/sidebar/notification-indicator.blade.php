<div x-on:notification-received.window="$wire.refreshCount()">
    @if ($count > 0)
        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-semibold text-white" aria-hidden="true">
            {{ $count > 9 ? '9+' : $count }}
        </span>
    @endif
</div>
