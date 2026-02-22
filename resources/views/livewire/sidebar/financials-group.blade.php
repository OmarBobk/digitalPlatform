<flux:sidebar.group
    expandable
    :expanded="$expanded"
    :heading="$heading"
    :has-badge="$hasBadge"
    class="grid"
>
    {{ $slot ?? '' }}
</flux:sidebar.group>
