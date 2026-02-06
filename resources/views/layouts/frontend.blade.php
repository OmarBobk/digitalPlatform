<x-layouts::frontend.header :title="$title ?? null">
    <flux:main class="!py-0 !px-3 main-bg">

        {{ $slot }}

        <x-layouts::frontend.footer />
    </flux:main>
</x-layouts::frontend.header>
