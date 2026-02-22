<flux:sidebar.group
    expandable
    :expanded="$expanded"
    :heading="$heading"
    :has-badge="$hasBadge"
    class="grid transition-all duration-300 ease"
>
    {{ $slot ?? '' }}
</flux:sidebar.group>
