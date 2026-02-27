<div class="relative shrink-0" wire:key="sidebar-toggle-badge">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
    @if ($hasBadge)
        <span class="absolute end-0 top-1 size-2.5 shrink-0 rounded-full bg-amber-500 ring-2 ring-zinc-50 dark:ring-zinc-900" aria-hidden="true"></span>
    @endif
</div>
