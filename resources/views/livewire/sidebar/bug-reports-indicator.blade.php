<span class="inline-flex">
    @if ($count > 0)
        <span
            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-500 px-1 text-[11px] font-semibold text-white"
            aria-label="{{ __('Open or in-progress bug reports') }}: {{ $count }}"
        >
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</span>
